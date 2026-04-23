<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BarcodeRequest extends FormRequest
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
            'barcode' => ['required', 'string', 'max:64'],
            'type' => ['required', Rule::in(['ean13', 'ean8', 'code128', 'internal', 'scale'])],
        ];
    }
}
