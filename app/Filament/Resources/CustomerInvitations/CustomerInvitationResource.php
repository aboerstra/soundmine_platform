<?php

namespace App\Filament\Resources\CustomerInvitations;

use App\Domain\Customers\Actions\InviteCustomer;
use App\Domain\Customers\Models\CustomerInvitation;
use App\Filament\Resources\CustomerInvitations\Pages\ManageCustomerInvitations;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class CustomerInvitationResource extends Resource
{
    protected static ?string $model = CustomerInvitation::class;

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable(), TextColumn::make('email')->searchable(),
            TextColumn::make('expires_at')->dateTime(),
            TextColumn::make('accepted_at')->dateTime()->placeholder('Not accepted'),
        ])->defaultSort('created_at', 'desc')->recordActions([
            Action::make('resend')->label('Reissue invitation')->requiresConfirmation()
                ->modalDescription('The previous invitation link will stop working.')
                ->authorize(fn (CustomerInvitation $record): bool => Gate::allows('resend', $record))
                ->action(function (CustomerInvitation $record, InviteCustomer $invite): void {
                    Gate::authorize('resend', $record);
                    $invite->handle(auth()->user(), ['email' => $record->email, 'name' => $record->name]);
                    Notification::make()->title('Invitation reissued')->success()->send();
                }),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCustomerInvitations::route('/')];
    }
}
