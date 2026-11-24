<?php

namespace App\Support\Academic;

use App\Enums\SchoolLevel;

/**
 * Standard grade scales per level (Sprint 6 defaults, chosen by Claude for Obed's review). All
 * editable under Settings → Grading. Early years have no grades (comments only).
 */
final class DefaultGradeScales
{
    /**
     * @return array{name: string, bands: list<array{min_score: int, grade: string, label: string, points: int|null}>}|null
     */
    public static function for(SchoolLevel $level): ?array
    {
        return match ($level) {
            SchoolLevel::Creche, SchoolLevel::Nursery, SchoolLevel::Kg => null,

            // NaCCA standards-based proficiency levels.
            SchoolLevel::Primary => ['name' => 'Proficiency levels (NaCCA)', 'bands' => [
                ['min_score' => 80, 'grade' => 'HP', 'label' => 'Highly proficient', 'points' => null],
                ['min_score' => 68, 'grade' => 'P', 'label' => 'Proficient', 'points' => null],
                ['min_score' => 54, 'grade' => 'AP', 'label' => 'Approaching proficiency', 'points' => null],
                ['min_score' => 40, 'grade' => 'D', 'label' => 'Developing', 'points' => null],
                ['min_score' => 0, 'grade' => 'E', 'label' => 'Emerging', 'points' => null],
            ]],

            // BECE-style 1–9; the grade number is also its aggregate points.
            SchoolLevel::Jhs => ['name' => 'Grades 1 to 9 (BECE style)', 'bands' => [
                ['min_score' => 80, 'grade' => '1', 'label' => 'Highest', 'points' => 1],
                ['min_score' => 70, 'grade' => '2', 'label' => 'Higher', 'points' => 2],
                ['min_score' => 65, 'grade' => '3', 'label' => 'High', 'points' => 3],
                ['min_score' => 60, 'grade' => '4', 'label' => 'High average', 'points' => 4],
                ['min_score' => 55, 'grade' => '5', 'label' => 'Average', 'points' => 5],
                ['min_score' => 50, 'grade' => '6', 'label' => 'Low average', 'points' => 6],
                ['min_score' => 45, 'grade' => '7', 'label' => 'Low', 'points' => 7],
                ['min_score' => 40, 'grade' => '8', 'label' => 'Lower', 'points' => 8],
                ['min_score' => 0, 'grade' => '9', 'label' => 'Lowest', 'points' => 9],
            ]],

            // WASSCE-style A1–F9.
            SchoolLevel::Shs => ['name' => 'Grades A1 to F9 (WASSCE style)', 'bands' => [
                ['min_score' => 80, 'grade' => 'A1', 'label' => 'Excellent', 'points' => 1],
                ['min_score' => 70, 'grade' => 'B2', 'label' => 'Very good', 'points' => 2],
                ['min_score' => 65, 'grade' => 'B3', 'label' => 'Good', 'points' => 3],
                ['min_score' => 60, 'grade' => 'C4', 'label' => 'Credit', 'points' => 4],
                ['min_score' => 55, 'grade' => 'C5', 'label' => 'Credit', 'points' => 5],
                ['min_score' => 50, 'grade' => 'C6', 'label' => 'Credit', 'points' => 6],
                ['min_score' => 45, 'grade' => 'D7', 'label' => 'Pass', 'points' => 7],
                ['min_score' => 40, 'grade' => 'E8', 'label' => 'Pass', 'points' => 8],
                ['min_score' => 0, 'grade' => 'F9', 'label' => 'Fail', 'points' => 9],
            ]],
        };
    }
}
