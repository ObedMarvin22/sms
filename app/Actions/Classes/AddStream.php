<?php

namespace App\Actions\Classes;

use App\Models\SchoolClass;
use App\Models\Stream;

class AddStream
{
    public function handle(SchoolClass $class, string $name): Stream
    {
        return Stream::create([
            'school_class_id' => $class->id,
            'name' => ClassRules::streamName($class, $name),
        ]);
    }
}
