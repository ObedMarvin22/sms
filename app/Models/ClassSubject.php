<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Database\Factories\ClassSubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A subject taken by a class. A pivot model so it gets school_id (BelongsToSchool).
 *
 * @property int $id
 * @property int $school_class_id
 * @property int $subject_id
 */
class ClassSubject extends Pivot
{
    use BelongsToSchool;

    /** @use HasFactory<ClassSubjectFactory> */
    use HasFactory;

    public $incrementing = true;

    protected $table = 'class_subject';

    /** @var list<string> */
    protected $fillable = ['school_class_id', 'subject_id'];
}
