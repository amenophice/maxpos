<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReceiptItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $vatRate = (float) $this->vat_rate;
        $unitPriceIncVat = (float) $this->unit_price;
        $unitPriceExVat = $vatRate > 0
            ? $unitPriceIncVat / (1 + $vatRate / 100)
            : $unitPriceIncVat;
        $lineTotalExVat = (float) $this->line_subtotal;
        $lineTotalIncVat = (float) $this->line_total;

        return [
            'id' => $this->id,
            'article_id' => $this->article_id,
            'gestiune_id' => $this->gestiune_id,
            'name' => $this->article_name_snapshot,
            'sku' => $this->sku_snapshot,
            'quantity' => (float) $this->quantity,
            'unit_price_ex_vat' => $unitPriceExVat,
            'unit_price_inc_vat' => $unitPriceIncVat,
            'unit' => $this->relationLoaded('article') ? ($this->article?->unit ?? 'buc') : 'buc',
            'vat_rate' => $vatRate,
            'discount_amount' => (float) $this->discount_amount,
            'line_total_ex_vat' => $lineTotalExVat,
            'line_vat' => $lineTotalIncVat - $lineTotalExVat,
            'line_total_inc_vat' => $lineTotalIncVat,
        ];
    }
}
