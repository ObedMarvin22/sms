<?php

namespace App\Support\Setup;

use App\Enums\SetupStep;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\Term;
use App\Support\Tenancy\CurrentSchool;

/**
 * Where the current school stands in setup. Shared by the wizard, the dashboard banner
 * and the "ready for students" middleware, so they always agree.
 */
final class SetupProgress
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function isDone(SetupStep $step): bool
    {
        $school = $this->currentSchool->require();

        return match ($step) {
            SetupStep::Profile => $school->levels_offered->isNotEmpty() && $school->phone !== null,
            SetupStep::AcademicYear => Term::current()->exists(),
            SetupStep::Classes => SchoolClass::exists(),
            SetupStep::Subjects => Subject::exists(),
            SetupStep::ClassSubjects => SchoolClass::exists() && SchoolClass::doesntHave('subjects')->doesntExist(),
        };
    }

    /**
     * A step can be worked on once every step before it is done.
     */
    public function isOpen(SetupStep $step): bool
    {
        foreach (SetupStep::cases() as $earlier) {
            if ($earlier === $step) {
                return true;
            }
            if (! $this->isDone($earlier)) {
                return false;
            }
        }

        return true;
    }

    public function nextStep(): ?SetupStep
    {
        foreach (SetupStep::cases() as $step) {
            if (! $this->isDone($step)) {
                return $step;
            }
        }

        return null;
    }

    public function isComplete(): bool
    {
        return $this->nextStep() === null;
    }

    /**
     * Students can be entered once there is a current term and at least one class (roadmap).
     */
    public function readyForStudents(): bool
    {
        return $this->isDone(SetupStep::AcademicYear) && $this->isDone(SetupStep::Classes);
    }
}
