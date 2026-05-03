<?php

namespace App\Filament\Resources\Gestiunes\Pages;

use App\Filament\Resources\Gestiunes\GestiuneResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGestiunes extends ListRecords
{
    protected static string $resource = GestiuneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
