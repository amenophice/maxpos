<?php

namespace App\Filament\Resources\Groups\Schemas;

use App\Http\Requests\GroupRequest;
use App\Models\Group;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GroupForm
{
    public static function configure(Schema $schema): Schema
    {
        $rules = GroupRequest::fieldRules();

        return $schema->components([
            TextInput::make('name')->label('Denumire')->required()->rules($rules['name']),
            Select::make('parent_id')
                ->label('Grupă părinte')
                ->options(fn () => Group::query()->pluck('name', 'id'))
                ->searchable()
                ->rules($rules['parent_id']),
            TextInput::make('display_order')
                ->label('Ordine afișare')
                ->numeric()
                ->default(0)
                ->rules($rules['display_order']),
        ]);
    }
}
