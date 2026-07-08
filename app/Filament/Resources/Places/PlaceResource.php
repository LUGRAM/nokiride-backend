<?php

namespace App\Filament\Resources\Places;

use App\Filament\Resources\Places\Pages\ManagePlaces;
use App\Models\Place;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PlaceResource extends Resource
{
    protected static ?string $model = Place::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|\UnitEnum|null $navigationGroup = 'Référentiel';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required(),
            TextInput::make('address')->label('Adresse')->required(),
            TextInput::make('latitude')->label('Latitude')->numeric()->required(),
            TextInput::make('longitude')->label('Longitude')->numeric()->required(),
            Toggle::make('is_active')->label('Actif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('address')->label('Adresse')->searchable(),
                TextColumn::make('latitude')->label('Lat.'),
                TextColumn::make('longitude')->label('Lng.'),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManagePlaces::route('/')];
    }
}
