<?php

namespace App\Actions\Subjects;

use App\Enums\SchoolLevel;
use App\Models\Subject;
use App\Support\Academic\DefaultSubjects;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;

/**
 * Creates the preset subjects for the current school's levels.
 *
 * Safe to run again: a missing subject is created; an existing one (same name) only gains any
 * preset levels it lacks. Nothing is renamed or removed.
 */
class GenerateDefaultSubjects
{
    public function __construct(private CurrentSchool $currentSchool) {}

    /**
     * @return int How many subjects were created.
     */
    public function handle(): int
    {
        $school = $this->currentSchool->require();

        return DB::transaction(function () use ($school): int {
            $existing = Subject::all()->keyBy(fn (Subject $s) => mb_strtolower($s->name));
            $usedCodes = Subject::whereNotNull('code')->pluck('code')->all();
            $created = 0;

            foreach (DefaultSubjects::merged($school->levels_offered) as $name => $levels) {
                $subject = $existing->get(mb_strtolower($name));

                if ($subject !== null) {
                    $missing = array_filter($levels, fn (SchoolLevel $l) => ! $subject->levels->contains($l));
                    if ($missing !== []) {
                        $subject->update(['levels' => [...$subject->levels->all(), ...$missing]]);
                    }

                    continue;
                }

                $code = DefaultSubjects::CODES[$name] ?? null;
                Subject::create([
                    'name' => $name,
                    'code' => $code !== null && ! in_array($code, $usedCodes, true) ? $code : null,
                    'levels' => $levels,
                ]);
                $usedCodes[] = $code;
                $created++;
            }

            return $created;
        });
    }
}
