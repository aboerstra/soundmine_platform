<?php

namespace App\Filament\Resources\Licenses\Pages;

use App\Domain\Licensing\Actions\GrantManualLicense;
use App\Filament\Resources\Licenses\LicenseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;

class ManageLicenses extends ManageRecords
{
    protected static string $resource = LicenseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Grant license')
            ->using(fn (array $data, GrantManualLicense $grant): Model => $grant->handle(auth()->user(), $data))];
    }
}
