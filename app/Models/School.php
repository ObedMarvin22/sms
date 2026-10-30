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
 * @property string|null $plan
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
     * Audit trail for the profile and status (not billing or internal settings).
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('schools')
            ->logOnly([
                'name', 'status', 'logo_path', 'primary_colour', 'secondary_colour',
                'phone', 'email', 'address', 'levels_offered',
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
        ];
    }
}
