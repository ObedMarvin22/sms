<?php

namespace App\Providers;

use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Http\Middleware\EnsureUserBelongsToSchool;
use App\Http\Middleware\IdentifySchool;
use App\Listeners\LogRoleChanges;
use App\Models\User;
use App\Support\Tenancy\CurrentSchool;
use App\Support\Tenancy\ReservedSubdomains;
use App\Support\Tenancy\SchoolUserProvider;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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

        // config/auth.php: users are only ever found within the current school.
        Auth::provider('school_eloquent', fn (Application $app, array $config) => new SchoolUserProvider(
            $app->make(Hasher::class),
            $config['model'],
        ));
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
