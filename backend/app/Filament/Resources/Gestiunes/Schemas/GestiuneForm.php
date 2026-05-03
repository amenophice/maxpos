<?php

namespace App\Filament\Resources\Gestiunes\Schemas;

use App\Http\Requests\GestiuneRequest;
use App\Models\Location;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class GestiuneForm
{
    public static function configure(Schema $schema): Schema
    {
        $rules = GestiuneRequest::fieldRules();

        return $schema->components([
            Select::make('location_id')
                ->label('Locație')
                ->options(fn () => Location::query()->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->rules($rules['location_id']),
            TextInput::make('name')->label('Denumire')->required()->rules($rules['name']),
            Select::make('type')
                ->label('Tip gestiune')
                ->options([
                    'global-valoric' => 'Global-valoric',
                    'cantitativ-valoric' => 'Cantitativ-valoric',
                ])
                ->required()
                ->rules($rules['type']),
            Toggle::make('is_active')->label('Activă')->default(true)->rules($rules['is_active']),
        ]);
    }
}
