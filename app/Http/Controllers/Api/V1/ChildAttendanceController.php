<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ParentApp\ChildAttendance;
use App\Actions\ParentApp\FindParentChild;
use App\Enums\AttendanceStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChildAttendanceController extends ParentController
{
    public function __invoke(Request $request, int $student, FindParentChild $find, ChildAttendance $attendance): JsonResponse
    {
        $link = $find->handle($this->guardian($request), $student);
        $month = $attendance->handle($link->student, $request->filled('month') ? $request->string('month')->toString() : null);

        return response()->json(['data' => [
            'month' => $month['month'],
            'counts' => $month['counts'],
            'days' => array_map(fn (array $day) => ['date' => $day['date'], 'status' => $day['status']->value], $month['days']),
            'labels' => collect(AttendanceStatus::cases())->mapWithKeys(fn (AttendanceStatus $s) => [$s->value => $s->label()])->all(),
        ]]);
    }
}
