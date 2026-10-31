<?php

namespace App\Providers;

use App\Enums\PermissionKey;
use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Http\Middleware\EnsureUserBelongsToSchool;
use App\Http\Middleware\IdentifySchool;
use App\Listeners\LogRoleChanges;
use App\Models\User;
use App\Services\Paystack\PaystackClient;
use App\Services\Sms\ArkeselSmsGateway;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\SmsGateway;
use App\Support\Navigation\StaffNavigation;
use App\Support\Phone;
use App\Support\Tenancy\CurrentSchool;
use App\Support\Tenancy\ReservedSubdomains;
use App\Support\Tenancy\SchoolUserProvider;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One holder per request or queued job; Laravel resets scoped instances between them.
        $this->app->scoped(CurrentSchool::class);

        $this->app->bind(StaffNavigation::class, fn () => StaffNavigation::default());

        // Keys come from config/services.php (.env); tests replace the HTTP calls with Http::fake().
        $this->app->bind(PaystackClient::class, fn () => PaystackClient::fromConfig());

        // "log" unless SMS_DRIVER=arkesel (production only, set by Obed).
        $this->app->bind(SmsGateway::class, fn () => config('sms.driver') === 'arkesel'
            ? ArkeselSmsGateway::fromConfig()
            : new LogSmsGateway);

        // {school} never matches a reserved subdomain (e.g. "admin" is the console). Set here in
        // register(), before Fortify's provider boots and adds its {school}.domain routes.
        $reserved = implode('|', array_map(preg_quote(...), ReservedSubdomains::ALL));
        $this->app->make(Router::class)->pattern('school', "(?!(?:{$reserved})\\.)[a-z0-9-]+");
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();

        // Livewire updates post to its own endpoint; re-run these there using the
        // original page's route, so components always have the current school and user.
        Livewire::addPersistentMiddleware([IdentifySchool::class, EnsureUserBelongsToSchool::class, EnsureTwoFactorEnabled::class]);

        // Audit trail for role assignments and permission grants.
        Event::listen(RoleAttachedEvent::class, [LogRoleChanges::class, 'roleAttached']);
        Event::listen(RoleDetachedEvent::class, [LogRoleChanges::class, 'roleDetached']);
        Event::listen(PermissionAttachedEvent::class, [LogRoleChanges::class, 'permissionAttached']);
        Event::listen(PermissionDetachedEvent::class, [LogRoleChanges::class, 'permissionDetached']);

        // Console: only super admins create schools.
        Gate::define('create-schools', fn (User $user): bool => $user->platform_role?->canCreateSchools() ?? false);

        // The register screen: class teachers (attendance.take) and heads/administrators
        // (attendance.edit_locked). Which streams each may open is decided by AttendanceRules.
        Gate::define('take-attendance', fn (User $user): bool => $user->can(PermissionKey::AttendanceTake->value)
            || $user->can(PermissionKey::AttendanceEditLocked->value));

        // Payments and receipts: staff who look at finances, and staff who record payments
        // (an accounts clerk may record without seeing every report).
        Gate::define('view-payments', fn (User $user): bool => $user->can(PermissionKey::FinanceView->value)
            || $user->can(PermissionKey::PaymentsRecord->value));

        // config/auth.php: users are only ever found within the current school.
        Auth::provider('school_eloquent', fn (Application $app, array $config) => new SchoolUserProvider(
            $app->make(Hasher::class),
            $config['model'],
        ));
    }

    /**
     * Parent app sign-in (SPEC section 14: rate limits on login and OTP). Keys include the host,
     * so each school counts separately, and the phone as typed is normalised first.
     */
    protected function configureRateLimiting(): void
    {
        $phone = fn (Request $request): string => $request->getHost().'|'.(Phone::normalise($request->string('phone')->toString()) ?? $request->string('phone')->toString());

        // Texts cost the school credit: 3 per phone per 10 minutes, 10 per IP an hour.
        RateLimiter::for('parent-otp', fn (Request $request) => [
            Limit::perMinutes(10, 3)->by('otp-phone|'.$phone($request)),
            Limit::perHour(10)->by('otp-ip|'.$request->ip()),
        ]);

        // Each code allows 5 wrong tries; this stops guessing across many codes.
        RateLimiter::for('parent-code', fn (Request $request) => [
            Limit::perMinute(5)->by('code-phone|'.$phone($request)),
            Limit::perMinute(20)->by('code-ip|'.$request->ip()),
        ]);

        RateLimiter::for('parent-login', fn (Request $request) => [
            Limit::perMinute(5)->by('login|'.$phone($request).'|'.$request->ip()),
            Limit::perMinute(30)->by('login-ip|'.$request->ip()),
        ]);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
