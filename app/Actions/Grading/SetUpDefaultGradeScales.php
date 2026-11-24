<?php

namespace App\Actions\Grading;

use App\Models\GradeScale;
use App\Support\Academic\DefaultGradeScales;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\DB;

/**
 * Gives every graded level the school offers its standard scale, where it has none yet.
 * Early years are skipped (comments, no grades).
 */
class SetUpDefaultGradeScales
{
    public function __construct(private SaveGradeScale $save) {}

    /**
     * @return int How many scales were created.
     */
    public function handle(): int
    {
        return DB::transaction(function (): int {
            $existing = GradeScale::pluck('level')->all();
            $created = 0;

            foreach (app(CurrentSchool::class)->require()->levels_offered as $level) {
                $default = DefaultGradeScales::for($level);
                if ($default === null || in_array($level, $existing, true)) {
                    continue;
                }

                $this->save->handle($level, $default['name'], $default['bands']);
                $created++;
            }

            return $created;
        });
    }
}
