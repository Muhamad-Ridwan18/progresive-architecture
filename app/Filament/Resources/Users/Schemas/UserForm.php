<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                // Password hanya diisi saat membuat akun. Setelah itu gunakan aksi "Reset Password".
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(10)
                    ->visibleOn('create'),
                FileUpload::make('avatar')
                    ->label('Profile photo')
                    ->avatar()
                    ->image()
                    ->disk('public')
                    ->directory('avatars')
                    ->maxSize(2048),
            ]);
    }
}
