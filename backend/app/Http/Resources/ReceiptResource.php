<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'location_id' => $this->location_id,
            'cash_session_id' => $this->cash_session_id,
            'customer_id' => $this->customer_id,
            'status' => $this->status,
            'subtotal' => (string) $this->subtotal,
            'vat_total' => (string) $this->vat_total,
            'discount_total' => (string) $this->discount_total,
            'total' => (string) $this->total,
            'fiscal_printed_at' => $this->fiscal_printed_at?->toIso8601String(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => ReceiptItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
