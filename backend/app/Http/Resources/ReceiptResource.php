<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $firstItem = $this->relationLoaded('items') ? $this->items->first() : null;
        $gestiune = $firstItem?->relationLoaded('gestiune') ? $firstItem->gestiune : null;
        $customer = $this->relationLoaded('customer') ? $this->customer : null;

        return [
            'id' => $this->id,
            'number' => (string) $this->number,
            'issued_at' => $this->created_at?->toIso8601String(),
            'client_local_id' => $this->client_local_id,
            'location_id' => $this->location_id,
            'cash_session_id' => $this->cash_session_id,
            'customer_id' => $this->customer_id,
            'customer_code' => $customer?->cui,
            'customer_name' => $customer?->name,
            'gestiune_code' => $gestiune?->saga_cod,
            'gestiune_name' => $gestiune?->name,
            'status' => $this->status,
            'subtotal' => (float) $this->subtotal,
            'vat_total' => (float) $this->vat_total,
            'discount_total' => (float) $this->discount_total,
            'total' => (float) $this->total,
            'fiscal_printed_at' => $this->fiscal_printed_at?->toIso8601String(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'void_reason' => $this->void_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'items' => ReceiptItemResource::collection($this->whenLoaded('items')),
            'payments' => PaymentResource::collection($this->whenLoaded('payments')),
        ];
    }
}
