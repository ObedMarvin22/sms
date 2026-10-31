<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ParentApp\ListParentNotifications;
use App\Actions\ParentApp\MarkParentNotificationsRead;
use App\Http\Resources\V1\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationsController extends ParentController
{
    public function index(Request $request, ListParentNotifications $list): AnonymousResourceCollection
    {
        $guardian = $this->guardian($request);

        $perPage = min(50, max(1, $request->integer('per_page', 20)));

        return NotificationResource::collection($list->handle($guardian, $perPage))
            ->additional(['unread' => ListParentNotifications::visible($guardian)->whereNull('read_at')->count()]);
    }

    public function read(Request $request, string $notification, MarkParentNotificationsRead $mark): JsonResponse
    {
        $mark->handle($this->guardian($request), $notification);

        return response()->json(['data' => ['read' => true]]);
    }

    public function readAll(Request $request, MarkParentNotificationsRead $mark): JsonResponse
    {
        return response()->json(['data' => ['marked' => $mark->handle($this->guardian($request))]]);
    }
}
