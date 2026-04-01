<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'delivery_address' => ['required', 'string', 'max:500'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'delivery_address' => is_string($this->delivery_address) ? trim($this->delivery_address) : $this->delivery_address,
            'customer_note' => is_string($this->customer_note) ? trim($this->customer_note) : $this->customer_note,
            'idempotency_key' => is_string($this->idempotency_key) ? trim($this->idempotency_key) : $this->idempotency_key,
        ]);
    }
}
