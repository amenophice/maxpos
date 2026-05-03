<?php

namespace App\Filament\Resources\Articles\Tables;

use App\Models\Gestiune;
use App\Models\Group;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')->label('SKU')->searchable()->sortable(),
                TextColumn::make('name')->label('Denumire')->searchable()->sortable(),
                TextColumn::make('group.name')->label('Grupă')->sortable(),
                TextColumn::make('price')->label('Preț')->money('RON')->sortable(),
                TextColumn::make('vat_rate')->label('TVA (%)')->sortable(),
                TextColumn::make('unit')->label('UM'),
                IconColumn::make('is_active')->label('Activ')->boolean(),
                TextColumn::make('barcodes_count')->counts('barcodes')->label('Coduri bare'),
            ])
            ->filters([
                SelectFilter::make('group_id')
                    ->label('Grupă')
                    ->options(fn () => Group::query()->pluck('name', 'id')),
                SelectFilter::make('default_gestiune_id')
                    ->label('Gestiune implicită')
                    ->options(fn () => Gestiune::query()->pluck('name', 'id')),
                TernaryFilter::make('is_active')->label('Activ'),
            ])
            ->searchable()
            ->modifyQueryUsing(function ($query) {
                return $query->when(
                    request('tableSearch'),
                    fn ($q, $search) => $q->orWhereHas(
                        'barcodes',
                        fn ($b) => $b->where('barcode', 'like', "%{$search}%")
                    )
                );
            })
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
