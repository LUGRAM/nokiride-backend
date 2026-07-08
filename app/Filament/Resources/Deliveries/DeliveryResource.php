<?php

namespace App\Filament\Resources\Deliveries;

use App\Filament\Resources\Deliveries\Pages\ManageDeliveries;
use App\Models\Delivery;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeliveryResource extends Resource
{
    protected static ?string $model = Delivery::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|\UnitEnum|null $navigationGroup = 'Opérations';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label('Client')->relationship('user', 'name')->required()->searchable(),
            Select::make('driver_id')->label('Coursier')->relationship('driver', 'name')->searchable(),
            TextInput::make('pickup_address')->label('Collecte')->required(),
            TextInput::make('dropoff_address')->label('Livraison')->required(),
            TextInput::make('recipient_name')->label('Destinataire')->required(),
            TextInput::make('recipient_phone')->label('Téléphone destinataire')->required(),
            Select::make('parcel_size')->label('Colis')->options(['small' => 'Petit', 'medium' => 'Moyen', 'large' => 'Grand'])->required(),
            Textarea::make('parcel_note')->label('Note')->columnSpanFull(),
            TextInput::make('distance_km')->label('Km')->numeric()->required(),
            TextInput::make('price_fcfa')->label('Prix FCFA')->numeric()->required(),
            TextInput::make('estimated_minutes')->label('Minutes')->numeric()->required(),
            Select::make('status')->label('Statut')->options([
                'estimating' => 'Estimation',
                'searching' => 'Recherche',
                'assigned' => 'Assignée',
                'in_progress' => 'En cours',
                'delivered' => 'Livrée',
                'cancelled' => 'Annulée',
            ])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')->label('Référence')->searchable(),
                TextColumn::make('recipient_name')->label('Destinataire')->searchable(),
                TextColumn::make('driver.name')->label('Coursier')->placeholder('Non assigné'),
                TextColumn::make('price_fcfa')->label('Prix')->money('XAF', divideBy: 1)->sortable(),
                TextColumn::make('status')->label('Statut')->badge()->sortable(),
                TextColumn::make('created_at')->label('Date')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDeliveries::route('/')];
    }
}
