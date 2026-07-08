<?php

namespace App\Filament\Resources\Merchants\Pages;

use App\Filament\Resources\Merchants\MerchantResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageMerchants extends ManageRecords
{
    protected static string $resource = MerchantResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
