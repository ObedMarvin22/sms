<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A webhook event from Paystack, stored before processing. Platform model (no school_id):
 * the school is found later from our own transaction reference.
 *
 * @property int $id
 * @property string $event_key
 * @property string $event
 * @property string|null $reference
 * @property array<string, mixed> $payload
 * @property CarbonImmutable|null $processed_at
 * @property string|null $error
 */
#[Fillable(['event_key', 'event', 'reference', 'payload'])]
class PaystackEvent extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['payload' => 'array', 'processed_at' => 'immutable_datetime'];
    }
}
