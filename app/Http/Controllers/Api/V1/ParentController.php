<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Guardian;
use Illuminate\Http\Request;

/**
 * Parent API controllers. The "parent" middleware has already found the signed-in guardian.
 */
abstract class ParentController extends Controller
{
    protected function guardian(Request $request): Guardian
    {
        $guardian = $request->attributes->get('guardian');
        abort_unless($guardian instanceof Guardian, 403);

        return $guardian;
    }
}
