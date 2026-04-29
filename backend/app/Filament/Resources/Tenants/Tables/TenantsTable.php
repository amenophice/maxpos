<?php

namespace App\Filament\Resources\Tenants\Tables;

use App\Models\Tenant;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class TenantsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Denumire')->searchable()->sortable(),
                TextColumn::make('cui')->label('CUI')->searchable()->sortable(),
                TextColumn::make('status')->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'trial' => 'info',
                        'active' => 'success',
                        'suspended' => 'danger',
                        'rejected' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('registered_at')->label('Înregistrat la')
                    ->dateTime('d.m.Y H:i')->sortable(),
                TextColumn::make('trial_ends_at')->label('Trial expiră')
                    ->dateTime('d.m.Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'pending' => 'În așteptare',
                        'trial' => 'Trial',
                        'active' => 'Activ',
                        'suspended' => 'Suspendat',
                        'rejected' => 'Respins',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                \Filament\Actions\Action::make('approve')
                    ->label('Aprobă')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobă tenant')
                    ->modalDescription('Tenantul va primi o perioadă de trial de 30 de zile.')
                    ->visible(fn (Tenant $record): bool => $record->status === 'pending')
                    ->action(function (Tenant $record): void {
                        $record->status = 'trial';
                        $record->trial_ends_at = now()->addDays(30);
                        $record->save();
                    }),
                \Filament\Actions\Action::make('reject')
                    ->label('Respinge')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Tenant $record): bool => $record->status === 'pending')
                    ->form([
                        \Filament\Forms\Components\Textarea::make('reason')
                            ->label('Motiv respingere')
                            ->required(),
                    ])
                    ->action(function (Tenant $record, array $data): void {
                        $record->status = 'rejected';
                        $record->rejection_reason = $data['reason'];
                        $record->save();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve_selected')
                        ->label('Aprobă selectate')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each(function (Tenant $tenant) {
                            if ($tenant->status === 'pending') {
                                $tenant->status = 'trial';
                                $tenant->trial_ends_at = now()->addDays(30);
                                $tenant->save();
                            }
                        }))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('reject_selected')
                        ->label('Respinge selectate')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->form([
                            \Filament\Forms\Components\Textarea::make('reason')
                                ->label('Motiv respingere')
                                ->required(),
                        ])
                        ->action(fn (Collection $records, array $data) => $records->each(function (Tenant $tenant) use ($data) {
                            if ($tenant->status === 'pending') {
                                $tenant->status = 'rejected';
                                $tenant->rejection_reason = $data['reason'];
                                $tenant->save();
                            }
                        }))
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('registered_at', 'desc');
    }
}
