<?php

namespace App\Filament\Resources\Customers;

use App\Domain\Customers\Models\Customer;
use App\Filament\Resources\Customers\Pages\ManageCustomers;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomerResource extends Resource
{
    protected static ?string $model = Customer::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('display_name')->required()->maxLength(255)]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('display_name')->searchable(),
            TextColumn::make('user.email')->searchable(),
            TextColumn::make('user.email_verified_at')->dateTime()->placeholder('Awaiting verification'),
            TextColumn::make('created_at')->dateTime(),
        ])->defaultSort('created_at', 'desc')->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCustomers::route('/')];
    }
}
