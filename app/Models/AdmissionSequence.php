<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\AdmissionSequenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Counter for admission numbers, per school and scope (e.g. the year "2026").
 *
 * @property int $id
 * @property string $scope
 * @property int $last_number
 */
#[Fillable(['scope', 'last_number'])]
class AdmissionSequence extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<AdmissionSequenceFactory> */
    use HasFactory;
}
