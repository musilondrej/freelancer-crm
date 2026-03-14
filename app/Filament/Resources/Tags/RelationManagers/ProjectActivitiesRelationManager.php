<?php

namespace App\Filament\Resources\Tags\RelationManagers;

use App\Enums\ProjectActivityType;
use App\Models\ProjectActivity;
use App\Support\Filament\Currency;
use App\Support\Filament\WorklogStatus;
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

class ProjectActivitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'projectActivities';

    protected static ?string $title = 'Worklogs';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('project_id')
                    ->relationship('project', 'name')
                    ->required(),
                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('type')
                    ->options(ProjectActivityType::class)
                    ->default('hourly')
                    ->required(),
                Select::make('status')
                    ->options(fn (): array => WorklogStatus::options(Filament::auth()->id()))
                    ->default(fn (): string => WorklogStatus::defaultCode(Filament::auth()->id()))
                    ->required(),
                Toggle::make('is_billable')
                    ->required(),
                TextInput::make('currency'),
                TextInput::make('quantity')
                    ->numeric(),
                TextInput::make('unit_rate')
                    ->numeric()
                    ->suffix(fn (Get $get): string => Currency::resolve($get)),
                TextInput::make('flat_amount')
                    ->numeric()
                    ->suffix(fn (Get $get): string => Currency::resolve($get)),
                TextInput::make('tracked_minutes')
                    ->numeric(),
                DatePicker::make('due_date'),
                DateTimePicker::make('started_at'),
                DateTimePicker::make('finished_at'),
                KeyValue::make('meta')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('started_at', 'desc')
            ->columns([
                TextColumn::make('project.name')
                    ->searchable(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (ProjectActivity $record): string => $record->resolvedStatusLabel())
                    ->color(fn (ProjectActivity $record): string => $record->resolvedStatusColor())
                    ->searchable(),
                IconColumn::make('is_billable')
                    ->boolean(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unit_rate')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('flat_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tracked_minutes')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('started_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('finished_at')
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
