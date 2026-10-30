<?php

namespace App\Actions\Classes;

use App\Models\SchoolClass;
use App\Models\Stream;
use App\Support\Academic\DefaultClasses;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;

/**
 * Creates the usual classes (each with stream "A") for every level the current school offers.
 *
 * Safe to run again: it only adds classes that are missing and never renames or deletes.
 */
class GenerateDefaultClasses
{
    public function __construct(private CurrentSchool $currentSchool) {}

    /**
     * @return int How many classes were created.
     */
    public function handle(): int
    {
        $school = $this->currentSchool->require();

        return DB::transaction(function () use ($school): int {
            $existing = SchoolClass::pluck('name')->map(fn (string $name) => mb_strtolower($name))->all();
            $created = 0;

            foreach ($school->levels_offered as $level) {
                foreach (DefaultClasses::for($level) as $position => $name) {
                    if (in_array(mb_strtolower($name), $existing, true)) {
                        continue;
                    }

                    $class = SchoolClass::create([
                        'level' => $level,
                        'name' => $name,
                        'sequence' => DefaultClasses::sequence($level, $position + 1),
                    ]);
                    Stream::create(['school_class_id' => $class->id, 'name' => 'A']);
                    $created++;
                }
            }

            return $created;
        });
    }
}
