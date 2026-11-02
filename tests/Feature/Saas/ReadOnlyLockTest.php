<?php

require_once __DIR__.'/../Fees/helpers.php';
require_once __DIR__.'/../ParentApi/helpers.php';

use App\Actions\Saas\RecordSaasPayment;
use App\Actions\Saas\VoidSaasInvoice;
use App\Enums\PlatformRoleKey;
use App\Enums\RoleKey;
use App\Exceptions\SchoolIsReadOnly;
use App\Jobs\SendAnnouncementNotices;
use App\Livewire\Console\Schools\Billing;
use App\Models\Announcement;
use App\Models\FeeItem;
use App\Models\Guardian;
use App\Models\Payment;
use App\Models\SaasInvoice;
use App\Models\School;
use App\Models\User;
use App\Support\Saas\ReadOnlyGuard;
use App\Support\Saas\SaasLock;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| Pilot preparation step 4 (SPEC section 8): a school whose Orange Tree invoice is still unpaid
| 14 days after its due date becomes read-only for staff. Parents, webhooks, jobs and the
| console are never affected; fee payments can still be recorded.
|
| Livewire::test() does not run route middleware, so the lock is tested with real requests:
| a page, then a Livewire update posted the way the browser does (livewireCall()).
*/

beforeEach(function () {
    setUpParentSchool(); // Greenhill on 28 Oct 2026, with SMS credit
    $this->invoice = SaasInvoice::factory()->create([
        'school_id' => $this->school->id, 'term_id' => $this->term1->id,
        'amount' => 150000, 'issued_at' => '2026-10-01 01:00:00', 'due_on' => '2026-10-31',
    ]); // locks on 15 Nov (31 Oct + 14 days grace + 1)
    $this->bursar = staffWith(RoleKey::Bursar);
    $this->superAdmin = User::factory()->platform()->create();
});

/**
 * Loads a staff page and calls a method on its Livewire component through Livewire's real
 * update endpoint (so the route's persistent middleware runs, as in the browser).
 *
 * @param  array<string, mixed>  $updates
 */
function livewireCall(string $page, string $method, array $updates = []): TestResponse
{
    $html = test()->get("http://greenhill.localhost/{$page}")->assertOk()->getContent();
    preg_match_all('/wire:snapshot="([^"]+)"/', (string) $html, $matches);
    $snapshot = collect($matches[1])->map(fn (string $s) => html_entity_decode($s, ENT_QUOTES))
        ->first(fn (string $s) => ! str_contains($s, '"name":"flux'));

    // A new request in production starts with fresh per-request state (the guard off); the test
    // process keeps it between requests, so clear it as the server would.
    app()->forgetScopedInstances();

    return test()->withHeaders(['X-Livewire' => '1'])->postJson(route('default-livewire.update'), [
        'components' => [['snapshot' => $snapshot, 'updates' => $updates, 'calls' => [['path' => '', 'method' => $method, 'params' => []]]]],
    ]);
}

/**
 * The toast texts a Livewire response asked the browser to show.
 *
 * @return list<string>
 */
function toasts(TestResponse $response): array
{
    return collect($response->json('components.0.effects.dispatches') ?? [])
        ->where('name', 'toast-show')->pluck('params.slots.text')->values()->all();
}

