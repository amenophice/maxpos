<?php

namespace App\Filament\Resources\StockLevels\Tables;

use App\Models\Gestiune;
use App\Models\Group;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockLevelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('article.sku')->label('SKU')->searchable()->sortable(),
                TextColumn::make('article.name')->label('Articol')->searchable()->sortable(),
                TextColumn::make('gestiune.name')->label('Gestiune')->searchable()->sortable(),
                TextColumn::make('quantity')->label('Cantitate')->numeric(3)->sortable(),
                TextColumn::make('updated_at')->label('Actualizat')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('gestiune_id')
                    ->label('Gestiune')
                    ->options(fn () => Gestiune::query()->pluck('name', 'id')),
                SelectFilter::make('article.group_id')
                    ->label('Grupă articol')
                    ->options(fn () => Group::query()->pluck('name', 'id'))
                    ->query(function ($query, array $data) {
                        return $query->when(
                            $data['value'] ?? null,
                            fn ($q, $groupId) => $q->whereHas('article', fn ($a) => $a->where('group_id', $groupId))
                        );
                    }),
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
