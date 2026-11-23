<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Support\Pdf\FeeDocuments;
use Symfony\Component\HttpFoundation\Response;

/**
 * A student's fee statement as an A4 PDF, for staff who see payments. The student is bound
 * through the school scope. Parents download the same statement through the API.
 */
class StatementPdfController extends Controller
{
    public function __invoke(FeeDocuments $documents, Student $student): Response
    {
        return $documents->statement($student);
    }
}
