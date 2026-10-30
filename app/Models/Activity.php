<?php

namespace App\Models;

use App\Support\Tenancy\CurrentSchool;
use App\Support\Tenancy\SchoolScope;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * Activity log entry (config/activitylog.php activity_model).
 *
 * School-aware but not BelongsToSchool: platform events have no school, which that trait
 * refuses. Entries get the current school, or else the school of what they describe
 * (e.g. a console change to a school's user), so that school's admins can see them.
 * Queries are limited to the current school when one is set.
 *
 * @property int|null $school_id
 */
class Activity extends SpatieActivity
{
    protected static function booted(): void
    {
        static::addGlobalScope(new SchoolScope);

        static::creating(function (Activity $activity): void {
            $subject = $activity->subject;

            $activity->school_id ??= app(CurrentSchool::class)->id()
                ?? ($subject instanceof School ? $subject->id : $subject?->getAttribute('school_id'));
        });
    }
}
