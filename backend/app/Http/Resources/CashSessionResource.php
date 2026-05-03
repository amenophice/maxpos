<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'location_id' => $this->location_id,
            'user_id' => $this->user_id,
            'opened_at' => $this->opened_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'initial_cash' => (string) $this->initial_cash,
            'final_cash' => $this->final_cash !== null ? (string) $this->final_cash : null,
            'expected_cash' => $this->expected_cash !== null ? (string) $this->expected_cash : null,
            'status' => $this->status,
            'notes' => $this->notes,
        ];
    }
}
