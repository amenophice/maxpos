<?php

namespace App\Filament\Resources\Articles\RelationManagers;

use App\Http\Requests\BarcodeRequest;
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

class BarcodesRelationManager extends RelationManager
{
    protected static string $relationship = 'barcodes';

    protected static ?string $title = 'Coduri bare';

    public function form(Schema $schema): Schema
    {
        $rules = BarcodeRequest::fieldRules();

        return $schema->components([
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

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('barcode')
            ->columns([
                TextColumn::make('barcode')->label('Cod')->searchable(),
                TextColumn::make('type')->label('Tip')->badge(),
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
