<?php

require_once __DIR__.'/helpers.php';

use App\Actions\Billing\IssueBill;
use App\Actions\Fees\SaveFeeStructure;
use App\Actions\Payments\RecordPayment;
use App\Enums\AttendanceStatus;
use App\Enums\GuardianAccess;
use App\Enums\Residence;
use App\Enums\RoleKey;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Guardian;
use App\Models\Notification;
use App\Models\PaystackTransaction;
use App\Models\Student;
use App\Models\User;
use App\Notifications\PaymentReceived;
use App\Notifications\StudentAbsent;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/*
| SPEC section 5 / ROADMAP Sprint 4 step 6: a parent sees only their own children, and only what
| their access level allows (full: everything; limited: attendance and notices; none: nothing).
*/

beforeEach(function () {
    Http::preventStrayRequests();
    setUpParentSchool();
    app(SaveFeeStructure::class)->handle($this->term1, $this->p1, Residence::Day, [$this->tuition->id => '1000']);

    $this->ama = feeStudent('Ama');     // full access
    $this->kofi = feeStudent('Kofi');   // limited
    $this->esi = feeStudent('Esi');     // contact only
    $this->yaw = feeStudent('Yaw');     // another family's child

    $this->mother = guardianWithChild(child: $this->ama);
    guardianWithChild(access: GuardianAccess::Limited, child: $this->kofi);
    guardianWithChild(access: GuardianAccess::None, child: $this->esi);
    $this->other = guardianWithChild('+233244000222', child: $this->yaw);

    foreach ([$this->ama, $this->kofi, $this->esi, $this->yaw] as $child) {
        app(IssueBill::class)->handle($child, $this->term1); // each owes GH₵1,000.00
    }

    $this->withToken(activateParent());
});

function mark(Student $student, string $date, AttendanceStatus $status, ?string $note = null): AttendanceRecord
{
    $enrolment = $student->currentEnrolment()->sole();
    $session = AttendanceSession::firstOrCreate(
        ['stream_id' => $enrolment->stream_id, 'date' => $date],
        ['academic_year_id' => $enrolment->academic_year_id, 'term_id' => test()->term1->id],
    );

    return AttendanceRecord::create([
        'attendance_session_id' => $session->id, 'enrolment_id' => $enrolment->id,
        'student_id' => $student->id, 'status' => $status, 'note' => $note,
    ]);
}

function notice(Guardian $guardian, string $type, Student $student, string $message = 'Hello', ?string $at = null): Notification
{
    $notice = Notification::create([
        'id' => (string) Str::uuid(),
        'type' => $type,
        'notifiable_type' => $guardian->getMorphClass(),
        'notifiable_id' => $guardian->id,
        'data' => ['student_id' => $student->id, 'message' => $message],
    ]);
    if ($at !== null) {
        $notice->forceFill(['created_at' => $at])->save();
    }

    return $notice;
}

describe('children', function () {
    it("lists the parent's own children with today's mark, and the balance only with full access", function () {
        mark($this->ama, '2026-10-28', AttendanceStatus::Late);

        $children = $this->getJson(api('children'))->assertOk()->json('data');

        expect(array_column($children, 'first_name'))->toBe(['Ama', 'Kofi'])
            ->and($children[0])->toMatchArray([
                'id' => $this->ama->id, 'class' => 'Primary 1A', 'access' => 'full', 'sees_fees' => true, 'today' => 'late',
                'balance' => ['pesewas' => 100000, 'display' => 'GH₵1,000.00'],
            ])
            ->and($children[1])->toMatchArray(['access' => 'limited', 'sees_fees' => false, 'today' => null, 'balance' => null]);
    });

    it("never shows another family's child or a contact-only child", function (string $who) {
        $this->getJson(api('children/'.$this->{$who}->id))->assertNotFound();
    })->with(['another family' => 'yaw', 'contact only' => 'esi']);

    it('shows one child', function () {
        $this->getJson(api('children/'.$this->kofi->id))->assertOk()->assertJsonPath('data.first_name', 'Kofi');
    });

    it('drops a child whose record was deleted', function () {
        $this->ama->delete();

        expect(array_column($this->getJson(api('children'))->json('data'), 'first_name'))->toBe(['Kofi']);
        $this->getJson(api('children/'.$this->ama->id))->assertNotFound();
    });

    it('takes the same number of queries for one child or many', function () {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->getJson(api('children'))->assertOk();

            return count(DB::getQueryLog());
        };

        $count(); // first request warms the permission cache
        $two = $count();
        guardianWithChild(child: feeStudent('Abena'));
        guardianWithChild(child: feeStudent('Kwame'));

        expect($count())->toBe($two);
    });
});

