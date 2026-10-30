<?php

use App\Actions\Billing\IssueBill;
use App\Actions\Fees\SaveFeeStructure;
use App\Actions\Guardians\AddGuardian;
use App\Actions\Payments\RecordPayment;
use App\Actions\Sms\DeliverSms;
use App\Actions\Sms\QueueSms;
use App\Actions\Sms\SetSenderId;
use App\Actions\Sms\SetSmsSettings;
use App\Actions\Sms\TopUpCredits;
use App\Enums\CreditEntryType;
use App\Enums\PlatformRoleKey;
use App\Enums\Residence;
use App\Enums\RoleKey;
use App\Enums\SmsEvent;
use App\Enums\SmsStatus;
use App\Jobs\SendSms;
use App\Livewire\Console\Schools\Sms;
use App\Livewire\Fees\Messages;
use App\Livewire\School\Profile;
use App\Models\FeeItem;
use App\Models\MessageCreditEntry;
use App\Models\SmsMessage;
use App\Models\User;
use App\Notifications\LowSmsCredit;
use App\Services\Sms\ArkeselSmsGateway;
use App\Services\Sms\LogSmsGateway;
use App\Services\Sms\SmsException;
use App\Services\Sms\SmsGateway;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once __DIR__.'/../Fees/helpers.php';

beforeEach(function () {
    Http::preventStrayRequests();
    setUpFeeSchool();
    $this->superAdmin = User::factory()->create(['school_id' => null, 'platform_role' => PlatformRoleKey::SuperAdmin]);
});

function credit(int $units): void
{
    app(TopUpCredits::class)->handle(test()->superAdmin, test()->school, (string) $units, 'Test top-up');
}

function sms(string $message = 'Hello from school', string $to = '+233244000001'): SmsMessage
{
    return app(QueueSms::class)->handle($to, $message, SmsEvent::Absence);
}

describe('credit', function () {
    it('takes units when a message is queued and sends it through the gateway', function () {
        credit(10);

        $message = sms(str_repeat('a', 200)); // 2 units

        expect($message->fresh()->status)->toBe(SmsStatus::Sent)
            ->and($message->fresh()->provider_id)->toStartWith('log-')
            ->and($message->segments)->toBe(2)
            ->and(MessageCreditEntry::balance())->toBe(8);
    });

    it('never sends on credit: without enough units nothing is taken or sent', function () {
        Queue::fake();
        credit(1);

        $message = sms(str_repeat('a', 200));

        expect($message->status)->toBe(SmsStatus::NotSent)
            ->and($message->error)->toBe('Not enough SMS credit.')
            ->and(MessageCreditEntry::balance())->toBe(1);
        Queue::assertNothingPushed();
    });

    it('lets the last unit go to one message only', function () {
        Queue::fake();
        credit(1);

        expect(sms()->status)->toBe(SmsStatus::Queued)
            ->and(sms()->status)->toBe(SmsStatus::NotSent)
            ->and(MessageCreditEntry::balance())->toBe(0);
    });

    it('gives the units back when a message finally fails', function () {
        Queue::fake();
        credit(5);
        $message = sms();

        (new SendSms($this->school->id, $message->id))->failed(new SmsException('Network down'));
        (new SendSms($this->school->id, $message->id))->failed(new SmsException('Again')); // only once

        expect($message->fresh()->status)->toBe(SmsStatus::Failed)
            ->and(MessageCreditEntry::balance())->toBe(5)
            ->and(MessageCreditEntry::where('type', CreditEntryType::Refund->value)->count())->toBe(1);
    });

    it('retries when the provider refuses, without sending twice once sent', function () {
        Queue::fake();
        credit(5);
        $message = sms();
        app()->instance(SmsGateway::class, Mockery::mock(SmsGateway::class)
            ->shouldReceive('send')->once()->andThrow(new SmsException('Busy'))->getMock());

        expect(fn () => (new SendSms($this->school->id, $message->id))->handle(app(DeliverSms::class)))
            ->toThrow(SmsException::class);
        expect($message->fresh()->status)->toBe(SmsStatus::Queued);
    });

    it('warns finance staff once when credit falls below the threshold, and again after a top-up', function () {
        Notification::fake();
        $bursar = staffWith(RoleKey::Bursar);
        $teacher = staffWith(RoleKey::ClassTeacher);
        app(SetSmsSettings::class)->handle($this->school, [], '3');
        credit(4);

        sms(); // 3 left: not below 3
        Notification::assertNothingSent();
        sms(); // 2 left: warn
        sms(); // 1 left: already warned
        Notification::assertSentToTimes($bursar, LowSmsCredit::class, 1);
        Notification::assertNotSentTo($teacher, LowSmsCredit::class);

        credit(10); // back above: re-armed
        foreach (range(1, 9) as $i) {
            sms();
        }
        Notification::assertSentToTimes($bursar, LowSmsCredit::class, 2);
    });

    it('keeps the credit ledger append-only', function () {
        credit(5);
        $entry = MessageCreditEntry::sole();

        expect(fn () => $entry->forceFill(['units' => 500])->save())->toThrow(LogicException::class)
            ->and(fn () => $entry->delete())->toThrow(LogicException::class);
    });
});

