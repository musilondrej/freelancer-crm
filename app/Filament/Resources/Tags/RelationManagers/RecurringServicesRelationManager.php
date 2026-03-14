<?php

namespace App\Filament\Resources\Tags\RelationManagers;

use App\Enums\RecurringServiceBillingModel;
use App\Enums\RecurringServiceCadenceUnit;
use App\Enums\RecurringServiceStatus;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RecurringServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'recurringServices';

    public function form(Schema $schema): Schema
    {
        $ownerId = Filament::auth()->id();

        return $schema
            ->components([
                Select::make('customer_id')
                    ->relationship('customer', 'name'),
                Select::make('project_id')
                    ->relationship('project', 'name'),
                TextInput::make('name')
                    ->required(),
                Select::make('service_type_id')
                    ->label('Service category')
                    ->relationship(
                        name: 'serviceType',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $ownerId !== null
                            ? $query
                                ->where('owner_id', $ownerId)
                                ->orderBy('name')
                            : $query,
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('billing_model')
                    ->options(RecurringServiceBillingModel::class)
                    ->default('fixed')
                    ->required(),
                TextInput::make('currency'),
                TextInput::make('fixed_amount')
                    ->numeric()
                    ->suffix(fn (Get $get): string => (string) ($get('currency') ?: (data_get(Filament::auth()->user(), 'default_currency', 'CZK')))),
                TextInput::make('hourly_rate')
                    ->numeric()
                    ->suffix(fn (Get $get): string => (string) ($get('currency') ?: (data_get(Filament::auth()->user(), 'default_currency', 'CZK')))),
                TextInput::make('included_quantity')
                    ->numeric(),
                Select::make('cadence_unit')
                    ->options(RecurringServiceCadenceUnit::class)
                    ->default('month')
                    ->required(),
                TextInput::make('cadence_interval')
                    ->required()
                    ->numeric()
                    ->default(1),
                DatePicker::make('starts_on')
                    ->required(),
                DatePicker::make('next_due_on')
                    ->helperText('One-time override. Cadence changes recalculate this date.'),
                DatePicker::make('last_invoiced_on'),
                DatePicker::make('ends_on'),
                Toggle::make('auto_renew')
                    ->required(),
                Select::make('status')
                    ->options(RecurringServiceStatus::class)
                    ->default('active')
                    ->required(),
                CheckboxList::make('remind_days_before')
                    ->options([
                        1 => '1 day',
                        3 => '3 days',
                        7 => '7 days',
                        14 => '14 days',
                        30 => '30 days',
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Textarea::make('notes')
                    ->columnSpanFull(),
                KeyValue::make('meta')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('customer.name')
                    ->searchable(),
                TextColumn::make('project.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('serviceType.name')
                    ->label('Service category')
                    ->badge()
                    ->searchable(),
                TextColumn::make('billing_model')
                    ->badge()
                    ->searchable(),
                TextColumn::make('fixed_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('hourly_rate_with_currency')
                    ->label('Hourly rate')
                    ->sortable(
                        query: fn (Builder $query, string $direction): Builder => $query
                            ->orderBy('hourly_rate', $direction)
                            ->orderBy('currency', $direction),
                    ),
                TextColumn::make('included_quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cadence_unit')
                    ->badge()
                    ->searchable(),
                TextColumn::make('cadence_interval')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('starts_on')
                    ->date()
                    ->sortable(),
                TextColumn::make('next_due_on')
                    ->date()
                    ->sortable(),
                TextColumn::make('last_invoiced_on')
                    ->date()
                    ->sortable(),
                TextColumn::make('ends_on')
                    ->date()
                    ->sortable(),
                IconColumn::make('auto_renew')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make(),
                AttachAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DetachAction::make(),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withoutGlobalScopes([
                    SoftDeletingScope::class,
                ]));
    }
}
