<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

/**
 * Thrown when code tries to write a school-owned record into, or move it to, another school.
 */
class CrossSchoolWrite extends RuntimeException
{
    public static function creating(Model $model, int $currentSchoolId): self
    {
        return new self(sprintf(
            'Cannot create %s for school %s while the current school is %d.',
            $model::class,
            json_encode($model->getAttribute('school_id')),
            $currentSchoolId,
        ));
    }

    public static function moving(Model $model): self
    {
        return new self(sprintf(
            'Cannot move %s #%s to another school (school_id is immutable).',
            $model::class,
            json_encode($model->getKey()),
        ));
    }
}
