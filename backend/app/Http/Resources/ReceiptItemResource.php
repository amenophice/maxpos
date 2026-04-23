<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'article_id' => $this->article_id,
            'gestiune_id' => $this->gestiune_id,
            'article_name_snapshot' => $this->article_name_snapshot,
            'sku_snapshot' => $this->sku_snapshot,
            'quantity' => (string) $this->quantity,
            'unit_price' => (string) $this->unit_price,
            'vat_rate' => (string) $this->vat_rate,
            'discount_amount' => (string) $this->discount_amount,
            'line_subtotal' => (string) $this->line_subtotal,
            'line_vat' => (string) $this->line_vat,
            'line_total' => (string) $this->line_total,
        ];
    }
}
