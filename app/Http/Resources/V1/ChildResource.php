<?php

namespace App\Http\Resources\V1;

use App\Enums\GuardianAccess;
use App\Support\ParentApp\ParentChild;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of the parent's children. "balance" is null unless the parent has full access.
 *
 * @property ParentChild $resource
 */
class ChildResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $link = $this->resource->link;
        $student = $link->student;
        $stream = $student->currentEnrolment?->stream;

        return [
            'id' => $student->id,
            'name' => $student->full_name,
            'first_name' => $student->first_name,
            'admission_number' => $student->admission_number,
            'status' => $student->status->value,
            'class' => $stream?->full_name,
            'relationship' => $link->relationship->value,
            'access' => $link->access_level->value,
            'sees_fees' => $link->access_level === GuardianAccess::Full,
            'today' => $this->resource->today?->value,
            'balance' => MoneyData::of($this->resource->balance),
        ];
    }
}
