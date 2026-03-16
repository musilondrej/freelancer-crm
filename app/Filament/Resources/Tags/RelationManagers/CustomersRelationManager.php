<?php

namespace App\Filament\Resources\Tags\RelationManagers;

use App\Enums\CustomerStatus;
use App\Support\CustomerIdentityFields;
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
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CustomersRelationManager extends RelationManager
{
    protected static string $relationship = 'customers';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('legal_name'),
                TextInput::make('registration_number')
                    ->label(CustomerIdentityFields::registrationNumberLabel())
                    ->helperText(CustomerIdentityFields::registrationNumberHelperText()),
                TextInput::make('vat_id')
                    ->label(CustomerIdentityFields::primaryTaxIdLabel())
                    ->helperText(CustomerIdentityFields::primaryTaxIdHelperText()),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('website')
                    ->url(),
                TextInput::make('timezone'),
                TextInput::make('billing_currency'),
                TextInput::make('hourly_rate')
                    ->numeric()
                    ->suffix(fn (Get $get): string => (string) ($get('billing_currency') ?: 'CZK')),
                Select::make('status')
                    ->options(CustomerStatus::class)
                    ->default('lead')
                    ->required(),
                TextInput::make('source'),
                DateTimePicker::make('last_contacted_at'),
                DateTimePicker::make('next_follow_up_at'),
                Textarea::make('internal_summary')
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
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('legal_name')
                    ->searchable(),
                TextColumn::make('registration_number')
                    ->label(CustomerIdentityFields::registrationNumberLabel())
                    ->searchable(),
                TextColumn::make('vat_id')
                    ->label(CustomerIdentityFields::primaryTaxIdLabel())
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('website')
                    ->searchable(),
                TextColumn::make('timezone')
                    ->searchable(),
                TextColumn::make('hourly_rate_with_currency')
                    ->label('Hourly rate')
                    ->sortable(
                        query: fn (Builder $query, string $direction): Builder => $query
                            ->orderBy('hourly_rate', $direction)
                            ->orderBy('billing_currency', $direction),
                    ),
                TextColumn::make('status')
                    ->badge()
                    ->searchable(),
                TextColumn::make('source')
                    ->searchable(),
                TextColumn::make('last_contacted_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('next_follow_up_at')
                    ->dateTime()
                    ->sortable(),
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
