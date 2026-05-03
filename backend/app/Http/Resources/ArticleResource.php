<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'description' => $this->description,
            'group_id' => $this->group_id,
            'default_gestiune_id' => $this->default_gestiune_id,
            'price' => (string) $this->price,
            'vat_rate' => (string) $this->vat_rate,
            'unit' => $this->unit,
            'plu' => $this->plu,
            'is_active' => $this->is_active,
            'barcodes' => $this->whenLoaded('barcodes', fn () => $this->barcodes->map(fn ($b) => [
                'barcode' => $b->barcode,
                'type' => $b->type,
            ])),
            'stock_levels' => $this->whenLoaded('stockLevels', fn () => $this->stockLevels->map(fn ($s) => [
                'gestiune_id' => $s->gestiune_id,
                'quantity' => (string) $s->quantity,
            ])),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
