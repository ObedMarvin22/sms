<?php

use App\Actions\Billing\IssueBill;
use App\Actions\Fees\SaveFeeStructure;
use App\Actions\Guardians\AddGuardian;
use App\Actions\Payments\RecordPayment;
use App\Actions\Paystack\CreateSchoolSubaccount;
use App\Actions\Paystack\InitialisePaystackPayment;
use App\Actions\Paystack\PaystackCharge;
use App\Actions\Paystack\ReconcilePaystack;
use App\Actions\Paystack\SetPaystackChargePayer;
use App\Enums\PaymentMethod;
use App\Enums\PaystackTransactionStatus;
use App\Enums\PlatformRoleKey;
use App\Enums\Residence;
use App\Enums\RoleKey;
use App\Jobs\ProcessPaystackEvent;
use App\Livewire\Fees\Payments;
use App\Livewire\Students\Fees;
use App\Models\FeeItem;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\PaystackEvent;
use App\Models\PaystackTransaction;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\Paystack\PaystackClient;
use App\Services\Paystack\PaystackException;
use App\Support\Money\Money;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once __DIR__.'/../Fees/helpers.php';

const PAYSTACK_SECRET = 'sk_test_orange_tree_example';

beforeEach(function () {
    config(['services.paystack.secret_key' => PAYSTACK_SECRET, 'services.paystack.base_url' => 'https://api.paystack.co']);
    Http::preventStrayRequests(); // nothing ever reaches Paystack from a test

    setUpFeeSchool();
    $this->school->forceFill(['paystack_subaccount_code' => 'ACCT_greenhill'])->save();
    app(SaveFeeStructure::class)->handle($this->term1, $this->p1, Residence::Day, [FeeItem::where('name', 'Tuition')->sole()->id => '1000']);
    $this->ama = feeStudent('Ama');
    app(IssueBill::class)->handle($this->ama, $this->term1); // owes GH₵1,000.00
    $this->mother = app(AddGuardian::class)->handle($this->ama, ['name' => 'Akosua Mensah', 'phone' => '0244123456', 'relationship' => 'mother']);
});

/**
 * Paystack's answers: initialize always works; verify says what the test wants.
 */
function fakePaystack(string $status = 'success', ?int $amount = null, string $currency = 'GHS'): void
{
    Http::fake([
        'api.paystack.co/transaction/initialize' => fn (Request $r) => Http::response(['status' => true, 'message' => 'ok', 'data' => [
            'authorization_url' => 'https://checkout.paystack.com/abc123', 'access_code' => 'abc123', 'reference' => $r['reference'],
        ]]),
        'api.paystack.co/transaction/verify/*' => fn (Request $r) => Http::response(['status' => true, 'message' => 'ok', 'data' => [
            'status' => $status,
            'reference' => basename(parse_url($r->url(), PHP_URL_PATH) ?: ''),
            'amount' => $amount ?? PaystackTransaction::withoutGlobalScopes()->where('reference', basename(parse_url($r->url(), PHP_URL_PATH) ?: ''))->value('total'),
            'currency' => $currency,
            'paid_at' => '2026-10-28T09:30:00.000Z',
        ]]),
    ]);
}

function startPayment(string $amount = '400'): PaystackTransaction
{
    return app(InitialisePaystackPayment::class)->handle(test()->ama, $amount, 'http://greenhill.localhost/pay/return')['transaction'];
}

/**
 * @return array{string, string} raw body and its signature
 */
function webhook(PaystackTransaction $transaction, string $event = 'charge.success', int $id = 1001, array $metadata = []): array
{
    $body = json_encode(['event' => $event, 'data' => ['id' => $id, 'reference' => $transaction->reference, 'status' => 'success', 'metadata' => $metadata]], JSON_THROW_ON_ERROR);

    return [$body, hash_hmac('sha512', $body, PAYSTACK_SECRET)];
}

function postWebhook(string $body, string $signature): TestResponse
{
    return test()->call('POST', 'http://localhost/webhooks/paystack', [], [], [], [
        'CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature,
    ], $body);
}

