<?php

namespace App\Actions\Subjects;

use App\Models\Programme;

class RenameProgramme
{
    public function handle(Programme $programme, string $name): Programme
    {
        $programme->update(['name' => SubjectRules::programmeName($name, ignore: $programme)]);

        return $programme;
    }
}
