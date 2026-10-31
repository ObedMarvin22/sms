<?php

namespace App\Http\Resources\V1;

use App\Models\Guardian;
use App\Support\Tenancy\CurrentSchool;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The signed-in parent and their school.
 *
 * @mixin Guardian
 */
class ParentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $school = app(CurrentSchool::class)->require();

        return [
            'name' => $this->name,
            'phone' => $this->phone,
            'school' => [
                'name' => $school->name,
                'subdomain' => $school->subdomain,
            ],
        ];
    }
}
