<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\ManageProducts;
use App\Models\Product;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|\UnitEnum|null $navigationGroup = 'Marché';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('merchant_id')->label('Marchand')->relationship('merchant', 'name')->required()->searchable(),
            TextInput::make('name')->label('Nom')->required()->maxLength(255),
            Textarea::make('description')->label('Description')->columnSpanFull(),
            TextInput::make('price')->label('Prix FCFA')->numeric()->required()->minValue(0),
            TextInput::make('emoji')->label('Icône')->maxLength(20),
            Toggle::make('is_available')->label('Disponible')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('merchant.name')->label('Marchand')->searchable()->sortable(),
                TextColumn::make('name')->label('Produit')->searchable()->sortable(),
                TextColumn::make('price')->label('Prix')->money('XAF', divideBy: 1)->sortable(),
                IconColumn::make('is_available')->label('Disponible')->boolean(),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageProducts::route('/')];
    }
}
