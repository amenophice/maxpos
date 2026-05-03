<?php

namespace App\Filament\Resources\Locations\Schemas;

use App\Http\Requests\LocationRequest;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        $rules = LocationRequest::fieldRules();

        return $schema->components([
            Section::make('Detalii locație')
                ->schema([
                    TextInput::make('name')->label('Denumire')->required()->rules($rules['name']),
                    TextInput::make('city')->label('Oraș')->required()->rules($rules['city']),
                    TextInput::make('county')->label('Județ')->required()->rules($rules['county']),
                    Textarea::make('address')->label('Adresă')->rows(3)->rules($rules['address']),
                ])->columns(2),
            Section::make('Setări')
                ->schema([
                    Toggle::make('is_active')->label('Activ')->default(true)->rules($rules['is_active']),
                    TextInput::make('saga_agent_token')->label('Token agent Saga')->rules($rules['saga_agent_token']),
                    TagsInput::make('scale_barcode_prefixes')
                        ->label('Prefixe coduri cântar (EAN-13)')
                        ->helperText('Două cifre fiecare. Lasă gol pentru valorile implicite (26, 27, 28, 29).')
                        ->placeholder('26')
                        ->splitKeys([',', ' '])
                        ->rules($rules['scale_barcode_prefixes']),
                ])->columns(2),
        ]);
    }
}
