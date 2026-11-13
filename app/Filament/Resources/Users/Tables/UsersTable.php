<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime('d M Y H:i')
                    ->placeholder('Never')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
            ])
            ->recordActions([
                EditAction::make(),
                self::resetPasswordAction(),
                self::deactivateAction(),
                self::reactivateAction(),
            ]);
    }

    private static function resetPasswordAction(): Action
    {
        return Action::make('resetPassword')
            ->label('Reset Password')
            ->icon('heroicon-o-key')
            ->color('gray')
            ->modalHeading(fn (User $record): string => "Reset password: {$record->name}")
            ->schema([
                TextInput::make('password')
                    ->label('New password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(10),
            ])
            ->action(function (User $record, array $data): void {
                $record->update(['password' => $data['password']]);

                Notification::make()
                    ->title('Password has been reset')
                    ->success()
                    ->send();
            });
    }

    private static function deactivateAction(): Action
    {
        return Action::make('deactivate')
            ->label('Deactivate')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (User $record): bool => $record->is_active)
            ->requiresConfirmation()
            ->modalDescription('This user will no longer be able to sign in.')
            ->action(function (User $record): void {
                // SRS FR-US-010: administrator aktif terakhir tidak boleh dinonaktifkan.
                if ($record->otherActiveAdminsCount() === 0) {
                    Notification::make()
                        ->title('Cannot deactivate the last active administrator')
                        ->danger()
                        ->send();

                    return;
                }

                $record->update(['is_active' => false]);

                Notification::make()->title('User deactivated')->success()->send();
            });
    }

    private static function reactivateAction(): Action
    {
        return Action::make('reactivate')
            ->label('Reactivate')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (User $record): bool => ! $record->is_active)
            ->requiresConfirmation()
            ->action(function (User $record): void {
                $record->update(['is_active' => true]);

                Notification::make()->title('User reactivated')->success()->send();
            });
    }
}
