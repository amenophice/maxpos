<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'cui' => ['required', 'string', 'regex:/^(RO)?\d{2,10}$/i', 'unique:tenants,cui'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required'],
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->has('cui')) {
            $digits = preg_replace('/^RO/i', '', $this->cui);
            $this->merge(['cui' => 'RO'.$digits]);
        }
    }
}
