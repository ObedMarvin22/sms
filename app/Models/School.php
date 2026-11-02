<?php

namespace App\Models;

use App\Enums\SchoolLevel;
use App\Enums\SchoolStatus;
use Carbon\CarbonImmutable;
use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A tenant. Platform model: it does not use BelongsToSchool.
 *
 * @property int $id
 * @property string $name
 * @property string $subdomain
 * @property SchoolStatus $status
 * @property string|null $logo_path Private "local" disk: schools/{id}/branding/logo.{ext}
 * @property string|null $primary_colour
 * @property string|null $secondary_colour
 * @property string|null $phone +233XXXXXXXXX
 * @property string|null $email
 * @property string|null $address
 * @property Collection<int, SchoolLevel> $levels_offered
 * @property string|null $plan Unused (free text from Sprint 1); the plan is plan_id.
 * @property int|null $plan_id Orange Tree's price plan (SaaS billing)
 * @property-read Plan|null $planModel
 * @property CarbonImmutable|null $saas_lock_override_until Kept open despite an overdue invoice until this date
 * @property string|null $saas_lock_override_reason
 * @property string|null $paystack_subaccount_code
 * @property string|null $paystack_settlement e.g. "GCB Bank · ****1234 · Greenhill Academy Ltd"
 * @property string|null $sms_sender_id
 * @property array<string, mixed>|null $settings Read through App\Support\Settings\SchoolSettings
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable([
    'name',
    'subdomain',
    'status',
    'logo_path',
    'primary_colour',
    'secondary_colour',
    'phone',
    'email',
    'address',
    'levels_offered',
    'plan',
    'paystack_subaccount_code',
    'paystack_settlement',
    'sms_sender_id',
    'settings',
])]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory, LogsActivity;

    /**
     * Orange Tree's price plan. (Named planModel: the old text column is called plan.)
     *
     * @return BelongsTo<Plan, $this>
     */
    public function planModel(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    /**
     * @return HasMany<SaasInvoice, $this>
     */
    public function saasInvoices(): HasMany
    {
        return $this->hasMany(SaasInvoice::class);
    }

    /**
     * Audit trail for the profile, status and price plan (not internal settings).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('schools')
            ->logOnly([
                'name', 'status', 'logo_path', 'primary_colour', 'secondary_colour',
                'phone', 'email', 'address', 'levels_offered', 'plan_id',
                'saas_lock_override_until', 'saas_lock_override_reason',
            ])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SchoolStatus::class,
            'levels_offered' => AsEnumCollection::of(SchoolLevel::class),
            'settings' => 'array',
            'saas_lock_override_until' => 'immutable_date',
        ];
    }
}
