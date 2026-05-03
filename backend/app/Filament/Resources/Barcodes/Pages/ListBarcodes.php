<?php

namespace App\Filament\Resources\Barcodes\Pages;

use App\Filament\Resources\Barcodes\BarcodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBarcodes extends ListRecords
{
    protected static string $resource = BarcodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
