<?php

namespace App\Enums;

enum Gender: string
{
    case Female = 'female';
    case Male = 'male';

    public function label(): string
    {
        return match ($this) {
            self::Female => 'Female',
            self::Male => 'Male',
        };
    }
}
