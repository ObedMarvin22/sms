<?php

namespace App\Actions\Subjects;

use App\Models\Programme;
use App\Models\Subject;
use App\Support\Academic\DefaultSubjects;
use Illuminate\Support\Facades\DB;

/**
 * Creates the preset SHS programmes with the four core subjects and their electives.
 *
 * Runs GenerateDefaultSubjects first so those subjects exist. Safe to run again: existing
 * programmes (same name) are left as they are.
 */
class GenerateDefaultProgrammes
{
    public function __construct(
        private GenerateDefaultSubjects $generateDefaultSubjects,
        private SetProgrammeSubjects $setProgrammeSubjects,
    ) {}

    /**
     * @return int How many programmes were created.
     */
    public function handle(): int
    {
        SubjectRules::requireShs();

        return DB::transaction(function (): int {
            $this->generateDefaultSubjects->handle();

            $subjectIds = Subject::pluck('id', 'name');
            $existing = Programme::pluck('name')->map(fn (string $n) => mb_strtolower($n))->all();
            $created = 0;

            foreach (DefaultSubjects::PROGRAMMES as $name => $electives) {
                if (in_array(mb_strtolower($name), $existing, true)) {
                    continue;
                }

                $programme = Programme::create(['name' => $name]);
                $this->setProgrammeSubjects->handle(
                    $programme,
                    self::ids($subjectIds->all(), DefaultSubjects::SHS_CORE),
                    self::ids($subjectIds->all(), $electives),
                );
                $created++;
            }

            return $created;
        });
    }

    /**
     * @param  array<string, int>  $idsByName
     * @param  list<string>  $names
     * @return list<int>
     */
    private static function ids(array $idsByName, array $names): array
    {
        return array_values(array_filter(array_map(fn (string $name) => $idsByName[$name] ?? null, $names)));
    }
}
