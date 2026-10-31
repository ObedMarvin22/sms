<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\SaasPaymentMethod;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Database\Factories\SaasPaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Money a school paid Orange Tree against an invoice (platform model). Never changed or deleted.
 *
 * @property int $id
 * @property int $saas_invoice_id
 * @property int $school_id
 * @property Money $amount
 * @property CarbonImmutable $paid_on
 * @property SaasPaymentMethod $method
 * @property string|null $reference
 * @property string|null $note
 * @property int|null $recorded_by
 * @property-read User|null $recorder
 */
class SaasPayment extends Model
{
    /** @use HasFactory<SaasPaymentFactory> */
    use HasFactory;

    use LogsActivity;

    public const UPDATED_AT = null;

    /** @var list<string> */
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Orange Tree payments are never changed.'));
        static::deleting(fn () => throw new LogicException('Orange Tree payments are never deleted.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('saas')
            ->logOnly(['saas_invoice_id', 'school_id', 'amount', 'paid_on', 'method', 'reference'])
            ->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => MoneyCast::class,
            'paid_on' => 'immutable_date',
            'method' => SaasPaymentMethod::class,
            'created_at' => 'immutable_datetime',
        ];
    }
}
