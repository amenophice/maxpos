<?php

namespace App\Filament\Resources\Barcodes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BarcodesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('barcode')->label('Cod')->searchable()->sortable(),
                TextColumn::make('article.name')->label('Articol')->searchable()->sortable(),
                TextColumn::make('article.sku')->label('SKU')->searchable(),
                TextColumn::make('type')->label('Tip')->badge()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tip')->options([
                    'ean13' => 'EAN-13',
                    'ean8' => 'EAN-8',
                    'code128' => 'Code 128',
                    'internal' => 'Intern',
                    'scale' => 'Cântar',
                ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
