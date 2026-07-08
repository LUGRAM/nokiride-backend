<?php

namespace App\Filament\Resources\Merchants;

use App\Filament\Resources\Merchants\Pages\ManageMerchants;
use App\Models\Merchant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MerchantResource extends Resource
{
    protected static ?string $model = Merchant::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static string|\UnitEnum|null $navigationGroup = 'Marché';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required()->maxLength(255),
            TextInput::make('category')->label('Catégorie')->required()->maxLength(255),
            TextInput::make('location')->label('Quartier')->required()->maxLength(255),
            TextInput::make('price_range')->label('Prix')->required()->maxLength(20),
            TextInput::make('rating')->label('Note')->numeric()->minValue(0)->maxValue(5),
            TextInput::make('review_count')->label('Avis')->numeric()->minValue(0),
            TextInput::make('delivery_minutes')->label('Minutes livraison')->numeric()->minValue(1),
            TextInput::make('delivery_fee')->label('Frais livraison')->numeric()->minValue(0),
            TextInput::make('emoji')->label('Icône')->maxLength(20),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nom')->searchable()->sortable(),
                TextColumn::make('category')->label('Catégorie')->searchable()->sortable(),
                TextColumn::make('location')->label('Quartier')->sortable(),
                TextColumn::make('rating')->label('Note')->sortable(),
                TextColumn::make('delivery_fee')->label('Livraison')->money('XAF', divideBy: 1)->sortable(),
                IconColumn::make('is_active')->label('Actif')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageMerchants::route('/')];
    }
}
