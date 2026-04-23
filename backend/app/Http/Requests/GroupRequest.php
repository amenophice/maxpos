<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GroupRequest extends FormRequest
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
            'parent_id' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'display_order' => ['integer', 'min:0'],
        ];
    }
}
