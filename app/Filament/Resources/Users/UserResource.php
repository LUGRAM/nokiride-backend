<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|\UnitEnum|null $navigationGroup = 'Référentiel';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required(),
            TextInput::make('phone')->label('Téléphone')->required(),
            TextInput::make('email')->label('Email')->email(),
            Select::make('role')->label('Rôle')->options([
                'customer' => 'Client',
                'driver' => 'Chauffeur',
                'admin' => 'Admin',
            ])->required(),
            TextInput::make('wallet_balance')->label('Solde FCFA')->numeric()->required(),
            TextInput::make('password')->label('Mot de passe')->password()->dehydrated(fn (?string $state): bool => filled($state)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('phone')->label('Téléphone')->searchable(),
                TextColumn::make('role')->label('Rôle')->badge(),
                TextColumn::make('wallet_balance')->label('Solde')->money('XAF', divideBy: 1)->sortable(),
                TextColumn::make('created_at')->label('Inscription')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageUsers::route('/')];
    }
}
