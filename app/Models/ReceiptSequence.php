<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\ReceiptSequenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Receipt number counter per school and year.
 *
 * @property int $id
 * @property int $year
 * @property int $last_number
 */
#[Fillable(['year', 'last_number'])]
class ReceiptSequence extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<ReceiptSequenceFactory> */
    use HasFactory;
}
