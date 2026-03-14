<?php

namespace App\Filament\Resources\Tags\RelationManagers;

use App\Enums\ProjectPipelineStage;
use App\Enums\ProjectPricingModel;
use App\Models\Project;
use App\Models\ProjectStatusOption;
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
use Filament\Forms\Components\DatePicker;
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

class ProjectsRelationManager extends RelationManager
{
    protected static string $relationship = 'projects';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('client_id')
                    ->required()
                    ->numeric(),
                Select::make('primary_contact_id')
                    ->relationship('primaryContact', 'id'),
                TextInput::make('name')
                    ->required(),
                Select::make('status')
                    ->options(fn (): array => ProjectStatusOption::optionsForOwner(Filament::auth()->id()))
                    ->default(fn (): string => ProjectStatusOption::defaultCodeForOwner(Filament::auth()->id()))
                    ->required(),
                Select::make('pipeline_stage')
                    ->options(ProjectPipelineStage::class)
                    ->default('new')
                    ->required(),
                Select::make('pricing_model')
                    ->options(ProjectPricingModel::class)
                    ->default('fixed')
                    ->required(),
                TextInput::make('priority')
                    ->required()
                    ->numeric()
                    ->default(3),
                DatePicker::make('start_date'),
                DatePicker::make('target_end_date'),
                DatePicker::make('closed_date'),
                TextInput::make('currency'),
                TextInput::make('hourly_rate')
                    ->numeric()
                    ->suffix(fn (Get $get): string => (string) ($get('currency') ?: (data_get(Filament::auth()->user(), 'default_currency', 'CZK')))),
                TextInput::make('fixed_price')
                    ->numeric()
                    ->suffix(fn (Get $get): string => (string) ($get('currency') ?: (data_get(Filament::auth()->user(), 'default_currency', 'CZK')))),
                TextInput::make('estimated_hours')
                    ->numeric(),
                TextInput::make('estimated_value')
                    ->numeric()
                    ->suffix(fn (Get $get): string => (string) ($get('currency') ?: (data_get(Filament::auth()->user(), 'default_currency', 'CZK')))),
                TextInput::make('actual_value')
                    ->numeric()
                    ->suffix(fn (Get $get): string => (string) ($get('currency') ?: (data_get(Filament::auth()->user(), 'default_currency', 'CZK')))),
                Textarea::make('description')
                    ->columnSpanFull(),
                DateTimePicker::make('last_activity_at'),
                KeyValue::make('meta')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('client_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('primaryContact.id')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (Project $record): string => $record->resolvedStatusLabel())
                    ->color(fn (Project $record): string => $record->resolvedStatusColor())
                    ->searchable(),
                TextColumn::make('pipeline_stage')
                    ->badge()
                    ->searchable(),
                TextColumn::make('pricing_model')
                    ->badge()
                    ->searchable(),
                TextColumn::make('priority')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('target_end_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('closed_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('hourly_rate_with_currency')
                    ->label('Hourly rate')
                    ->sortable(
                        query: fn (Builder $query, string $direction): Builder => $query
                            ->orderBy('hourly_rate', $direction)
                            ->orderBy('currency', $direction),
                    ),
                TextColumn::make('fixed_price')
                    ->money()
                    ->sortable(),
                TextColumn::make('estimated_hours')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('estimated_value')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('actual_value')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('last_activity_at')
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
