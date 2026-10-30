<?php

namespace App\Actions\Classes;

use App\Models\SchoolClass;

class RenameClass
{
    public function handle(SchoolClass $class, string $name): SchoolClass
    {
        $class->update(['name' => ClassRules::className($name, ignore: $class)]);

        return $class;
    }
}
