<?php

namespace App\Http\Requests\Api\V1;

use App\Rules\GhanaPhone;
use Illuminate\Foundation\Http\FormRequest;

class RequestParentCodeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['phone' => ['required', 'string', 'max:30', new GhanaPhone]];
    }
}