describe('the lock state', function () {
    it('is open while nothing is overdue', function () {
        expect(SaasLock::for($this->school)->status)->toBe(SaasLock::OPEN); // due 31 Oct, today 28 Oct
    });

    it('warns from the day after the due date, with the date it locks', function () {
        $this->travelTo('2026-11-01 09:00:00');

        $lock = SaasLock::for($this->school);
        expect($lock->status)->toBe(SaasLock::WARNING)
            ->and($lock->invoice?->id)->toBe($this->invoice->id)
            ->and($lock->locksOn?->toDateString())->toBe('2026-11-15');
    });

    it('locks 14 days after the due date', function () {
        $this->travelTo('2026-11-14 23:00:00');
        expect(SaasLock::for($this->school)->status)->toBe(SaasLock::WARNING);

        $this->travelTo('2026-11-15 00:01:00');
        expect(SaasLock::for($this->school)->isLocked())->toBeTrue();
    });

    it('reopens at once when the invoice is paid, or voided', function (string $how) {
        $this->travelTo('2026-11-20 09:00:00');
        expect(SaasLock::for($this->school)->isLocked())->toBeTrue();

        $how === 'paid'
            ? app(RecordSaasPayment::class)->handle($this->superAdmin, $this->invoice, ['amount' => '1500', 'paid_on' => '2026-11-20', 'method' => 'bank_transfer'])
            : app(VoidSaasInvoice::class)->handle($this->superAdmin, $this->invoice, 'Wrong count');

        expect(SaasLock::for($this->school)->status)->toBe(SaasLock::OPEN);
    })->with(['paid', 'voided']);

    it('stays locked after a part payment', function () {
        $this->travelTo('2026-11-20 09:00:00');
        app(RecordSaasPayment::class)->handle($this->superAdmin, $this->invoice, ['amount' => '500', 'paid_on' => '2026-11-20', 'method' => 'cash']);

        expect(SaasLock::for($this->school)->isLocked())->toBeTrue();
    });

    it('stays open while Orange Tree keeps the school open, and locks the day after', function () {
        $this->school->forceFill(['saas_lock_override_until' => '2026-12-01', 'saas_lock_override_reason' => 'Plan agreed'])->save();

        $this->travelTo('2026-12-01 18:00:00');
        $lock = SaasLock::for($this->school->fresh());
        expect($lock->status)->toBe(SaasLock::WARNING)
            ->and($lock->locksOn?->toDateString())->toBe('2026-12-02')
            ->and($lock->keptOpenUntil?->toDateString())->toBe('2026-12-01');

        $this->travelTo('2026-12-02 08:00:00');
        expect(SaasLock::for($this->school->fresh())->isLocked())->toBeTrue();
    });

    it("never locks a school for another school's invoice", function () {
        $hillview = School::factory()->active()->create(['subdomain' => 'hillview']);
        $this->travelTo('2026-11-20 09:00:00');

        expect(SaasLock::for($hillview)->status)->toBe(SaasLock::OPEN);
    });
});

describe('the database guard', function () {
    it('refuses writes to school-owned tables, including the school row and roles, and nothing else', function () {
        $guard = app(ReadOnlyGuard::class);
        $guard->switchOn();

        expect(fn () => FeeItem::create(['name' => 'Bus', 'category' => 'transport']))->toThrow(SchoolIsReadOnly::class)
            ->and(fn () => DB::table('students')->update(['first_name' => 'X']))->toThrow(SchoolIsReadOnly::class)
            ->and(fn () => DB::table('schools')->where('id', $this->school->id)->update(['name' => 'X']))->toThrow(SchoolIsReadOnly::class)
            ->and(fn () => DB::table('model_has_roles')->delete())->toThrow(SchoolIsReadOnly::class);

        // Not school-owned: users (own password), sessions, cache, jobs, activity log.
        $this->bursar->forceFill(['name' => 'New name'])->save();
        DB::table('cache')->insert(['key' => 'probe', 'value' => '1', 'expiration' => time() + 60]);
        expect($this->bursar->fresh()->name)->toBe('New name');
    });

    it('is off for every new request and job', function () {
        app(ReadOnlyGuard::class)->switchOn();
        app()->forgetScopedInstances(); // what Laravel does between requests and jobs

        expect(app(ReadOnlyGuard::class)->isOn())->toBeFalse();
    });
});

describe('staff screens of a locked school', function () {
    beforeEach(function () {
        $this->travelTo('2026-11-20 09:00:00');
        $this->actingAs($this->bursar);
    });

    it('refuses a save with a message and changes nothing', function () {
        $before = FeeItem::count();

        $response = livewireCall('fees/items', 'save', ['form.name' => 'Excursion', 'form.category' => 'other']);

        $response->assertOk();
        expect(toasts($response))->toBe([SchoolIsReadOnly::MESSAGE])
            ->and(FeeItem::count())->toBe($before);
    });

    it('shows the read-only banner on every page', function () {
        $this->get('http://greenhill.localhost/dashboard')
            ->assertSee('This school is read-only')
            ->assertSee($this->invoice->number);
    });

    it('opens every staff page just as when the school is open', function () {
        $this->actingAs(staffWith(RoleKey::Proprietor));

        $statuses = function (): array {
            $seen = [];
            foreach (app('router')->getRoutes()->getRoutes() as $route) {
                /** @var Route $route */
                if (in_array('GET', $route->methods(), true) && in_array('saas.read-only', $route->gatherMiddleware(), true)
                    && ! str_contains($route->uri(), '{')) {
                    $seen[$route->uri()] = $this->get('http://greenhill.localhost/'.ltrim($route->uri(), '/'))->baseResponse->getStatusCode();
                }
            }

            return $seen;
        };

        $this->travelTo('2026-10-28 09:00:00');
        $open = $statuses();
        $this->travelTo('2026-11-20 09:00:00');
        expect(SaasLock::for($this->school)->isLocked())->toBeTrue();

        expect($statuses())->toBe($open)
            ->and(count($open))->toBeGreaterThan(25)
            ->and(array_filter($open, fn (int $status) => $status >= 500))->toBe([]);
    });

    it('still records fee payments, through the payments screen', function () {
        $student = feeStudent('Ama'); // admitted in setup, not through a locked screen

        $response = livewireCall('fees/payments', 'record', [
            'studentId' => (string) $student->id,
            'form.amount' => '100', 'form.method' => 'cash', 'form.paid_on' => '2026-11-20',
        ]);

        $response->assertOk();
        expect(toasts($response)[0] ?? '')->toStartWith('Receipt ')
            ->and(Payment::where('student_id', $student->id)->count())->toBe(1);
    });
});