describe('the client', function () {
    it('turns Paystack refusals and outages into readable errors', function () {
        Http::fake(['api.paystack.co/transaction/verify/*' => Http::response(['status' => false, 'message' => 'Invalid key'], 401)]);
        expect(fn () => app(PaystackClient::class)->verify('X'))->toThrow(PaystackException::class, 'Invalid key');

        Http::fake(['api.paystack.co/*' => fn () => throw new ConnectionException('timeout')]);
        expect(fn () => app(PaystackClient::class)->verify('X'))->toThrow(PaystackException::class, 'Could not reach Paystack');

        config(['services.paystack.secret_key' => '']);
        expect(fn () => app(PaystackClient::class)->verify('X'))->toThrow(PaystackException::class, 'not set up');
    });

    it('checks webhook signatures', function () {
        $client = app(PaystackClient::class);

        expect($client->signatureIsValid('{"a":1}', hash_hmac('sha512', '{"a":1}', PAYSTACK_SECRET)))->toBeTrue()
            ->and($client->signatureIsValid('{"a":2}', hash_hmac('sha512', '{"a":1}', PAYSTACK_SECRET)))->toBeFalse()
            ->and($client->signatureIsValid('{"a":1}', null))->toBeFalse();
    });
});

describe('connecting a school', function () {
    beforeEach(function () {
        $this->hillview = School::factory()->active()->create(['name' => 'Hillview School', 'subdomain' => 'hillview', 'email' => 'office@hillview.test']);
        $this->superAdmin = User::factory()->create(['school_id' => null, 'platform_role' => PlatformRoleKey::SuperAdmin]);
        Http::fake([
            'api.paystack.co/bank*' => fn (Request $r) => str_contains($r->url(), '/bank/resolve')
                ? Http::response(['status' => true, 'data' => ['account_number' => '1234567890', 'account_name' => 'HILLVIEW SCHOOL LTD']])
                : Http::response(['status' => true, 'data' => [['name' => 'GCB Bank', 'code' => 'GCB', 'type' => 'ghipss'], ['name' => 'MTN Mobile Money', 'code' => 'MTN', 'type' => 'mobile_money']]]),
            'api.paystack.co/subaccount' => Http::response(['status' => true, 'data' => ['subaccount_code' => 'ACCT_hillview']]),
        ]);
    });

    it('creates a subaccount with no Orange Tree share and shows where money settles', function () {
        $school = app(CreateSchoolSubaccount::class)->handle($this->superAdmin, $this->hillview, 'GCB', '12-3456-7890');

        expect($school->paystack_subaccount_code)->toBe('ACCT_hillview')
            ->and($school->paystack_settlement)->toBe('GCB Bank · ****7890 · HILLVIEW SCHOOL LTD');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/subaccount') && $r['percentage_charge'] === 0
            && $r['account_number'] === '1234567890' && $r['settlement_bank'] === 'GCB');
    });

    it('refuses an unknown bank or a school already connected', function () {
        expect(fn () => app(CreateSchoolSubaccount::class)->handle($this->superAdmin, $this->hillview, 'NOPE', '1234567890'))->toThrow(ValidationException::class)
            ->and(fn () => app(CreateSchoolSubaccount::class)->handle($this->superAdmin, $this->school, 'GCB', '1234567890'))->toThrow(ValidationException::class, 'already connected');
    });

    it('is a super admin screen in the console', function () {
        $this->actingAs($this->superAdmin, 'platform')->get("http://admin.localhost/schools/{$this->hillview->id}/payments")
            ->assertOk()->assertSee('GCB Bank');
    });
});

