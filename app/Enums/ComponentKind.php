<?php

namespace App\Enums;

/**
 * What a part of an assessment scheme is. Continuous assessment is every kind except the exam.
 */
enum ComponentKind: string
{
    case ClassWork = 'class_work';
    case Test = 'test';
    case Project = 'project';
    case Homework = 'homework';
    case Exam = 'exam';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ClassWork => 'Class work',
            self::Test => 'Tests',
            self::Project => 'Project',
            self::Homework => 'Homework',
            self::Exam => 'End of term exam',
            self::Other => 'Other',
        };
    }

    public function isContinuous(): bool
    {
        return $this !== self::Exam;
    }
}
