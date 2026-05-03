<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class CreateReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'cash_session_id' => ['required', 'uuid', 'exists:cash_sessions,id'],
            'customer_id' => ['nullable', 'uuid', 'exists:customers,id'],
        ];
    }
}
