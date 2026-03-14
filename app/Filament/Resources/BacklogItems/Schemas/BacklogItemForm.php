<?php

namespace App\Filament\Resources\BacklogItems\Schemas;

use App\Enums\BacklogItemPriority;
use App\Enums\BacklogItemStatus;
use App\Filament\Resources\Notes\Schemas\NoteRepeater;
use App\Filament\Resources\Tags\Schemas\TagsSelect;
use App\Models\Activity;
use App\Models\BacklogItem;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

class BacklogItemForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = Filament::auth()->id();

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Tabs::make('Backlog Workspace')
                            ->tabs([
                                Tab::make('Details')
                                    ->icon(Heroicon::OutlinedQueueList)
                                    ->schema([
                                        Section::make('Plan')
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
                                                    ->label('Project')
                                                    ->required()
                                                    ->searchable()
                                                    ->preload()
                                                    ->live(),
                                                Select::make('activity_id')
                                                    ->label('Activity template')
                                                    ->options(fn (Get $get): array => self::activityOptions($ownerId, $get('project_id')))
                                                    ->searchable()
                                                    ->preload()
                                                    ->disabled(fn (Get $get): bool => ! is_numeric($get('project_id'))),
                                                TextInput::make('title')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpanFull(),
                                                Textarea::make('description')
                                                    ->rows(6)
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1),
                                        Section::make('Schedule')
                                            ->schema([
                                                Select::make('status')
                                                    ->options(BacklogItemStatus::class)
                                                    ->default(BacklogItemStatus::Todo)
                                                    ->required(),
                                                Select::make('priority')
                                                    ->options(BacklogItemPriority::class)
                                                    ->default(BacklogItemPriority::Medium->value)
                                                    ->required(),
                                                DatePicker::make('due_date'),
                                                TextInput::make('estimated_minutes')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->suffix('min'),
                                            ])
                                            ->columns(1),
                                    ]),
                                Tab::make('Notes')
                                    ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                                    ->schema([
                                        Section::make('Quick Notes')
                                            ->schema([
                                                NoteRepeater::make($ownerId),
                                            ]),
                                        Section::make('Tags')
                                            ->schema([
                                                TagsSelect::make($ownerId),
                                            ]),
                                    ]),
                            ]),
                    ])
                    ->columnSpan([
                        'lg' => 8,
                    ]),
                Group::make()
                    ->schema([
                        Section::make('System')
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->state(fn (?BacklogItem $record): ?string => $record?->created_at?->diffForHumans()),
                                TextEntry::make('updated_at')
                                    ->label('Last modified')
                                    ->state(fn (?BacklogItem $record): ?string => $record?->updated_at?->diffForHumans()),
                                TextEntry::make('converted_at')
                                    ->label('Converted to worklog')
                                    ->state(function (?BacklogItem $record): string {
                                        $convertedAt = $record?->converted_at;

                                        if ($convertedAt !== null) {
                                            return Date::parse((string) $convertedAt)->diffForHumans();
                                        }

                                        return '-';
                                    }),
                            ])
                            ->hidden(fn (?BacklogItem $record): bool => ! $record instanceof BacklogItem),
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
