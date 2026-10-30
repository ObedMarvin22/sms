<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\BillingRunStatus;
use App\Models\Concerns\BelongsToSchool;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Database\Factories\BillingRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One billing of a term's students, processed in queued chunks.
 *
 * @property int $id
 * @property int $term_id
 * @property int|null $user_id
 * @property BillingRunStatus $status
 * @property int $total_students
 * @property array<int|string, array{name: string, reason: string}>|null $skipped student id => why
 * @property Money $total_net
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable $created_at
 * @property-read Term $term
 * @property-read User|null $user
 */
#[Fillable(['term_id'])]
class BillingRun extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BillingRunFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['status' => 'queued', 'total_net' => 0];

    public function billedCount(): int
    {
        return Bill::where('billing_run_id', $this->id)->count();
    }

    public function skippedCount(): int
    {
        return count($this->skipped ?? []);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BillingRunStatus::class,
            'skipped' => 'array',
            'total_net' => MoneyCast::class,
            'completed_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }
}
