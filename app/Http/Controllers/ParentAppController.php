<?php

namespace App\Http\Controllers;

use App\Support\Branding\SchoolIcon;
use App\Support\Branding\SchoolTheme;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * The parent app's shell (SPEC section 2): one Blade page that the Vue app mounts on, plus the
 * files that make it installable. Everything else the app shows comes from /api/v1.
 */
class ParentAppController extends Controller
{
    public function __construct(private CurrentSchool $currentSchool) {}

    public function page(): View
    {
        $school = $this->currentSchool->require();
        $colour = SchoolIcon::colour($school);

        return view('parent.app', [
            'school' => $school,
            'colour' => $colour,
            'textColour' => SchoolTheme::textColourOn($colour),
        ]);
    }

    public function manifest(): JsonResponse
    {
        $school = $this->currentSchool->require();

        return response()->json([
            'name' => $school->name,
            'short_name' => mb_strimwidth($school->name, 0, 12, '…'),
            'description' => "{$school->name} parent app",
            'id' => '/parent',
            'start_url' => '/parent',
            'scope' => '/parent', // includes /parent itself; /parent/ would leave the start page out
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => SchoolIcon::colour($school),
            'icons' => array_map(fn (int $size) => [
                'src' => "/parent/icon-{$size}.png",
                'sizes' => "{$size}x{$size}",
                'type' => 'image/png',
                'purpose' => 'any maskable',
            ], SchoolIcon::SIZES),
        ], 200, ['Content-Type' => 'application/manifest+json']);
    }

    public function icon(int $size): Response
    {
        abort_unless(in_array($size, SchoolIcon::SIZES, true), 404);

        return response(SchoolIcon::png($this->currentSchool->require(), $size), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    /**
     * Its scope is /parent (the app page and its own files), never the staff screens. The cache
     * name changes with every build, so a new upload replaces the old files on parents' phones.
     */
    public function serviceWorker(): Response
    {
        $build = public_path('build/manifest.json');
        $version = is_file($build) ? substr((string) md5_file($build), 0, 12) : 'dev';
        $script = (string) file_get_contents(resource_path('js/parent/sw.js'));

        return response("const VERSION = '{$version}';\n".$script, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
            // The worker lives at /parent/sw.js but must also control the /parent page itself.
            'Service-Worker-Allowed' => '/parent',
        ]);
    }
}