describe('attendance', function () {
    it("gives this month's marks and counts, without teachers' notes", function () {
        mark($this->ama, '2026-10-26', AttendanceStatus::Present);
        mark($this->ama, '2026-10-27', AttendanceStatus::Absent, 'Mother called: malaria');
        mark($this->ama, '2026-09-30', AttendanceStatus::Present);

        $response = $this->getJson(api("children/{$this->ama->id}/attendance"))->assertOk();

        expect($response->json('data.month'))->toBe('2026-10')
            ->and($response->json('data.days'))->toBe([
                ['date' => '2026-10-26', 'status' => 'present'],
                ['date' => '2026-10-27', 'status' => 'absent'],
            ])
            ->and($response->json('data.counts'))->toMatchArray(['present' => 1, 'absent' => 1, 'late' => 0])
            ->and($response->getContent())->not->toContain('malaria');
    });

    it('gives another month', function () {
        mark($this->ama, '2026-09-30', AttendanceStatus::Excused);

        $this->getJson(api("children/{$this->ama->id}/attendance?month=2026-09"))
            ->assertOk()->assertJsonPath('data.days.0.status', 'excused');
    });

    it('refuses a badly written month', function () {
        $this->getJson(api("children/{$this->ama->id}/attendance?month=Oct"))->assertJsonValidationErrors('month');
    });

    it('is open to limited access', function () {
        $this->getJson(api("children/{$this->kofi->id}/attendance"))->assertOk();
    });

    it("is not found for another family's child", function () {
        $this->getJson(api("children/{$this->yaw->id}/attendance"))->assertNotFound();
    });
});

describe('fees', function () {
    it('shows the bills with their lines and what to pay', function () {
        $bills = $this->getJson(api("children/{$this->ama->id}/bills"))->assertOk();

        expect($bills->json('data.0'))->toMatchArray(['class' => 'Primary 1', 'residence' => 'day'])
            ->and($bills->json('data.0.lines.0.description'))->toBe('Tuition')
            ->and($bills->json('data.0.payable.pesewas'))->toBe(100000)
            ->and($bills->json('balance.display'))->toBe('GH₵1,000.00');
    });

    it('shows the statement with the balance after each line', function () {
        $bursar = staffWith(RoleKey::Bursar);
        app(RecordPayment::class)->handle($bursar, $this->ama, ['amount' => '400', 'method' => 'cash', 'paid_on' => '2026-10-28']);

        $statement = $this->getJson(api("children/{$this->ama->id}/statement"))->assertOk();

        expect(array_column($statement->json('data'), 'type'))->toBe(['bill', 'payment'])
            ->and($statement->json('data.1.receipt_number'))->not->toBeNull()
            ->and($statement->json('data.1.balance.pesewas'))->toBe(60000)
            ->and($statement->json('balance.pesewas'))->toBe(60000);
    });

    it('is refused with limited access', function (string $path) {
        $this->getJson(api("children/{$this->kofi->id}/{$path}"))->assertForbidden();
    })->with(['bills', 'statement']);

    it("is not found for another family's child", function (string $path) {
        $this->getJson(api("children/{$this->yaw->id}/{$path}"))->assertNotFound();
    })->with(['bills', 'statement']);
});

describe('paying online', function () {
    beforeEach(function () {
        config(['services.paystack.secret_key' => 'sk_test_orange_tree_example', 'services.paystack.base_url' => 'https://api.paystack.co']);
        $this->school->forceFill(['paystack_subaccount_code' => 'ACCT_greenhill'])->save();
        Http::fake(['api.paystack.co/transaction/initialize' => fn (Request $r) => Http::response(['status' => true, 'message' => 'ok', 'data' => [
            'authorization_url' => 'https://checkout.paystack.com/abc123', 'access_code' => 'abc123', 'reference' => $r['reference'],
        ]])]);
    });

    it('starts a Paystack payment with the parent as payer', function () {
        $response = $this->postJson(api("children/{$this->ama->id}/payments"), ['amount' => '400'])->assertCreated();

        expect($response->json('data.authorization_url'))->toBe('https://checkout.paystack.com/abc123')
            ->and($response->json('data.amount.pesewas'))->toBe(40000);

        $transaction = PaystackTransaction::sole();
        expect($transaction->student_id)->toBe($this->ama->id)
            ->and($transaction->initiated_by)->toBe(User::where('phone', '+233244000111')->value('id'));

        Http::assertSent(fn (Request $r) => $r['subaccount'] === 'ACCT_greenhill'
            && $r['callback_url'] === 'http://greenhill.localhost/pay/return'
            && $r['amount'] === $transaction->total->pesewas);
    });

    it('needs a sensible amount', function () {
        $this->postJson(api("children/{$this->ama->id}/payments"), ['amount' => 'lots'])->assertJsonValidationErrors('amount');
        $this->postJson(api("children/{$this->ama->id}/payments"), [])->assertJsonValidationErrors('amount');
        Http::assertNothingSent();
    });

    it('explains when the school does not take online payments yet', function () {
        $this->school->forceFill(['paystack_subaccount_code' => null])->save();

        $this->postJson(api("children/{$this->ama->id}/payments"), ['amount' => '400'])
            ->assertJsonValidationErrors(['amount' => 'cannot take online payments yet']);
    });

    it('is refused with limited access, and not found for other children', function () {
        $this->postJson(api("children/{$this->kofi->id}/payments"), ['amount' => '400'])->assertForbidden();
        $this->postJson(api("children/{$this->yaw->id}/payments"), ['amount' => '400'])->assertNotFound();
        Http::assertNothingSent();
    });
});

