<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\Residence;
use App\Models\Concerns\BelongsToSchool;
use App\Support\Money\Money;
use Carbon\CarbonImmutable;
use Database\Factories\BillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A student's bill for a term (this term's charges only). Never edited after issue.
 *
 * @property int $id
 * @property int $student_id
 * @property int $enrolment_id
 * @property int $term_id
 * @property int $school_class_id
 * @property Residence $residence
 * @property int|null $billing_run_id
 * @property Money $gross
 * @property Money $reductions
 * @property Money $net
 * @property Money $arrears balance just before this bill
 * @property CarbonImmutable $issued_at
 * @property-read Term $term
 * @property-read SchoolClass $schoolClass
 * @property-read Student $student
 * @property-read Collection<int, BillLine> $lines
 * @property-read Collection<int, BillInstalment> $instalments
 */
#[Fillable(['student_id', 'enrolment_id', 'term_id', 'school_class_id', 'residence', 'billing_run_id', 'gross', 'reductions', 'net', 'arrears', 'issued_at'])]
class Bill extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<BillFactory> */
    use HasFactory;

    /**
     * Arrears plus this term's net: what the parent is asked to pay.
     */
    public function payable(): Money
    {
        return $this->arrears->plus($this->net);
    }

    /**
     * @return HasMany<BillLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BillLine::class)->orderBy('sequence');
    }

    /**
     * @return HasMany<BillInstalment, $this>
     */
    public function instalments(): HasMany
    {
        return $this->hasMany(BillInstalment::class)->orderBy('sequence');
    }

    /**
     * @return BelongsTo<Term, $this>
     */
    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    /**
     * @return BelongsTo<SchoolClass, $this>
     */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class)->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'residence' => Residence::class,
            'gross' => MoneyCast::class,
            'reductions' => MoneyCast::class,
            'net' => MoneyCast::class,
            'arrears' => MoneyCast::class,
            'issued_at' => 'immutable_datetime',
        ];
    }
}
