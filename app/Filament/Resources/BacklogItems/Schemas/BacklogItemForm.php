<?php

namespace App\Filament\Resources\BacklogItems\Schemas;

use App\Enums\BacklogItemPriority;
use App\Enums\BacklogItemStatus;
use App\Filament\Resources\Notes\Schemas\NoteRepeater;
use App\Filament\Resources\Tags\Schemas\TagsSelect;
use App\Models\Activity;
use App\Models\BacklogItem;
use App\Support\TimeDuration;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class BacklogItemForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = Filament::auth()->id();

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make('Backlog Item')
                            ->schema([
                                Hidden::make('owner_id')
                                    ->default($ownerId),
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
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
                                    ->preload(),
                                Select::make('activity_id')
                                    ->label('Activity template')
                                    ->options(fn (): array => self::activityOptions($ownerId))
                                    ->searchable()
                                    ->preload(),
                                Textarea::make('description')
                                    ->rows(5)
                                    ->columnSpanFull(),
                            ]),

                        Section::make('Quick Notes')
                            ->schema([
                                NoteRepeater::make($ownerId),
                            ]),
                    ])
                    ->columnSpan([
                        'lg' => 8,
                    ]),

                // Sidebar
                Group::make()
                    ->schema([
                        Section::make('Tags')
                            ->schema([
                                TagsSelect::make($ownerId),
                            ]),
                        Section::make('Planning')
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
                                    ->label('Estimate')
                                    ->placeholder('e.g. 2h 30m, 1d, 45m')
                                    ->formatStateUsing(fn (?int $state): ?string => TimeDuration::format($state))
                                    ->dehydrateStateUsing(fn (?string $state): ?int => $state !== null ? TimeDuration::toMinutes($state) : null),
                            ]),
                        Section::make('Conversion')
                            ->schema([
                                TextEntry::make('converted_at')
                                    ->label('Converted to worklog')
                                    ->state(fn (?BacklogItem $record): string => $record?->converted_at?->diffForHumans() ?? 'Not yet converted'),
                            ])
                            ->hidden(fn (?BacklogItem $record): bool => ! $record instanceof BacklogItem),
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
    private static function activityOptions(?int $ownerId): array
    {
        if ($ownerId === null) {
            return [];
        }

        return Activity::query()
            ->where('owner_id', $ownerId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
