<?php

namespace App\Models;

use App\Enums\CreditEntryType;
use App\Models\Concerns\BelongsToSchool;
use Carbon\CarbonImmutable;
use Database\Factories\MessageCreditEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * One line of a school's SMS credit (units). Append-only: changes and deletes throw.
 *
 * @property int $id
 * @property CreditEntryType $type
 * @property int $units
 * @property string|null $note
 * @property int|null $sms_message_id
 * @property int|null $created_by
 * @property CarbonImmutable $created_at
 * @property-read User|null $creator
 */
#[Fillable(['type', 'units', 'note', 'sms_message_id', 'created_by'])]
class MessageCreditEntry extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<MessageCreditEntryFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'message_credit_ledger';

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Credit entries are never changed.'));
        static::deleting(fn () => throw new LogicException('Credit entries are never deleted.'));
    }

    /**
     * The current school's SMS units left.
     */
    public static function balance(): int
    {
        return (int) self::sum('units');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['type' => CreditEntryType::class, 'units' => 'integer', 'created_at' => 'immutable_datetime'];
    }
}
