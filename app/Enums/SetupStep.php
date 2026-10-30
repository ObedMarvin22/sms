<?php

namespace App\Enums;

/**
 * The setup wizard's steps, in the order a new school completes them (Sprint 2 steps 2–6).
 */
enum SetupStep: string
{
    case Profile = 'profile';
    case AcademicYear = 'academic_year';
    case Classes = 'classes';
    case Subjects = 'subjects';
    case ClassSubjects = 'class_subjects';

    public function label(): string
    {
        return match ($this) {
            self::Profile => 'School profile',
            self::AcademicYear => 'Academic year',
            self::Classes => 'Classes',
            self::Subjects => 'Subjects',
            self::ClassSubjects => 'Class subjects',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Profile => 'Choose your levels and add a phone number.',
            self::AcademicYear => 'Create the current academic year and its terms.',
            self::Classes => 'Create your classes and streams.',
            self::Subjects => 'Add the subjects you teach.',
            self::ClassSubjects => 'Choose the subjects each class takes.',
        };
    }

    /**
     * The screen where this step is done.
     */
    public function route(): string
    {
        return match ($this) {
            self::Profile => 'school.profile',
            self::AcademicYear => 'academic-years.index',
            self::Classes => 'classes.index',
            self::Subjects => 'subjects.index',
            self::ClassSubjects => 'class-subjects.index',
        };
    }
}
