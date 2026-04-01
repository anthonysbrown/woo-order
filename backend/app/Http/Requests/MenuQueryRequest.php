<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MenuQueryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'available_only' => ['sometimes', 'boolean'],
            'category' => ['sometimes', 'string', 'max:80'],
            'per_page' => ['sometimes', 'integer', 'between:1,50'],
        ];
    }
}
