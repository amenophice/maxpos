<?php

namespace App\Filament\Resources\Groups\Tables;

use App\Models\Group;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Denumire')->searchable()->sortable(),
                TextColumn::make('parent.name')->label('Grupă părinte')->sortable(),
                TextColumn::make('display_order')->label('Ordine')->sortable(),
                TextColumn::make('articles_count')->counts('articles')->label('Nr. articole'),
            ])
            ->defaultSort('display_order')
            ->filters([
                SelectFilter::make('parent_id')
                    ->label('Grupă părinte')
                    ->options(fn () => Group::query()->pluck('name', 'id')),
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
