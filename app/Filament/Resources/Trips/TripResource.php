<?php

namespace App\Filament\Resources\Trips;

use App\Filament\Resources\Trips\Pages\ManageTrips;
use App\Models\Trip;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TripResource extends Resource
{
    protected static ?string $model = Trip::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static string|\UnitEnum|null $navigationGroup = 'Opérations';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label('Client')->relationship('user', 'name')->required()->searchable(),
            Select::make('driver_id')->label('Chauffeur')->relationship('driver', 'name')->searchable(),
            TextInput::make('pickup_address')->label('Départ')->required(),
            TextInput::make('dropoff_address')->label('Arrivée')->required(),
            Select::make('service_type')->label('Service')->options(['eco' => 'Eco', 'premium' => 'Premium'])->required(),
            TextInput::make('distance_km')->label('Km')->numeric()->required(),
            TextInput::make('price_fcfa')->label('Prix FCFA')->numeric()->required(),
            TextInput::make('estimated_minutes')->label('Minutes')->numeric()->required(),
            Select::make('status')->label('Statut')->options([
                'estimating' => 'Estimation',
                'searching' => 'Recherche',
                'assigned' => 'Assignée',
                'in_progress' => 'En cours',
                'completed' => 'Terminée',
                'cancelled' => 'Annulée',
            ])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->label('Référence')->searchable(),
                TextColumn::make('user.name')->label('Client')->searchable(),
                TextColumn::make('driver.name')->label('Chauffeur')->placeholder('Non assigné'),
                TextColumn::make('price_fcfa')->label('Prix')->money('XAF', divideBy: 1)->sortable(),
                TextColumn::make('status')->label('Statut')->badge()->sortable(),
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTrips::route('/')];
    }
}
