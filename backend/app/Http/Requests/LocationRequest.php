<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LocationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['required', 'string', 'max:120'],
            'county' => ['required', 'string', 'max:120'],
            'is_active' => ['boolean'],
            'saga_agent_token' => ['nullable', 'string', 'max:120'],
            'scale_barcode_prefixes' => ['nullable', 'array'],
            'scale_barcode_prefixes.*' => ['string', 'regex:/^\d{2}$/'],
        ];
    }
}
