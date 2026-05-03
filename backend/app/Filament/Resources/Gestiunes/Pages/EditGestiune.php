<?php

namespace App\Filament\Resources\Gestiunes\Pages;

use App\Filament\Resources\Gestiunes\GestiuneResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGestiune extends EditRecord
{
    protected static string $resource = GestiuneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
