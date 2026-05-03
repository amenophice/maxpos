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
            'name' => $this->article_name_snapshot,
            'sku' => $this->sku_snapshot,
            'quantity' => (float) $this->quantity,
            'unit_price' => (float) $this->unit_price,
            'unit' => $this->relationLoaded('article') ? ($this->article?->unit ?? 'buc') : 'buc',
            'vat_rate' => (float) $this->vat_rate,
            'discount_amount' => (float) $this->discount_amount,
            'line_subtotal' => (float) $this->line_subtotal,
            'line_vat' => (float) $this->line_vat,
            'line_total' => (float) $this->line_total,
        ];
    }
}
