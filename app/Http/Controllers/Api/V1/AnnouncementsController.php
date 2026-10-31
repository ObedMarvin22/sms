<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ParentApp\ListParentAnnouncements;
use App\Http\Resources\V1\AnnouncementResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnnouncementsController extends ParentController
{
    public function index(Request $request, ListParentAnnouncements $announcements): AnonymousResourceCollection
    {
        return AnnouncementResource::collection($announcements->handle($this->guardian($request)));
    }

    public function show(Request $request, int $announcement, ListParentAnnouncements $announcements): AnnouncementResource
    {
        return new AnnouncementResource($announcements->find($this->guardian($request), $announcement));
    }
}