describe('notices', function () {
    beforeEach(function () {
        app(SaveFeeStructure::class)->handle($this->term1, $this->p1, Residence::Day, [FeeItem::where('name', 'Tuition')->sole()->id => '1000']);
        $this->ama = feeStudent('Ama');
        app(IssueBill::class)->handle($this->ama, $this->term1);
        app(AddGuardian::class)->handle($this->ama, ['name' => 'Akosua Mensah', 'phone' => '0244000001', 'relationship' => 'mother']);
        app(AddGuardian::class)->handle($this->ama, ['name' => 'Yaw Mensah', 'phone' => '0244000002', 'relationship' => 'father', 'access_level' => 'none']);
        credit(20);
    });

    function payCash(): void
    {
        app(RecordPayment::class)->handle(staffWith(RoleKey::Bursar), test()->ama, ['amount' => '500', 'method' => 'cash', 'paid_on' => now()->toDateString()]);
    }

    it('sends no SMS for a notice the school has not turned on', function () {
        payCash();

        expect(SmsMessage::count())->toBe(0)->and(MessageCreditEntry::balance())->toBe(20);
    });

    it('sends the payment SMS in GSM text to each guardian with app access, under the school\'s sender ID', function () {
        app(SetSmsSettings::class)->handle($this->school, ['payment'], '10');
        app(SetSenderId::class)->handle($this->superAdmin, $this->school, 'GREENHILL');

        payCash();

        $sms = SmsMessage::sole();
        expect($sms->to)->toBe('+233244000001')
            ->and($sms->sender)->toBe('GREENHILL')
            ->and($sms->message)->toContain('GHS500.00 for Ama')->not->toContain('₵')
            ->and($sms->segments)->toBe(1)
            ->and($sms->status)->toBe(SmsStatus::Sent);
    });
});

describe('Arkesel', function () {
    it('sends to Arkesel v2 with the API key and the number without the plus', function () {
        Http::fake(['sms.arkesel.com/api/v2/sms/send' => Http::response(['status' => 'success', 'data' => [['recipient' => '233244000001', 'id' => 'ark-123']]])]);

        $id = (new ArkeselSmsGateway('https://sms.arkesel.com', 'test-key'))->send('+233244000001', 'Hello', 'GREENHILL');

        expect($id)->toBe('ark-123');
        Http::assertSent(fn (Request $r) => $r->hasHeader('api-key', 'test-key') && $r['recipients'] === ['233244000001']
            && $r['sender'] === 'GREENHILL' && $r['message'] === 'Hello');
    });

    it('reports refusals, outages and a missing key', function () {
        Http::fake(['sms.arkesel.com/*' => Http::response(['status' => 'error', 'message' => 'Insufficient balance'], 422)]);
        expect(fn () => (new ArkeselSmsGateway('https://sms.arkesel.com', 'k'))->send('+233244000001', 'Hi', 'GREENHILL'))
            ->toThrow(SmsException::class, 'Insufficient balance');

        expect(fn () => (new ArkeselSmsGateway('https://sms.arkesel.com', null))->send('+233244000001', 'Hi', 'GREENHILL'))
            ->toThrow(SmsException::class, 'no Arkesel key');
    });

    it('is used only when SMS_DRIVER is arkesel', function () {
        expect(app(SmsGateway::class))->toBeInstanceOf(LogSmsGateway::class);

        config(['sms.driver' => 'arkesel']);
        expect(app(SmsGateway::class))->toBeInstanceOf(ArkeselSmsGateway::class);
    });
});

describe('screens and settings', function () {
    it('validates top-ups and sender IDs', function () {
        expect(fn () => app(TopUpCredits::class)->handle($this->superAdmin, $this->school, '0', 'x'))->toThrow(ValidationException::class)
            ->and(fn () => app(TopUpCredits::class)->handle($this->superAdmin, $this->school, '100', ''))->toThrow(ValidationException::class)
            ->and(fn () => app(SetSenderId::class)->handle($this->superAdmin, $this->school, 'GREENHILL ACADEMY'))->toThrow(ValidationException::class)
            ->and(fn () => app(SetSenderId::class)->handle($this->superAdmin, $this->school, '12345'))->toThrow(ValidationException::class);
    });

    it('adds credit from the console', function () {
        $this->actingAs($this->superAdmin, 'platform');

        Livewire::test(Sms::class, ['school' => $this->school])
            ->set('units', '500')->set('note', 'MoMo ref 98765')
            ->call('topUp')
            ->assertHasNoErrors()
            ->assertSee('500');

        app(CurrentSchool::class)->set($this->school);
        expect(MessageCreditEntry::balance())->toBe(500);
    });

    it('chooses SMS notices on School profile and lists messages for finance staff', function () {
        $admin = staffWith(RoleKey::Administrator);
        $bursar = staffWith(RoleKey::Bursar);
        $this->onSchool($this->school);

        Livewire::actingAs($admin)->test(Profile::class)
            ->set('sms_events', ['absence'])
            ->set('sms_low_credit_threshold', '50')
            ->call('saveSmsSettings')
            ->assertHasNoErrors();
        expect($this->school->fresh()->settings['sms_events'])->toBe(['absence']);

        credit(5);
        sms('Ama was marked absent today.');
        Livewire::actingAs($bursar)->test(Messages::class)->assertSee('Ama was marked absent today.')->assertSee('4');
    });
});
