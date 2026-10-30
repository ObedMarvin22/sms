<?php

namespace App\Actions\Classes;

use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

/**
 * Gives every class all the subjects taught at its level.
 *
 * Safe to run again: only adds missing links, never removes a subject from a class.
 */
class ApplyDefaultClassSubjects
{
    /**
     * @return int How many class-subject links were created.
     */
    public function handle(): int
    {
        return DB::transaction(function (): int {
            $subjects = Subject::all();
            $existing = ClassSubject::get(['school_class_id', 'subject_id'])
                ->map(fn (ClassSubject $link) => $link->school_class_id.':'.$link->subject_id)
                ->flip();
            $created = 0;

            foreach (SchoolClass::all() as $class) {
                foreach ($subjects as $subject) {
                    if (! $subject->levels->contains($class->level) || $existing->has($class->id.':'.$subject->id)) {
                        continue;
                    }

                    ClassSubject::create(['school_class_id' => $class->id, 'subject_id' => $subject->id]);
                    $created++;
                }
            }

            return $created;
        });
    }
}
