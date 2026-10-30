<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\School;
use App\Notifications\Channels\HasLogMessage;
use App\Notifications\Channels\LogChannel;
use App\Support\Money\Money;
use Illuminate\Notifications\Notification;

/**
 * Tells a guardian the school received a payment for their child (sent by RecordPayment).
 */
class PaymentReceived extends Notification implements HasLogMessage
{
    public function __construct(
        public readonly Payment $payment,
        public readonly Money $balance,
        public readonly School $school,
    ) {}

    /**
     * In-app always; the log stands in for SMS until step 5.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', LogChannel::class];
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

    public function toLog(object $notifiable): string
    {
        return $this->message();
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
