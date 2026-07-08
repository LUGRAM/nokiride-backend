<?php

namespace App\Filament\Resources\WalletTransactions;

use App\Filament\Resources\WalletTransactions\Pages\ManageWalletTransactions;
use App\Models\WalletTransaction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WalletTransactionResource extends Resource
{
    protected static ?string $model = WalletTransaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';

    protected static string|\UnitEnum|null $navigationGroup = 'Finance';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label('Client')->relationship('user', 'name')->required()->searchable(),
            TextInput::make('reference')->label('Référence')->required(),
            TextInput::make('label')->label('Libellé')->required(),
            Select::make('type')->label('Type')->options(['credit' => 'Crédit', 'debit' => 'Débit'])->required(),
            Select::make('method')->label('Méthode')->options([
                'noki_pay' => 'Noki Pay',
                'airtel_money' => 'Airtel Money',
                'moov_money' => 'Moov Money',
                'card' => 'Carte',
            ])->required(),
            TextInput::make('amount_fcfa')->label('Montant FCFA')->numeric()->required(),
            Select::make('status')->label('Statut')->options(['pending' => 'En attente', 'completed' => 'Terminé', 'failed' => 'Échec'])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->label('Référence')->searchable(),
                TextColumn::make('user.name')->label('Client')->searchable(),
                TextColumn::make('label')->label('Libellé')->searchable(),
                TextColumn::make('type')->label('Type')->badge(),
                TextColumn::make('amount_fcfa')->label('Montant')->money('XAF', divideBy: 1)->sortable(),
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageWalletTransactions::route('/')];
    }
}
