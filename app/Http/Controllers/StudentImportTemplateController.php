<?php

namespace App\Http\Controllers;

use App\Actions\Students\Import\BuildImportTemplate;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Downloads the student import template for the current school.
 */
class StudentImportTemplateController extends Controller
{
    public function __invoke(BuildImportTemplate $template, CurrentSchool $currentSchool): BinaryFileResponse
    {
        return Excel::download($template->handle(), 'students-import-'.Str::slug($currentSchool->require()->subdomain).'.xlsx');
    }
}