describe('starting a payment', function () {
    it('needs the school to be connected first', function () {
        $this->school->forceFill(['paystack_subaccount_code' => null])->save();

        expect(fn () => startPayment())->toThrow(ValidationException::class, 'cannot take online payments yet');
    });

    it('sends the school\'s subaccount and the amount in pesewas, with an email made from the parent\'s phone', function () {
        fakePaystack();
        $transaction = startPayment('400');

        expect($transaction->status)->toBe(PaystackTransactionStatus::Pending)
            ->and($transaction->total->pesewas)->toBe(40_000)
            ->and($transaction->email)->toBe('233244123456@greenhill.localhost')
            ->and($transaction->reference)->toStartWith("OT{$this->school->id}-");
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/transaction/initialize')
            && $r['subaccount'] === 'ACCT_greenhill' && $r['amount'] === 40_000 && $r['currency'] === 'GHS' && $r['bearer'] === 'subaccount');
    });

    it('adds Paystack\'s charge when the school passes it on, so the school still receives the full amount', function () {
        fakePaystack();
        app(SetPaystackChargePayer::class)->handle($this->school, 'parent');

        $transaction = startPayment('1000');

        // 1,000.00 / (1 - 1.95%) = 1,019.887... -> GH₵1,019.89; the parent pays GH₵19.89 on top.
        expect($transaction->total->pesewas)->toBe(101_989)
            ->and($transaction->charge->pesewas)->toBe(1_989)
            ->and($transaction->amount->pesewas)->toBe(100_000)
            ->and(PaystackCharge::grossUp(Money::pesewas(101_989), 195)->pesewas)->toBeGreaterThan(101_989);
    });
});

describe('the webhook', function () {
    it('refuses a wrong signature', function () {
        fakePaystack();
        [$body] = webhook(startPayment());

        postWebhook($body, 'not-the-signature')->assertUnauthorized();

        expect(PaystackEvent::count())->toBe(0)->and(Payment::count())->toBe(0);
    });

    it('verifies with Paystack, then records the payment once with a receipt', function () {
        fakePaystack();
        $transaction = startPayment('400');
        [$body, $signature] = webhook($transaction);

        postWebhook($body, $signature)->assertOk();
        postWebhook($body, $signature)->assertOk(); // Paystack sends it again

        $payment = Payment::sole();
        expect($transaction->fresh()->status)->toBe(PaystackTransactionStatus::Success)
            ->and($payment->method)->toBe(PaymentMethod::Paystack)
            ->and($payment->reference)->toBe(mb_strtoupper($transaction->reference))
            ->and($payment->recorded_by)->toBeNull()
            ->and(LedgerEntry::balanceFor($this->ama->id)->format())->toBe('GH₵600.00')
            ->and(PaystackEvent::sole()->processed_at)->not->toBeNull();
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/transaction/verify/'));
    });

    it('stores and queues a repeated event only once', function () {
        fakePaystack();
        $transaction = startPayment();
        Queue::fake();
        [$body, $signature] = webhook($transaction);

        postWebhook($body, $signature)->assertOk();
        postWebhook($body, $signature)->assertOk();

        Queue::assertPushed(ProcessPaystackEvent::class, 1);
        expect(PaystackEvent::count())->toBe(1);
    });

    it('records nothing when Paystack says the payment failed', function () {
        fakePaystack('failed');
        $transaction = startPayment();
        postWebhook(...webhook($transaction))->assertOk();

        expect(Payment::count())->toBe(0)->and($transaction->fresh()->status)->toBe(PaystackTransactionStatus::Failed);
    });

    it('flags a wrong amount or currency instead of recording it', function (?int $amount, string $currency) {
        fakePaystack('success', $amount, $currency);
        $transaction = startPayment('400');
        postWebhook(...webhook($transaction))->assertOk();

        expect(Payment::count())->toBe(0)
            ->and($transaction->fresh()->status)->toBe(PaystackTransactionStatus::Attention)
            ->and($transaction->fresh()->attention)->toContain('Nothing was recorded');
    })->with([
        'too little' => [100, 'GHS'],
        'other currency' => [null, 'NGN'],
    ]);

    it('works out the school from our reference, never from what the event claims', function () {
        fakePaystack();
        $hillview = School::factory()->active()->create(['subdomain' => 'hillview', 'paystack_subaccount_code' => 'ACCT_hillview']);
        $theirs = app(CurrentSchool::class)->runFor($hillview, function () use ($hillview) {
            $student = Student::factory()->for($hillview)->create();

            return PaystackTransaction::factory()->create(['school_id' => $hillview->id, 'student_id' => $student->id]);
        });

        postWebhook(...webhook($theirs, metadata: ['school_id' => $this->school->id]))->assertOk();

        $payment = Payment::withoutGlobalScopes()->sole(); // test-only check across schools
        expect($payment->school_id)->toBe($hillview->id)
            ->and(Payment::count())->toBe(0); // nothing in Greenhill
    });

    it('ignores events it does not act on and unknown references', function () {
        fakePaystack();
        $transaction = startPayment();
        postWebhook(...webhook($transaction, 'transfer.success', 2001))->assertOk();
        $transaction->reference = 'NOT-OURS';
        postWebhook(...webhook($transaction, id: 2002))->assertOk();

        expect(Payment::count())->toBe(0)
            ->and(PaystackEvent::where('event_key', 'charge.success:2002')->sole()->error)->toBe('No transaction with this reference.');
    });
});

