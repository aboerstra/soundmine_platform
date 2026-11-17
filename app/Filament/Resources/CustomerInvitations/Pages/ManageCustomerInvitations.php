<?php

namespace App\Filament\Resources\CustomerInvitations\Pages;

use App\Filament\Resources\CustomerInvitations\CustomerInvitationResource;
use Filament\Resources\Pages\ManageRecords;

class ManageCustomerInvitations extends ManageRecords
{
    protected static string $resource = CustomerInvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
