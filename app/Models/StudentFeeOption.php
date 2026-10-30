<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\StudentFeeOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * An optional fee item a student takes (transport, feeding, ...). Adding and removing are logged.
 *
 * @property int $id
 * @property int $student_id
 * @property int $fee_item_id
 * @property-read FeeItem $feeItem
 */
#[Fillable(['student_id', 'fee_item_id'])]
class StudentFeeOption extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<StudentFeeOptionFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * @return BelongsTo<FeeItem, $this>
     */
    public function feeItem(): BelongsTo
    {
        return $this->belongsTo(FeeItem::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('fees')
            ->logOnly(['student_id', 'fee_item_id'])
            ->logOnlyDirty()->dontLogEmptyChanges();
    }
}
