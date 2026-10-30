<?php

namespace App\Enums;

/**
 * Where an online payment stands. "attention" means a bursar must look (wrong amount, or
 * Paystack stopped reporting it as paid after we recorded it); it is never fixed silently.
 */
enum PaystackTransactionStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Attention = 'attention';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Waiting for payment',
            self::Success => 'Paid',
            self::Failed => 'Not paid',
            self::Attention => 'Needs attention',
        };
    }
}
