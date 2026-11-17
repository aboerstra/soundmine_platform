<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Domain\Customers\Actions\InviteCustomer;
use App\Domain\Customers\Models\Customer;
use App\Filament\Resources\Customers\CustomerResource;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Gate;

class ManageCustomers extends ManageRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('invite')->label('Invite customer')
            ->authorize(fn (): bool => Gate::allows('create', Customer::class))
            ->schema([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')->email()->required()->maxLength(255),
            ])->action(function (array $data, InviteCustomer $invite): void {
                $invite->handle(auth()->user(), $data);
                Notification::make()->title('Invitation sent')->success()->send();
            })];
    }
}
