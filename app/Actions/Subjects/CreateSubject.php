<?php

namespace App\Actions\Subjects;

use App\Models\Subject;

class CreateSubject
{
    /**
     * @param  list<string>  $levels  SchoolLevel values the school offers
     */
    public function handle(string $name, ?string $code, array $levels): Subject
    {
        return Subject::create([
            'name' => SubjectRules::name($name),
            'code' => SubjectRules::code($code),
            'levels' => SubjectRules::levels($levels),
        ]);
    }
}
