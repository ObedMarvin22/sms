<?php

namespace App\Actions\Guardians;

use App\Models\Guardian;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Creates a guardian and links them to a child. A phone that is already on file is refused
 * (GuardianRules), so one person never has two records; the screen offers LinkGuardian instead.
 */
class AddGuardian
{
    public function __construct(private LinkGuardian $linkGuardian) {}

    /**
     * @param  array<string, mixed>  $input  guardian details plus relationship, access_level, can_pick_up
     */
    public function handle(Student $student, array $input): Guardian
    {
        $details = GuardianRules::details($input);
        GuardianRules::link($input);

        return DB::transaction(function () use ($student, $details, $input) {
            $guardian = Guardian::create($details);
            $this->linkGuardian->handle($student, $guardian, $input);

            return $guardian;
        });
    }
}
