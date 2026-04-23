<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_company' => $this->is_company,
            'cui' => $this->cui,
            'registration_number' => $this->registration_number,
            'city' => $this->city,
            'county' => $this->county,
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }
}