describe('reconciliation and the rest', function () {
    it('records a payment the webhook missed and flags one Paystack no longer reports as paid', function () {
        // One fake whose answer changes during the test (the first matching fake always wins).
        $reversed = null;
        Http::fake([
            'api.paystack.co/transaction/initialize' => fn (Request $r) => Http::response(['status' => true, 'data' => [
                'authorization_url' => 'https://checkout.paystack.com/abc123', 'reference' => $r['reference'],
            ]]),
            'api.paystack.co/transaction/verify/*' => function (Request $r) use (&$reversed) {
                $reference = basename((string) parse_url($r->url(), PHP_URL_PATH));

                return Http::response(['status' => true, 'data' => [
                    'status' => $reference === $reversed ? 'reversed' : 'success',
                    'amount' => PaystackTransaction::withoutGlobalScopes()->where('reference', $reference)->value('total'),
                    'currency' => 'GHS', 'paid_at' => '2026-10-28T10:00:00.000Z',
                ]]);
            },
        ]);
        $missed = startPayment('300');
        $recorded = startPayment('200');
        postWebhook(...webhook($recorded))->assertOk();
        $reversed = $recorded->reference; // Paystack later reports the recorded one as reversed

        expect(app(ReconcilePaystack::class)->handle())->toMatchArray(['checked' => 2, 'recorded' => 1, 'flagged' => 1])
            ->and($missed->fresh()->payment_id)->not->toBeNull()
            ->and($recorded->fresh()->status)->toBe(PaystackTransactionStatus::Attention)
            ->and(Payment::count())->toBe(2);

        $this->artisan('paystack:reconcile')->assertSuccessful();
    });

    it('runs the reconciliation nightly', function () {
        $event = collect(app(Schedule::class)->events())->filter(fn (Event $e) => str_contains((string) $e->command, 'paystack:reconcile'))->sole();

        expect($event->expression)->toBe('0 2 * * *');
    });

    it('never lets staff record a Paystack payment by hand', function () {
        expect(fn () => app(RecordPayment::class)->handle(staffWith(RoleKey::Bursar), $this->ama, ['amount' => '100', 'method' => 'paystack', 'reference' => 'X', 'paid_on' => now()->toDateString()]))
            ->toThrow(ValidationException::class, 'recorded automatically');
    });

    it('shows the parent the outcome on the return page, checking Paystack straight away', function () {
        fakePaystack();
        $transaction = startPayment('400');

        $this->get('http://greenhill.localhost/pay/return?reference='.$transaction->reference)
            ->assertOk()->assertSee('Payment received')->assertSee('GH₵400.00');
        $this->get('http://greenhill.localhost/pay/return?reference=NOT-OURS')->assertNotFound();
    });

    it('lists flagged payments for the bursar and makes payment links from the student page', function () {
        fakePaystack('success', 1);
        $transaction = startPayment('400');
        postWebhook(...webhook($transaction))->assertOk();
        $bursar = staffWith(RoleKey::Bursar);
        $this->onSchool($this->school);

        Livewire::actingAs($bursar)->test(Payments::class)->assertSee('Online payments that need attention')->assertSee($transaction->reference);
        Livewire::actingAs($bursar)->test(Fees::class, ['studentId' => $this->ama->id])
            ->set('linkAmount', '250')
            ->call('createPaymentLink')
            ->assertHasNoErrors()
            ->assertSet('paymentLink', 'https://checkout.paystack.com/abc123');
    });
});
