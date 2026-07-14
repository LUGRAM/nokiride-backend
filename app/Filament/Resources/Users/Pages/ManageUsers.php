<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ManageUsers extends ManageRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('Tous les comptes'),
            'customers' => Tab::make('Clients')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('role', 'customer')),
            'drivers' => Tab::make('Chauffeurs')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('role', 'driver')),
            'admins' => Tab::make('Administrateurs')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('role', 'admin')),
        ];
    }
}
