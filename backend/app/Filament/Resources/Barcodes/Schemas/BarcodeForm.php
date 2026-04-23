<?php

namespace App\Filament\Resources\Barcodes\Schemas;

use App\Http\Requests\BarcodeRequest;
use App\Models\Article;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BarcodeForm
{
    public static function configure(Schema $schema): Schema
    {
        $rules = BarcodeRequest::fieldRules();

        return $schema->components([
            Select::make('article_id')
                ->label('Articol')
                ->options(fn () => Article::query()->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->rules($rules['article_id']),
            TextInput::make('barcode')->label('Cod')->required()->rules($rules['barcode']),
            Select::make('type')
                ->label('Tip')
                ->options([
                    'ean13' => 'EAN-13',
                    'ean8' => 'EAN-8',
                    'code128' => 'Code 128',
                    'internal' => 'Intern',
                    'scale' => 'Cântar',
                ])
                ->required()
                ->rules($rules['type']),
        ]);
    }
}
