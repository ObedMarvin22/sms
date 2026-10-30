<?php

namespace App\Http\Controllers;

use App\Actions\Schools\UpdateSchoolProfile;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves the current school's logo from the private disk.
 *
 * No id or path in the URL: a subdomain can only ever show its own school's logo.
 * Open to guests because the login page shows it.
 */
class SchoolLogoController extends Controller
{
    public function __invoke(CurrentSchool $currentSchool): StreamedResponse
    {
        $school = $currentSchool->require();
        $disk = Storage::disk(UpdateSchoolProfile::LOGO_DISK);

        abort_if($school->logo_path === null || ! $disk->exists($school->logo_path), 404);

        return $disk->response($school->logo_path, null, [
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
