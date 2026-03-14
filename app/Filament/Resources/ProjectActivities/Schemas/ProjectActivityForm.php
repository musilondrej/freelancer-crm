<?php

namespace App\Filament\Resources\ProjectActivities\Schemas;

use App\Enums\ProjectActivityType;
use App\Models\Activity;
use App\Models\ProjectActivity;
use App\Models\ProjectActivityStatusOption;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProjectActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = Filament::auth()->id();

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Tabs::make('Activity Workspace')
                            ->tabs([
                                Tab::make('Details')
                                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                                    ->schema([
                                        Section::make('Activity')
                                            ->schema([
                                                Hidden::make('owner_id')
                                                    ->default($ownerId),
                                                Select::make('project_id')
                                                    ->relationship(
                                                        name: 'project',
                                                        titleAttribute: 'name',
                                                        modifyQueryUsing: fn (Builder $query): Builder => $ownerId !== null
                                                            ? $query->where('owner_id', $ownerId)
                                                            : $query,
                                                    )
                                                    ->required()
                                                    ->searchable()
                                                    ->preload()
                                                    ->live()
                                                    ->afterStateUpdated(fn (Set $set): mixed => $set('activity_id', null)),
                                                Select::make('activity_id')
                                                    ->label('Activity')
                                                    ->required()
                                                    ->options(fn (Get $get): array => self::activityOptions($ownerId, $get('project_id')))
                                                    ->searchable()
                                                    ->preload()
                                                    ->disabled(fn (Get $get): bool => ! is_numeric($get('project_id')))
                                                    ->live()
                                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                                        if (! is_numeric($state)) {
                                                            return;
                                                        }

                                                        $activity = Activity::query()->find((int) $state);

                                                        if (! $activity instanceof Activity) {
                                                            return;
                                                        }

                                                        $set('title', $activity->name);
                                                        $set('is_billable', $activity->is_billable);

                                                        if ($activity->default_hourly_rate !== null) {
                                                            $set('unit_rate', $activity->default_hourly_rate);
                                                        }
                                                    }),
                                                TextInput::make('title')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->readOnly(fn (Get $get): bool => is_numeric($get('activity_id')))
                                                    ->columnSpanFull(),
                                                Textarea::make('description')
                                                    ->rows(7)
                                                    ->columnSpanFull(),
                                                Select::make('type')
                                                    ->options(ProjectActivityType::class)
                                                    ->default(ProjectActivityType::Hourly)
                                                    ->required()
                                                    ->live(),
                                                Select::make('status')
                                                    ->options(fn (): array => ProjectActivityStatusOption::optionsForOwner($ownerId))
                                                    ->default(fn (): string => ProjectActivityStatusOption::defaultCodeForOwner($ownerId))
                                                    ->required(),
                                                Toggle::make('is_billable')
                                                    ->default(true),
                                                Toggle::make('is_invoiced')
                                                    ->default(false)
                                                    ->live()
                                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                                        if (! (bool) $state) {
                                                            $set('invoice_reference', null);
                                                            $set('invoiced_at', null);

                                                            return;
                                                        }

                                                        $set('invoiced_at', now());
                                                    }),
                                            ])
                                            ->columns(2),
                                    ]),
                                Tab::make('Notes')
                                    ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                                    ->schema([
                                        Section::make('Quick Notes')
                                            ->schema([
                                                Repeater::make('notes')
                                                    ->relationship('notes')
                                                    ->schema([
                                                        Hidden::make('owner_id')
                                                            ->default($ownerId),
                                                        Toggle::make('is_pinned')
                                                            ->default(false),
                                                        DateTimePicker::make('noted_at')
                                                            ->default(now()),
                                                        Textarea::make('body')
                                                            ->required()
                                                            ->rows(3)
                                                            ->columnSpanFull(),
                                                        KeyValue::make('meta')
                                                            ->columnSpanFull(),
                                                    ])
                                                    ->columns(2)
                                                    ->addActionLabel('Add note')
                                                    ->defaultItems(0)
                                                    ->collapsed()
                                                    ->reorderable(false)
                                                    ->itemLabel(fn (array $state): string => Str::limit((string) ($state['body'] ?? 'Note'), 64)),
                                            ]),
                                    ]),
                            ]),
                    ])
                    ->columnSpan([
                        'lg' => 8,
                    ]),
                Group::make()
                    ->schema([
                        Section::make('Billing & Time')
                            ->schema([
                                Select::make('currency')
                                    ->options([
                                        'CZK' => 'CZK (Kč)',
                                        'EUR' => 'EUR (€)',
                                        'USD' => 'USD ($)',
                                    ]),
                                TextInput::make('quantity')
                                    ->numeric()
                                    ->minValue(0),
                                TextInput::make('unit_rate')
                                    ->numeric()
                                    ->minValue(0),
                                TextInput::make('flat_amount')
                                    ->numeric()
                                    ->minValue(0)
                                    ->visible(fn (Get $get): bool => self::resolveActivityTypeValue($get('type')) === ProjectActivityType::OneTime->value),
                                TextInput::make('tracked_minutes')
                                    ->numeric()
                                    ->minValue(0)
                                    ->visible(fn (Get $get): bool => self::resolveActivityTypeValue($get('type')) === ProjectActivityType::Hourly->value),
                                TextInput::make('invoice_reference')
                                    ->maxLength(64)
                                    ->visible(fn (Get $get): bool => (bool) $get('is_invoiced')),
                                DateTimePicker::make('invoiced_at')
                                    ->seconds(false)
                                    ->visible(fn (Get $get): bool => (bool) $get('is_invoiced')),
                                DatePicker::make('due_date'),
                                DateTimePicker::make('started_at'),
                                DateTimePicker::make('finished_at'),
                            ]),
                        Section::make('System')
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->state(fn (?ProjectActivity $record): ?string => $record?->created_at?->diffForHumans()),
                                TextEntry::make('updated_at')
                                    ->label('Last modified')
                                    ->state(fn (?ProjectActivity $record): ?string => $record?->updated_at?->diffForHumans()),
                            ])
                            ->hidden(fn (?ProjectActivity $record): bool => ! $record instanceof ProjectActivity),
                        Section::make('Technical Metadata')
                            ->schema([
                                KeyValue::make('meta')
                                    ->columnSpanFull(),
                            ])
                            ->collapsed(),
                    ])
                    ->columnSpan([
                        'lg' => 4,
                    ]),
            ])
            ->columns([
                'default' => 1,
                'lg' => 12,
            ]);
    }

    private static function resolveActivityTypeValue(mixed $value): string
    {
        if ($value instanceof ProjectActivityType) {
            return $value->value;
        }

        return (string) $value;
    }

    /**
     * @return array<int, string>
     */
    private static function activityOptions(?int $ownerId, mixed $projectId): array
    {
        if ($ownerId === null || ! is_numeric($projectId)) {
            return [];
        }

        return Activity::query()
            ->where('owner_id', $ownerId)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($projectId): void {
                $query->whereNull('project_id')
                    ->orWhere('project_id', (int) $projectId);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
