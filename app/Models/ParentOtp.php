<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\ParentOtpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A one-time code texted to a parent's phone (activation or a forgotten password).
 *
 * @property int $id
 * @property string $phone
 * @property string $code_hash
 * @property int $attempts
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $used_at
 * @property int|null $sms_message_id
 * @property CarbonImmutable $created_at
 */
class ParentOtp extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ParentOtpFactory> */
    use HasFactory;

    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    /** @var list<string> */
    protected $fillable = ['phone', 'code_hash', 'expires_at', 'sms_message_id'];

    /** @var list<string> */
    protected $hidden = ['code_hash'];

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->attempts < self::MAX_ATTEMPTS && $this->expires_at->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
