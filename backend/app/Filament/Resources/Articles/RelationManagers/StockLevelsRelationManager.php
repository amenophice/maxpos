<?php

namespace App\Filament\Resources\Articles\RelationManagers;

use App\Http\Requests\StockLevelRequest;
use App\Models\Gestiune;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StockLevelsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockLevels';

    protected static ?string $title = 'Stocuri';

    public function form(Schema $schema): Schema
    {
        $rules = StockLevelRequest::fieldRules();

        return $schema->components([
            Select::make('gestiune_id')
                ->label('Gestiune')
                ->options(fn () => Gestiune::query()->pluck('name', 'id'))
                ->searchable()
                ->required()
                ->rules($rules['gestiune_id']),
            TextInput::make('quantity')->label('Cantitate')->numeric()->required()->rules($rules['quantity']),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('quantity')
            ->columns([
                TextColumn::make('gestiune.name')->label('Gestiune')->sortable(),
                TextColumn::make('quantity')->label('Cantitate')->numeric(3)->sortable(),
                TextColumn::make('updated_at')->label('Actualizat')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
