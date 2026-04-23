<?php

namespace App\Filament\Resources\Gestiunes;

use App\Filament\Resources\Gestiunes\Pages\CreateGestiune;
use App\Filament\Resources\Gestiunes\Pages\EditGestiune;
use App\Filament\Resources\Gestiunes\Pages\ListGestiunes;
use App\Filament\Resources\Gestiunes\Schemas\GestiuneForm;
use App\Filament\Resources\Gestiunes\Tables\GestiunesTable;
use App\Models\Gestiune;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class GestiuneResource extends Resource
{
    protected static ?string $model = Gestiune::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|\UnitEnum|null $navigationGroup = 'Nomenclatoare';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Gestiune';

    protected static ?string $pluralModelLabel = 'Gestiuni';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return GestiuneForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GestiunesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListGestiunes::route('/'),
            'create' => CreateGestiune::route('/create'),
            'edit' => EditGestiune::route('/{record}/edit'),
        ];
    }
}
