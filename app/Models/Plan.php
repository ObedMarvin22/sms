<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Support\Money\Money;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Orange Tree's price per active student per term (platform model, no school).
 *
 * @property int $id
 * @property string $name
 * @property Money $rate_per_student
 * @property string|null $description
 * @property bool $is_active
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    use LogsActivity;

    /** @var list<string> */
    protected $fillable = ['name', 'rate_per_student', 'description', 'is_active'];

    /**
     * @return HasMany<School, $this>
     */
    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('saas')
            ->logOnly(['name', 'rate_per_student', 'is_active'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_per_student' => MoneyCast::class,
            'is_active' => 'boolean',
        ];
    }
}
