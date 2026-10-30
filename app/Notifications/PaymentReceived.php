<?php

namespace App\Notifications;

use App\Enums\SmsEvent;
use App\Models\Payment;
use App\Models\School;
use App\Notifications\Channels\HasSmsMessage;
use App\Notifications\Channels\SmsChannel;
use App\Support\Money\Money;
use Illuminate\Notifications\Notification;

/**
 * Tells a guardian the school received a payment for their child (sent by RecordPayment).
 */
class PaymentReceived extends Notification implements HasSmsMessage
{
    public function __construct(
        public readonly Payment $payment,
        public readonly Money $balance,
        public readonly School $school,
    ) {}

    /**
     * In-app always; SMS when the school turned payment SMS on (SmsChannel decides).
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', SmsChannel::class];
    }

    public function smsEvent(): SmsEvent
    {
        return SmsEvent::Payment;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'student_id' => $this->payment->student_id,
            'payment_id' => $this->payment->id,
            'receipt_number' => $this->payment->receipt_number,
            'amount' => $this->payment->amount->pesewas,
            'balance' => $this->balance->pesewas,
            'message' => $this->message(),
        ];
    }

    /**
     * "₵" is not in the GSM alphabet: one would make the whole SMS Unicode (70 characters a unit
     * instead of 160), so the SMS says "GHS".
     */
    public function toSms(object $notifiable): string
    {
        return str_replace('GH₵', 'GHS', $this->message());
    }

    private function message(): string
    {
        $balance = $this->balance->pesewas <= 0
            ? ($this->balance->isZero() ? 'Fees are fully paid.' : 'In credit: '.Money::pesewas(-$this->balance->pesewas)->format().'.')
            : 'Balance: '.$this->balance->format().'.';

        return "{$this->school->name} received {$this->payment->amount->format()} for {$this->payment->student->first_name} "
            ."(receipt {$this->payment->receipt_number}). {$balance}";
    }
}
