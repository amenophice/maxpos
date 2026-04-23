<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return static::fieldRules();
    }

    public static function fieldRules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'group_id' => ['nullable', 'uuid'],
            'default_gestiune_id' => ['nullable', 'uuid'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'price' => ['required', 'numeric', 'min:0'],
            'unit' => ['required', 'string', 'max:20'],
            'plu' => ['nullable', 'string', 'max:20'],
            'is_active' => ['boolean'],
            'photo_path' => ['nullable', 'string', 'max:512'],
        ];
    }
}
