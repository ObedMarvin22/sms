<?php

namespace App\Actions\Subjects;

use App\Models\Subject;

class UpdateSubject
{
    /**
     * @param  list<string>  $levels  SchoolLevel values the school offers
     */
    public function handle(Subject $subject, string $name, ?string $code, array $levels): Subject
    {
        $subject->update([
            'name' => SubjectRules::name($name, ignore: $subject),
            'code' => SubjectRules::code($code, ignore: $subject),
            'levels' => SubjectRules::levels($levels),
        ]);

        return $subject;
    }
}
