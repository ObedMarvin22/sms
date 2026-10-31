<?php

namespace App\Models;

use App\Enums\SmsEvent;
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
     * What staff screens show. A parent-app code is never shown, even while it is waiting to
     * go: anyone who read it could take over the parent's account.
     */
    public function displayMessage(): string
    {
        return $this->event === SmsEvent::Otp->value ? self::hideCodes($this->message) : $this->message;
    }

    public static function hideCodes(string $message): string
    {
        return (string) preg_replace('/\d{4,}/', '******', $message);
    }

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
