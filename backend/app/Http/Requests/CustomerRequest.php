<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CustomerRequest extends FormRequest
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
            'is_company' => ['boolean'],
            'cui' => ['nullable', 'string', 'max:32'],
            'registration_number' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:120'],
            'county' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
        ];
    }
}
