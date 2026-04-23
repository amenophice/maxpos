<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nume')->searchable()->sortable(),
                TextColumn::make('cui')->label('CUI')->searchable(),
                TextColumn::make('city')->label('Oraș')->sortable(),
                TextColumn::make('county')->label('Județ')->sortable(),
                IconColumn::make('is_company')->label('Juridică')->boolean(),
                TextColumn::make('email')->label('E-mail')->searchable(),
                TextColumn::make('phone')->label('Telefon'),
            ])
            ->filters([
                TernaryFilter::make('is_company')->label('Persoană juridică'),
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
