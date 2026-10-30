<?php

namespace App\Actions\Classes;

use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces the subjects a class takes. Each must be a subject of this school that is taught at
 * the class's level. (From Sprint 5, recorded scores will block removing a subject.)
 */
class SetClassSubjects
{
    /**
     * @param  list<int>  $subjectIds
     */
    public function handle(SchoolClass $class, array $subjectIds): SchoolClass
    {
        $subjectIds = array_values(array_unique(array_map('intval', $subjectIds)));

        // School-scoped: another school's subjects are simply not found.
        $subjects = Subject::whereKey($subjectIds)->get()->keyBy('id');

        foreach ($subjectIds as $id) {
            $subject = $subjects->get($id);

            if ($subject === null || ! $subject->levels->contains($class->level)) {
                throw ValidationException::withMessages([
                    'subjects' => "Choose subjects taught at {$class->level->label()} level.",
                ]);
            }
        }

        $current = ClassSubject::where('school_class_id', $class->id)->pluck('subject_id')->all();
        $added = array_values(array_diff($subjectIds, $current));
        $removed = array_values(array_diff($current, $subjectIds));

        if ($added === [] && $removed === []) {
            return $class;
        }

        DB::transaction(function () use ($class, $added, $removed, $subjects): void {
            ClassSubject::where('school_class_id', $class->id)->whereIn('subject_id', $removed)->delete();

            foreach ($added as $id) {
                ClassSubject::create(['school_class_id' => $class->id, 'subject_id' => $id]);
            }

            $removedNames = Subject::whereKey($removed)->pluck('name')->all();

            activity('classes')
                ->performedOn($class)
                ->event('class_subjects_set')
                ->withProperties([
                    'added' => array_map(fn (int $id) => $subjects[$id]->name, $added),
                    'removed' => $removedNames,
                ])
                ->log('class subjects changed');
        });

        return $class;
    }
}
