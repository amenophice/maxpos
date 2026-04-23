<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class AddReceiptItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'article_id' => ['required', 'uuid', 'exists:articles,id'],
            'quantity' => ['required', 'numeric', 'not_in:0'],
            'gestiune_id' => ['nullable', 'uuid', 'exists:gestiuni,id'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
