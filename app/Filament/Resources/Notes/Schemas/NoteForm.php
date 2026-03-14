<?php

namespace App\Filament\Resources\Notes\Schemas;

use App\Filament\Resources\Tags\Schemas\TagsSelect;
use App\Models\ClientContact;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Note;
use App\Models\Project;
use App\Models\RecurringService;
use App\Models\Worklog;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MorphToSelect;
use Filament\Forms\Components\MorphToSelect\Type;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class NoteForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = Filament::auth()->id();

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Tabs::make('Note Workspace')
                            ->tabs([
                                Tab::make('Content')
                                    ->icon(Heroicon::OutlinedDocumentText)
                                    ->schema([
                                        Section::make('Note Content')
                                            ->schema([
                                                Hidden::make('owner_id')
                                                    ->default($ownerId),
                                                MorphToSelect::make('noteable')
                                                    ->required()
                                                    ->searchable()
                                                    ->preload()
                                                    ->types([
                                                        Type::make(Customer::class)
                                                            ->titleAttribute('name')
                                                            ->modifyOptionsQueryUsing(fn (Builder $query): Builder => $ownerId !== null
                                                                ? $query->where('owner_id', $ownerId)
                                                                : $query),
                                                        Type::make(Project::class)
                                                            ->titleAttribute('name')
                                                            ->modifyOptionsQueryUsing(fn (Builder $query): Builder => $ownerId !== null
                                                                ? $query->where('owner_id', $ownerId)
                                                                : $query),
                                                        Type::make(Worklog::class)
                                                            ->titleAttribute('title')
                                                            ->modifyOptionsQueryUsing(fn (Builder $query): Builder => $ownerId !== null
                                                                ? $query->where('owner_id', $ownerId)
                                                                : $query),
                                                        Type::make(Lead::class)
                                                            ->titleAttribute('full_name')
                                                            ->modifyOptionsQueryUsing(fn (Builder $query): Builder => $ownerId !== null
                                                                ? $query->where('owner_id', $ownerId)
                                                                : $query),
                                                        Type::make(RecurringService::class)
                                                            ->titleAttribute('name')
                                                            ->modifyOptionsQueryUsing(fn (Builder $query): Builder => $ownerId !== null
                                                                ? $query->where('owner_id', $ownerId)
                                                                : $query),
                                                        Type::make(ClientContact::class)
                                                            ->titleAttribute('full_name')
                                                            ->modifyOptionsQueryUsing(fn (Builder $query): Builder => $ownerId !== null
                                                                ? $query->where('owner_id', $ownerId)
                                                                : $query),
                                                    ])
                                                    ->columnSpanFull(),
                                                Textarea::make('body')
                                                    ->required()
                                                    ->rows(8)
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1),
                                    ]),
                                Tab::make('Flags')
                                    ->icon(Heroicon::OutlinedFlag)
                                    ->schema([
                                        Section::make('Flags & Timing')
                                            ->schema([
                                                Toggle::make('is_pinned')
                                                    ->default(false),
                                                DateTimePicker::make('noted_at')
                                                    ->default(now()),
                                            ])
                                            ->columns(1),
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
                                    ->state(fn (?Note $record): ?string => $record?->created_at?->diffForHumans()),
                                TextEntry::make('updated_at')
                                    ->label('Last modified')
                                    ->state(fn (?Note $record): ?string => $record?->updated_at?->diffForHumans()),
                            ])
                            ->hidden(fn (?Note $record): bool => ! $record instanceof Note),
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
}
