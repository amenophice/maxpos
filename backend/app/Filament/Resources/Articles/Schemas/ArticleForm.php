<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Http\Requests\ArticleRequest;
use App\Models\Gestiune;
use App\Models\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        $rules = ArticleRequest::fieldRules();

        return $schema->components([
            Section::make('Identificare')
                ->schema([
                    TextInput::make('sku')->label('SKU')->required()->rules($rules['sku']),
                    TextInput::make('name')->label('Denumire')->required()->rules($rules['name']),
                    TextInput::make('plu')->label('PLU (cântar electronic)')->rules($rules['plu']),
                    Textarea::make('description')->label('Descriere')->rows(3)->rules($rules['description'])->columnSpanFull(),
                ])->columns(3),
            Section::make('Clasificare')
                ->schema([
                    Select::make('group_id')
                        ->label('Grupă')
                        ->options(fn () => Group::query()->pluck('name', 'id'))
                        ->searchable()
                        ->rules($rules['group_id']),
                    Select::make('default_gestiune_id')
                        ->label('Gestiune implicită')
                        ->options(fn () => Gestiune::query()->pluck('name', 'id'))
                        ->searchable()
                        ->rules($rules['default_gestiune_id']),
                ])->columns(2),
            Section::make('Preț și TVA')
                ->schema([
                    TextInput::make('price')->label('Preț')->numeric()->required()->rules($rules['price']),
                    TextInput::make('vat_rate')->label('Cotă TVA (%)')->numeric()->default(19)->required()->rules($rules['vat_rate']),
                    TextInput::make('unit')->label('Unitate măsură')->default('buc')->required()->rules($rules['unit']),
                ])->columns(3),
            Section::make('Stare')
                ->schema([
                    Toggle::make('is_active')->label('Activ')->default(true)->rules($rules['is_active']),
                    TextInput::make('photo_path')->label('Cale imagine')->rules($rules['photo_path']),
                ])->columns(2),
        ]);
    }
}
