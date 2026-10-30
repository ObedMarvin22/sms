<?php

namespace App\Actions\Subjects;

use App\Models\Programme;

class CreateProgramme
{
    public function handle(string $name): Programme
    {
        SubjectRules::requireShs();

        return Programme::create(['name' => SubjectRules::programmeName($name)]);
    }
}
