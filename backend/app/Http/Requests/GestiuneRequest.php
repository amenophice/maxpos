<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GestiuneRequest extends FormRequest
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
            'location_id' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['global-valoric', 'cantitativ-valoric'])],
            'is_active' => ['boolean'],
        ];
    }
}
