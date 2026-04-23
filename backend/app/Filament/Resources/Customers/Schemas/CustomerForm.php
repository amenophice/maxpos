<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Http\Requests\CustomerRequest;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        $rules = CustomerRequest::fieldRules();

        return $schema->components([
            Section::make('Date de identificare')
                ->schema([
                    Toggle::make('is_company')
                        ->label('Persoană juridică')
                        ->live()
                        ->default(false)
                        ->rules($rules['is_company']),
                    TextInput::make('name')->label('Nume')->required()->rules($rules['name']),
                    TextInput::make('cui')
                        ->label('CUI')
                        ->visible(fn (Get $get): bool => (bool) $get('is_company'))
                        ->rules($rules['cui']),
                    TextInput::make('registration_number')
                        ->label('Nr. registrul comerțului')
                        ->visible(fn (Get $get): bool => (bool) $get('is_company'))
                        ->rules($rules['registration_number']),
                ])->columns(2),
            Section::make('Adresă')
                ->schema([
                    Textarea::make('address')->label('Adresă')->rows(3)->rules($rules['address'])->columnSpanFull(),
                    TextInput::make('city')->label('Oraș')->rules($rules['city']),
                    TextInput::make('county')->label('Județ')->rules($rules['county']),
                ])->columns(2),
            Section::make('Contact')
                ->schema([
                    TextInput::make('email')->label('E-mail')->email()->rules($rules['email']),
                    TextInput::make('phone')->label('Telefon')->rules($rules['phone']),
                ])->columns(2),
        ]);
    }
}
