<?php

namespace App\Filament\Resources\Gestiunes\Tables;

use App\Models\Location;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class GestiunesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Denumire')->searchable()->sortable(),
                TextColumn::make('location.name')->label('Locație')->searchable()->sortable(),
                TextColumn::make('type')->label('Tip')->badge()->sortable(),
                IconColumn::make('is_active')->label('Activă')->boolean(),
            ])
            ->filters([
                SelectFilter::make('location_id')
                    ->label('Locație')
                    ->options(fn () => Location::query()->pluck('name', 'id')),
                SelectFilter::make('type')->label('Tip')->options([
                    'global-valoric' => 'Global-valoric',
                    'cantitativ-valoric' => 'Cantitativ-valoric',
                ]),
                TernaryFilter::make('is_active')->label('Activă'),
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
