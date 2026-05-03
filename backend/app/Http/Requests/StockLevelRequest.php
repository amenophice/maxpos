<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StockLevelRequest extends FormRequest
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
            'article_id' => ['required', 'uuid'],
            'gestiune_id' => ['required', 'uuid'],
            'quantity' => ['required', 'numeric', 'min:0'],
        ];
    }
}
