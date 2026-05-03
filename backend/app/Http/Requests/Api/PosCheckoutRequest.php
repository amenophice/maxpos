<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PosCheckoutRequest extends FormRequest
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
            'client_local_id' => ['nullable', 'string', 'max:64'],
            'receipt_discount' => ['nullable', 'numeric', 'min:0'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.article_id' => ['required', 'uuid', 'exists:articles,id'],
            'items.*.quantity' => ['required', 'numeric', 'not_in:0'],
            'items.*.gestiune_id' => ['nullable', 'uuid', 'exists:gestiuni,id'],
            'items.*.discount_amount' => ['nullable', 'numeric', 'min:0'],

            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', Rule::in(['cash', 'card', 'voucher', 'modern', 'transfer'])],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
