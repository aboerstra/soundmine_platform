<?php

namespace App\Filament\Resources\Licenses;

use App\Domain\Licensing\Actions\ChangeLicenseStatus;
use App\Domain\Licensing\Enums\LicenseStatus;
use App\Domain\Licensing\Models\License;
use App\Filament\Resources\Licenses\Pages\ManageLicenses;
use App\Filament\Resources\Licenses\Pages\ViewLicense;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;

class LicenseResource extends Resource
{
    protected static ?string $model = License::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('customer_id')->relationship('customer', 'display_name')->searchable()->required(),
            Select::make('product_id')->relationship('product', 'name', modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true))->searchable()->required(),
            TextInput::make('max_activations')->numeric()->minValue(1)->maxValue(100)->default(2)->required(),
            DateTimePicker::make('starts_at')->timezone('UTC')->default(now())->required(),
            DateTimePicker::make('expires_at')->timezone('UTC')->after('starts_at'),
            Repeater::make('entitlements')->schema([
                TextInput::make('key')->required()->maxLength(100)->regex('/^[a-z][a-z0-9_.-]*$/'),
                TextInput::make('value')->required()->maxLength(1000),
            ])->defaultItems(0)->maxItems(50),
            Textarea::make('reason')->required()->maxLength(1000),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('id')->label('License reference'), TextEntry::make('customer.display_name'),
            TextEntry::make('product.name'), TextEntry::make('status')->formatStateUsing(fn (LicenseStatus $state): string => $state->value),
            TextEntry::make('starts_at')->dateTime(), TextEntry::make('expires_at')->dateTime()->placeholder('No fixed expiry'),
            TextEntry::make('max_activations'),
            RepeatableEntry::make('entitlements')->schema([
                TextEntry::make('key'), TextEntry::make('value')->formatStateUsing(fn ($state): string => is_string($state) ? $state : json_encode($state)),
            ]),
            RepeatableEntry::make('events')->label('License history')->schema([
                TextEntry::make('created_at')->dateTime(), TextEntry::make('actor.name'),
                TextEntry::make('event'), TextEntry::make('from_status'), TextEntry::make('to_status'), TextEntry::make('reason'),
            ])->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        $actions = [ViewAction::make()->url(fn (License $record): string => self::getUrl('view', ['record' => $record]))];
        foreach (['suspend' => LicenseStatus::Suspended, 'resume' => LicenseStatus::Active, 'revoke' => LicenseStatus::Revoked] as $ability => $target) {
            $actions[] = Action::make($ability)->requiresConfirmation()
                ->authorize(fn (License $record): bool => Gate::allows($ability, $record))
                ->visible(fn (License $record): bool => match ($target) {
                    LicenseStatus::Suspended => $record->status === LicenseStatus::Active,
                    LicenseStatus::Active => $record->status === LicenseStatus::Suspended,
                    LicenseStatus::Revoked => $record->status !== LicenseStatus::Revoked,
                    default => false,
                })
                ->schema([Textarea::make('reason')->required()->maxLength(1000)])
                ->action(fn (License $record, array $data, ChangeLicenseStatus $change) => $change->handle(auth()->user(), $record, $target, $data['reason']));
        }

        return $table->columns([
            TextColumn::make('customer.display_name')->searchable(), TextColumn::make('product.name')->searchable(),
            TextColumn::make('status')->formatStateUsing(fn (LicenseStatus $state): string => $state->value)->badge(),
            TextColumn::make('expires_at')->dateTime()->placeholder('No fixed expiry'),
        ])->defaultSort('created_at', 'desc')->recordActions($actions);
    }

    public static function getPages(): array
    {
        return ['index' => ManageLicenses::route('/'), 'view' => ViewLicense::route('/{record}')];
    }
}
