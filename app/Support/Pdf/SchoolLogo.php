<?php

namespace App\Support\Pdf;

use App\Actions\Schools\UpdateSchoolProfile;
use Illuminate\Support\Facades\Storage;

/**
 * The school's logo as a data URI for dompdf: the logo is on the private disk and dompdf may not
 * fetch files, so it is embedded.
 */
final class SchoolLogo
{
    public static function dataUri(?string $path): ?string
    {
        $disk = Storage::disk(UpdateSchoolProfile::LOGO_DISK);
        if ($path === null || ! $disk->exists($path)) {
            return null;
        }

        return 'data:'.($disk->mimeType($path) ?: 'image/png').';base64,'.base64_encode((string) $disk->get($path));
    }
}
