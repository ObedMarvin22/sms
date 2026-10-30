<?php

namespace App\Support\Academic;

use App\Enums\SchoolLevel;

/**
 * Preset GES-based subjects per level and SHS programmes (approved by Obed). All editable.
 * The same name at several levels becomes one subject taught at those levels.
 */
final class DefaultSubjects
{
    /** SHS core subjects: every programme takes these. */
    public const SHS_CORE = ['English Language', 'Core Mathematics', 'Integrated Science', 'Social Studies'];

    /** @var array<string, string> name => code */
    public const CODES = [
        'English Language' => 'ENG', 'Mathematics' => 'MATH', 'Core Mathematics' => 'CMATH',
        'Science' => 'SCI', 'Integrated Science' => 'ISCI', 'Social Studies' => 'SOC',
        'Our World Our People' => 'OWOP', 'Ghanaian Language' => 'GHL', 'Creative Arts' => 'CA',
        'Creative Arts and Design' => 'CAD', 'Religious and Moral Education' => 'RME',
        'Physical Education' => 'PE', 'Physical and Health Education' => 'PHE', 'Computing' => 'COMP',
        'Career Technology' => 'CT', 'French' => 'FRE', 'Language and Literacy' => 'LIT', 'Numeracy' => 'NUM',
        'Biology' => 'BIO', 'Chemistry' => 'CHEM', 'Physics' => 'PHY', 'Elective Mathematics' => 'EMATH',
        'Business Management' => 'BM', 'Financial Accounting' => 'FA', 'Cost Accounting' => 'COST',
        'Economics' => 'ECON', 'Geography' => 'GEOG', 'Government' => 'GOVT', 'History' => 'HIST',
        'Literature in English' => 'LIT-E', 'Christian Religious Studies' => 'CRS',
        'General Knowledge in Art' => 'GKA', 'Graphic Design' => 'GD', 'Picture Making' => 'PM',
        'Sculpture' => 'SCULP', 'Management in Living' => 'MIL', 'Food and Nutrition' => 'FN',
        'Clothing and Textiles' => 'CTX', 'General Agriculture' => 'GAGR', 'Animal Husbandry' => 'AH',
    ];

    /** @var array<string, list<string>> programme => electives */
    public const PROGRAMMES = [
        'General Science' => ['Biology', 'Chemistry', 'Physics', 'Elective Mathematics'],
        'Business' => ['Business Management', 'Financial Accounting', 'Cost Accounting', 'Economics', 'Elective Mathematics'],
        'General Arts' => ['Economics', 'Geography', 'Government', 'History', 'Literature in English', 'French', 'Christian Religious Studies', 'Elective Mathematics'],
        'Visual Arts' => ['General Knowledge in Art', 'Graphic Design', 'Picture Making', 'Sculpture'],
        'Home Economics' => ['Management in Living', 'Food and Nutrition', 'Clothing and Textiles', 'General Knowledge in Art'],
        'Agricultural Science' => ['General Agriculture', 'Animal Husbandry', 'Chemistry', 'Physics', 'Elective Mathematics'],
    ];

    /**
     * @return list<string>
     */
    public static function for(SchoolLevel $level): array
    {
        return match ($level) {
            SchoolLevel::Creche, SchoolLevel::Nursery => [],
            SchoolLevel::Kg => ['Language and Literacy', 'Numeracy', 'Creative Arts', 'Our World Our People', 'Physical Education'],
            SchoolLevel::Primary => [
                'English Language', 'Mathematics', 'Science', 'Our World Our People', 'Ghanaian Language',
                'Creative Arts', 'Religious and Moral Education', 'Physical Education', 'Computing', 'French',
            ],
            SchoolLevel::Jhs => [
                'English Language', 'Mathematics', 'Science', 'Social Studies', 'Religious and Moral Education',
                'Computing', 'Career Technology', 'Creative Arts and Design', 'Ghanaian Language', 'French',
                'Physical and Health Education',
            ],
            // Core plus every elective used by the preset programmes.
            SchoolLevel::Shs => array_values(array_unique([...self::SHS_CORE, ...array_merge(...array_values(self::PROGRAMMES))])),
        };
    }

    /**
     * Subject name => levels, for the given offered levels (names merged across levels).
     *
     * @param  iterable<SchoolLevel>  $levels
     * @return array<string, list<SchoolLevel>>
     */
    public static function merged(iterable $levels): array
    {
        $result = [];

        foreach ($levels as $level) {
            foreach (self::for($level) as $name) {
                $result[$name][] = $level;
            }
        }

        return $result;
    }
}
