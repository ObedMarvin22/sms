<?php

namespace App\Actions\Subjects;

use App\Enums\SchoolLevel;
use App\Enums\SubjectType;
use App\Models\Programme;
use App\Models\ProgrammeSubject;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Replaces a programme's core and elective subjects. Only SHS subjects of this school;
 * a subject cannot be both core and elective.
 */
class SetProgrammeSubjects
{
    /**
     * @param  list<int>  $coreIds
     * @param  list<int>  $electiveIds
     */
    public function handle(Programme $programme, array $coreIds, array $electiveIds): Programme
    {
        $coreIds = array_values(array_unique(array_map('intval', $coreIds)));
        $electiveIds = array_values(array_unique(array_map('intval', $electiveIds)));

        if (array_intersect($coreIds, $electiveIds) !== []) {
            throw ValidationException::withMessages(['subjects' => 'A subject cannot be both core and elective.']);
        }

        // School-scoped: ids from another school simply are not found.
        $subjects = Subject::whereKey([...$coreIds, ...$electiveIds])->get()->keyBy('id');

        foreach ([...$coreIds, ...$electiveIds] as $id) {
            $subject = $subjects->get($id);

            if ($subject === null || ! $subject->levels->contains(SchoolLevel::Shs)) {
                throw ValidationException::withMessages(['subjects' => 'Choose SHS subjects of this school only.']);
            }
        }

        DB::transaction(function () use ($programme, $coreIds, $electiveIds, $subjects): void {
            ProgrammeSubject::where('programme_id', $programme->id)->delete();

            foreach ([SubjectType::Core->value => $coreIds, SubjectType::Elective->value => $electiveIds] as $type => $ids) {
                foreach ($ids as $id) {
                    ProgrammeSubject::create(['programme_id' => $programme->id, 'subject_id' => $id, 'type' => $type]);
                }
            }

            activity('subjects')
                ->performedOn($programme)
                ->event('programme_subjects_set')
                ->withProperties([
                    'core' => array_map(fn (int $id) => $subjects[$id]->name, $coreIds),
                    'electives' => array_map(fn (int $id) => $subjects[$id]->name, $electiveIds),
                ])
                ->log('programme subjects set');
        });

        return $programme->load('subjects');
    }
}
