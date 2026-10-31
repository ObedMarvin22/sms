<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ParentApp\FindParentChild;
use App\Actions\ParentApp\ListParentChildren;
use App\Http\Resources\V1\ChildResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChildrenController extends ParentController
{
    public function index(Request $request, ListParentChildren $children): AnonymousResourceCollection
    {
        return ChildResource::collection($children->handle($this->guardian($request)));
    }

    public function show(Request $request, int $student, FindParentChild $find, ListParentChildren $children): ChildResource
    {
        $guardian = $this->guardian($request);
        $find->handle($guardian, $student);

        return new ChildResource($children->handle($guardian, $student)[0]);
    }
}
