<?php

namespace App\Filament\Resources\Drivers;

use App\Filament\Resources\Drivers\Pages\ManageDrivers;
use App\Models\Driver;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-truck';

    protected static string|\UnitEnum|null $navigationGroup = 'Opérations';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required(),
            TextInput::make('phone')->label('Téléphone')->required(),
            TextInput::make('vehicle_type')->label('Véhicule')->required(),
            TextInput::make('vehicle_plate')->label('Plaque'),
            TextInput::make('rating')->label('Note')->numeric()->minValue(0)->maxValue(5),
            Select::make('status')->label('Statut')->options([
                'available' => 'Disponible',
                'busy' => 'Occupé',
                'offline' => 'Hors ligne',
            ])->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('phone')->label('Téléphone')->searchable(),
                TextColumn::make('vehicle_type')->label('Véhicule'),
                TextColumn::make('rating')->label('Note')->sortable(),
                TextColumn::make('status')->label('Statut')->badge()->sortable(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageDrivers::route('/')];
    }
}
