<?php

namespace App\Filament\Resources\Tenants\Schemas;

use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TenantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Denumire')->required(),
                TextInput::make('cui')->label('CUI'),
                Placeholder::make('status')->label('Status')
                    ->content(fn ($record) => $record?->status ?? '-'),
                Placeholder::make('registered_at')->label('Înregistrat la')
                    ->content(fn ($record) => $record?->registered_at?->format('d.m.Y H:i') ?? '-'),
                Placeholder::make('trial_ends_at')->label('Trial expiră')
                    ->content(fn ($record) => $record?->trial_ends_at?->format('d.m.Y') ?? '-'),
                Placeholder::make('rejection_reason')->label('Motiv respingere')
                    ->content(fn ($record) => $record?->rejection_reason ?? '-')
                    ->visible(fn ($record) => $record?->status === 'rejected'),
            ]);
    }
}
