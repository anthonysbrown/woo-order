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
            'category' => ['sometimes', 'string', 'max:80'],
        ];
    }
}
