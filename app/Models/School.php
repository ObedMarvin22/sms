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

/**
 * A tenant. Platform model: it does not use BelongsToSchool.
 *
 * @property int $id
 * @property string $name
 * @property string $subdomain
 * @property SchoolStatus $status
 * @property string|null $logo_path
 * @property string|null $primary_colour
 * @property string|null $secondary_colour
 * @property Collection<int, SchoolLevel> $levels_offered
 * @property string|null $plan
 * @property string|null $paystack_subaccount_code
 * @property string|null $sms_sender_id
 * @property array<string, mixed>|null $settings
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
    'levels_offered',
    'plan',
    'paystack_subaccount_code',
    'sms_sender_id',
    'settings',
])]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

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
