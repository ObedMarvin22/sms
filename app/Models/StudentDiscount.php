<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Models\Concerns\BelongsToSchool;
use Database\Factories\StudentDiscountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A reduction given to one student: a school discount (discount_id set) or a scholarship
 * (its own name, sponsor, type, value and items; may end after a term).
 *
 * @property int $id
 * @property int $student_id
 * @property int|null $discount_id
 * @property string|null $name
 * @property string|null $sponsor
 * @property DiscountType|null $type
 * @property int|null $value
 * @property list<int>|null $fee_item_ids
 * @property int|null $ends_term_id
 * @property-read Discount|null $discount
 * @property-read Term|null $endsTerm
 * @property-read Student $student
 */
#[Fillable(['student_id', 'discount_id', 'name', 'sponsor', 'type', 'value', 'fee_item_ids', 'ends_term_id'])]
class StudentDiscount extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentDiscountFactory> */
    use HasFactory;

    use LogsActivity;

    public function isScholarship(): bool
    {
        return $this->discount_id === null;
    }

    /**
     * @return BelongsTo<Discount, $this>
     */
    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function endsTerm(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'ends_term_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * What it takes off, whether it is a school discount or a scholarship.
     *
     * @param  array<int, string>  $itemNames  fee item id => name
     */
    public function describe(array $itemNames): string
    {
        if ($this->discount !== null) {
            return $this->discount->describe($itemNames);
        }

        return $this->type?->describe((int) $this->value, $this->fee_item_ids, $itemNames) ?? '';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('fees')
            ->logOnly(['student_id', 'discount_id', 'name', 'sponsor', 'type', 'value', 'fee_item_ids', 'ends_term_id'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'value' => 'integer',
            'fee_item_ids' => 'array',
        ];
    }
}
