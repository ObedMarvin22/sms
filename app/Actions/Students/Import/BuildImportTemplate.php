<?php

namespace App\Actions\Students\Import;

use App\Enums\Gender;
use App\Enums\GuardianRelationship;
use App\Enums\Residence;
use App\Exports\StudentImportTemplate;
use App\Models\Programme;
use App\Models\Stream;

/**
 * The template for this school, listing its own classes and programmes.
 */
class BuildImportTemplate
{
    public function handle(): StudentImportTemplate
    {
        $classes = Stream::query()->join('school_classes', 'school_classes.id', '=', 'streams.school_class_id')
            ->orderBy('school_classes.sequence')->orderBy('streams.name')
            ->get(['streams.name', 'school_classes.name as class_name'])
            ->map(fn (Stream $s): string => $s->getAttribute('class_name').$s->name)->all();

        // No example row: an unchanged template must not import a made-up student.
        return new StudentImportTemplate([], [
            'class' => array_values($classes),
            'programme' => array_values(Programme::orderBy('name')->get()->map(fn (Programme $p): string => $p->name)->all()),
            'gender' => array_map(fn (Gender $g) => $g->value, Gender::cases()),
            'relationship' => array_map(fn (GuardianRelationship $r) => $r->value, GuardianRelationship::cases()),
            'residence' => array_map(fn (Residence $r) => $r->value, Residence::cases()),
        ]);
    }
}