describe('a school that is only overdue', function () {
    it('warns with the lock date and still saves', function () {
        $this->travelTo('2026-11-05 09:00:00');
        $this->actingAs($this->bursar);

        $this->get('http://greenhill.localhost/dashboard')
            ->assertSee("Orange Tree invoice {$this->invoice->number} is overdue")
            ->assertSee('15 Nov 2026');

        $before = FeeItem::count();
        $response = livewireCall('fees/items', 'save', ['form.name' => 'Excursion', 'form.category' => 'other']);
        expect(toasts($response))->not->toContain(SchoolIsReadOnly::MESSAGE)
            ->and(FeeItem::count())->toBe($before + 1);
    });

    it('shows no banner when nothing is overdue', function () {
        $this->actingAs($this->bursar)->get('http://greenhill.localhost/dashboard')->assertDontSee('Orange Tree invoice');
    });
});

describe('what a locked school keeps', function () {
    beforeEach(fn () => $this->travelTo('2026-11-20 09:00:00'));

    it('lets parents use the app, including marking notices read', function () {
        $guardian = guardianWithChild();
        $token = activateParent(); // writes OTPs, a user, a token: all through the API

        $this->withToken($token)->getJson(api('children'))->assertOk();
        $this->withToken($token)->postJson(api('notifications/read-all'))->assertOk();
        expect(Guardian::whereKey($guardian->id)->value('user_id'))->not->toBeNull();
    });

    it('runs queued jobs, whatever the last request was', function () {
        $announcement = Announcement::factory()->create(['school_id' => $this->school->id]);
        $guardian = guardianWithChild();

        $this->actingAs($this->bursar)->get('http://greenhill.localhost/dashboard')->assertSee('read-only');
        // The queue worker runs in its own process and clears per-request state before each job
        // (Laravel's worker calls forgetScopedInstances()); the test process does that by hand.
        app()->forgetScopedInstances();
        app(CurrentSchool::class); // re-resolved fresh, as in the worker
        dispatch_sync(new SendAnnouncementNotices($this->school->id, $announcement->id, [$guardian->id]));

        expect($guardian->notifications()->count())->toBe(1);
    });

    it('leaves the console alone, and only super admins keep a school open (logged)', function () {
        app(CurrentSchool::class)->clear();
        $support = User::factory()->platform(PlatformRoleKey::Support)->create();

        Livewire::actingAs($support)->test(Billing::class, ['school' => $this->school])
            ->assertSee('Read-only since 15 Nov 2026')
            ->set('keepOpenUntil', '2026-12-10')->set('keepOpenReason', 'Paying in two parts')
            ->call('keepOpen')->assertForbidden();

        Livewire::actingAs($this->superAdmin)->test(Billing::class, ['school' => $this->school])
            ->set('keepOpenUntil', '2026-12-10')->set('keepOpenReason', 'Paying in two parts')
            ->call('keepOpen')
            ->assertHasNoErrors()
            ->assertSee('Kept open until 10 Dec 2026');

        expect(SaasLock::for($this->school->fresh())->isLocked())->toBeFalse()
            ->and(Activity::where('log_name', 'schools')->where('subject_id', $this->school->id)->where('event', 'updated')->latest('id')->first()?->attribute_changes['attributes'] ?? [])
            ->toHaveKey('saas_lock_override_reason', 'Paying in two parts')
            ->and($this->school->fresh()->saas_lock_override_until?->toDateString())->toBe('2026-12-10');
    });

    it('refuses a keep-open date in the past, too far ahead, or without a reason', function (array $input, string $field) {
        app(CurrentSchool::class)->clear();

        Livewire::actingAs($this->superAdmin)->test(Billing::class, ['school' => $this->school])
            ->set('keepOpenUntil', $input[0])->set('keepOpenReason', $input[1])
            ->call('keepOpen')->assertHasErrors($field);
    })->with([
        'past' => [['2026-11-01', 'x'], 'until'],
        'too far' => [['2027-06-01', 'x'], 'until'],
        'no reason' => [['2026-12-10', ''], 'keep_reason'],
    ]);
});