describe('notifications', function () {
    it("lists the parent's own notices, newest first, with the unread count", function () {
        notice($this->mother, StudentAbsent::class, $this->ama, 'Ama was absent', '2026-10-27 09:00:00');
        notice($this->mother, StudentAbsent::class, $this->kofi, 'Kofi was absent', '2026-10-28 09:00:00');
        notice($this->other, StudentAbsent::class, $this->yaw, 'Yaw was absent');

        $response = $this->getJson(api('notifications'))->assertOk();

        expect(array_column($response->json('data'), 'message'))->toBe(['Kofi was absent', 'Ama was absent'])
            ->and($response->json('data.0'))->toMatchArray(['kind' => 'absence', 'read' => false, 'student_id' => $this->kofi->id])
            ->and($response->json('unread'))->toBe(2);
    });

    it('leaves out payment notices for a limited child and notices for a contact-only child', function () {
        notice($this->mother, PaymentReceived::class, $this->ama, 'Ama paid');
        notice($this->mother, PaymentReceived::class, $this->kofi, 'Kofi paid');
        notice($this->mother, StudentAbsent::class, $this->esi, 'Esi was absent');

        expect(array_column($this->getJson(api('notifications'))->json('data'), 'message'))->toBe(['Ama paid']);
    });

    it("does not show or mark another guardian's notice about the same child", function () {
        $father = guardianWithChild('+233244000333', child: $this->ama);
        $his = notice($father, StudentAbsent::class, $this->ama, 'For the father');

        expect($this->getJson(api('notifications'))->json('data'))->toBe([]);
        $this->postJson(api("notifications/{$his->id}/read"))->assertNotFound();
        $this->postJson(api('notifications/read-all'))->assertJsonPath('data.marked', 0);
    });

    it('marks one notice read', function () {
        $notice = notice($this->mother, StudentAbsent::class, $this->ama);

        $this->postJson(api("notifications/{$notice->id}/read"))->assertOk();

        expect($notice->fresh()->read_at)->not->toBeNull();
    });

    it("cannot mark another parent's notice", function () {
        $notice = notice($this->other, StudentAbsent::class, $this->yaw);

        $this->postJson(api("notifications/{$notice->id}/read"))->assertNotFound();

        expect($notice->fresh()->read_at)->toBeNull();
    });

    it('marks all of them read, and only theirs', function () {
        notice($this->mother, StudentAbsent::class, $this->ama);
        notice($this->mother, StudentAbsent::class, $this->kofi);
        $theirs = notice($this->other, StudentAbsent::class, $this->yaw);

        $this->postJson(api('notifications/read-all'))->assertOk()->assertJsonPath('data.marked', 2);

        expect($this->getJson(api('notifications'))->json('unread'))->toBe(0)
            ->and($theirs->fresh()->read_at)->toBeNull();
    });

    it('sends payment notices to full-access guardians only', function () {
        $father = guardianWithChild('+233244000333', GuardianAccess::Limited, $this->ama);
        $bursar = staffWith(RoleKey::Bursar);

        app(RecordPayment::class)->handle($bursar, $this->ama, ['amount' => '100', 'method' => 'cash', 'paid_on' => '2026-10-28']);

        expect($this->mother->notifications()->where('type', PaymentReceived::class)->count())->toBe(1)
            ->and($father->notifications()->count())->toBe(0);
    });
});
