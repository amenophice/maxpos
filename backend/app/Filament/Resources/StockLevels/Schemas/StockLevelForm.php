<?php

namespace App\Filament\Resources\StockLevels\Schemas;

use App\Http\Requests\StockLevelRequest;
use App\Models\Article;
use App\Models\Gestiune;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StockLevelForm
{
    public static function configure(Schema $schema): Schema
    {
        $rules = StockLevelRequest::fieldRules();

        return $schema->components([
            Select::make('article_id')
                ->label('Articol')
                ->options(fn () => Article::query()->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->rules($rules['article_id']),
            Select::make('gestiune_id')
                ->label('Gestiune')
                ->options(fn () => Gestiune::query()->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->rules($rules['gestiune_id']),
            TextInput::make('quantity')
                ->label('Cantitate')
                ->numeric()
                ->required()
                ->rules($rules['quantity']),
        ]);
    }
}
