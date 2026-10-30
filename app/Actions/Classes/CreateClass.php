<?php

namespace App\Actions\Classes;

use App\Enums\SchoolLevel;
use App\Models\SchoolClass;
use App\Models\Stream;
use App\Support\Academic\DefaultClasses;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Adds a class (with stream "A") to a level the current school offers, at the end of that level.
 */
class CreateClass
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function handle(SchoolLevel $level, string $name): SchoolClass
    {
        if (! $this->currentSchool->require()->levels_offered->contains($level)) {
            throw ValidationException::withMessages(['level' => 'The school does not offer this level. Add it in the school profile first.']);
        }

        $name = ClassRules::className($name);

        return DB::transaction(function () use ($level, $name): SchoolClass {
            $last = SchoolClass::where('level', $level)->max('sequence');
            $sequence = $last !== null ? (int) $last + 1 : DefaultClasses::sequence($level, 1);

            $class = SchoolClass::create(['level' => $level, 'name' => $name, 'sequence' => $sequence]);
            Stream::create(['school_class_id' => $class->id, 'name' => 'A']);

            return $class;
        });
    }
}
