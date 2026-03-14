<?php

namespace App\Filament\Resources\BacklogItems\Schemas;

use App\Enums\BacklogItemStatus;
use App\Models\Activity;
use App\Models\BacklogItem;
use App\Models\Tag;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

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
                                            ->columns(2),
                                        Section::make('Schedule')
                                            ->schema([
                                                Select::make('status')
                                                    ->options(BacklogItemStatus::class)
                                                    ->default(BacklogItemStatus::Todo)
                                                    ->required(),
                                                Select::make('priority')
                                                    ->options([
                                                        1 => '1',
                                                        2 => '2',
                                                        3 => '3',
                                                        4 => '4',
                                                        5 => '5',
                                                    ])
                                                    ->default(3)
                                                    ->required(),
                                                DatePicker::make('due_date'),
                                                TextInput::make('estimated_minutes')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->suffix('min'),
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
                                        Section::make('Tags')
                                            ->description('WordPress-like tag picker: search existing tags or create one inline.')
                                            ->schema([
                                                Select::make('tags')
                                                    ->multiple()
                                                    ->relationship(
                                                        name: 'tags',
                                                        titleAttribute: 'name',
                                                        modifyQueryUsing: fn (Builder $query): Builder => $ownerId !== null
                                                            ? $query->where('owner_id', $ownerId)->orderBy('sort_order')->orderBy('name')
                                                            : $query->orderBy('name'),
                                                    )
                                                    ->searchable()
                                                    ->preload()
                                                    ->native(false)
                                                    ->createOptionForm([
                                                        TextInput::make('name')
                                                            ->required()
                                                            ->maxLength(255),
                                                        ColorPicker::make('color')
                                                            ->default('#f59e0b'),
                                                    ])
                                                    ->createOptionUsing(function (array $data) use ($ownerId): int {
                                                        $resolvedOwnerId = (int) ($ownerId ?? Filament::auth()->id());
                                                        $name = trim((string) ($data['name'] ?? ''));
                                                        $slug = Str::slug($name);

                                                        if ($slug === '') {
                                                            $slug = 'tag';
                                                        }

                                                        $existingTag = Tag::query()
                                                            ->where('owner_id', $resolvedOwnerId)
                                                            ->where('slug', $slug)
                                                            ->first();

                                                        if ($existingTag instanceof Tag) {
                                                            return $existingTag->id;
                                                        }

                                                        $nextSortOrder = (int) (Tag::query()
                                                            ->where('owner_id', $resolvedOwnerId)
                                                            ->max('sort_order') ?? 0) + 10;

                                                        $tag = Tag::query()->create([
                                                            'owner_id' => $resolvedOwnerId,
                                                            'name' => $name,
                                                            'slug' => $slug,
                                                            'color' => $data['color'] ?? null,
                                                            'sort_order' => $nextSortOrder,
                                                        ]);

                                                        return $tag->id;
                                                    })
                                                    ->columnSpanFull(),
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
