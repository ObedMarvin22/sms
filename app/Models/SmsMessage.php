<?php

namespace App\Models;

use App\Enums\SmsStatus;
use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\SmsMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One SMS a school sent or tried to send.
 *
 * @property int $id
 * @property string $to
 * @property string $message
 * @property int $segments
 * @property string $sender
 * @property string $event
 * @property SmsStatus $status
 * @property string|null $provider_id
 * @property string|null $error
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable $created_at
 */
#[Fillable(['to', 'message', 'segments', 'sender', 'event'])]
class SmsMessage extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<SmsMessageFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'queued'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SmsStatus::class,
            'segments' => 'integer',
            'sent_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
