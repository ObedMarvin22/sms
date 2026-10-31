<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\ParentResource;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): ParentResource
    {
        return new ParentResource($request->attributes->get('guardian'));
    }
}
