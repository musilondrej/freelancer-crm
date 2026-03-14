<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Enums\ProjectPipelineStage;
use App\Enums\ProjectPricingModel;
use App\Models\ClientContact;
use App\Models\Project;
use App\Models\ProjectStatusOption;
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
use Illuminate\Support\Str;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = Filament::auth()->id();

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Tabs::make('Project Workspace')
                            ->tabs([
                                Tab::make('Brief')
                                    ->icon(Heroicon::OutlinedDocumentText)
                                    ->schema([
                                        Section::make('Project Brief')
                                            ->schema([
                                                Hidden::make('owner_id')
                                                    ->default($ownerId),
                                                TextInput::make('name')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpanFull(),
                                                Select::make('client_id')
                                                    ->label('Customer')
                                                    ->relationship(
                                                        name: 'customer',
                                                        titleAttribute: 'name',
                                                        modifyQueryUsing: fn (Builder $query): Builder => $ownerId !== null
                                                            ? $query->where('owner_id', $ownerId)
                                                            : $query,
                                                    )
                                                    ->required()
                                                    ->searchable()
                                                    ->preload()
                                                    ->live(),
                                                Select::make('primary_contact_id')
                                                    ->label('Primary contact')
                                                    ->options(function (Get $get) use ($ownerId): array {
                                                        $customerId = $get('client_id');

                                                        return ClientContact::query()
                                                            ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId))
                                                            ->when($customerId !== null, fn (Builder $query): Builder => $query->where('client_id', $customerId))
                                                            ->orderBy('full_name')
                                                            ->pluck('full_name', 'id')
                                                            ->all();
                                                    })
                                                    ->searchable()
                                                    ->preload(),
                                                Textarea::make('description')
                                                    ->rows(7)
                                                    ->columnSpanFull(),
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
                        Section::make('Execution')
                            ->schema([
                                Select::make('status')
                                    ->options(fn (): array => ProjectStatusOption::optionsForOwner($ownerId))
                                    ->default(fn (): string => ProjectStatusOption::defaultCodeForOwner($ownerId))
                                    ->required(),
                                Select::make('pipeline_stage')
                                    ->options(ProjectPipelineStage::class)
                                    ->default(ProjectPipelineStage::New)
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
                                DatePicker::make('start_date'),
                                DatePicker::make('target_end_date'),
                                DatePicker::make('closed_date'),
                            ]),
                        Section::make('Pricing')
                            ->schema([
                                Select::make('pricing_model')
                                    ->options(ProjectPricingModel::class)
                                    ->default(ProjectPricingModel::Fixed)
                                    ->required()
                                    ->live(),
                                Select::make('currency')
                                    ->options([
                                        'CZK' => 'CZK (Kč)',
                                        'EUR' => 'EUR (€)',
                                        'USD' => 'USD ($)',
                                    ]),
                                TextInput::make('hourly_rate')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => (string) ($get('currency') ?: (data_get(Filament::auth()->user(), 'default_currency', 'CZK'))))
                                    ->visible(fn (Get $get): bool => in_array(self::resolvePricingModelValue($get('pricing_model')), [ProjectPricingModel::Hourly->value, ProjectPricingModel::Retainer->value], true)),
                                TextInput::make('fixed_price')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => (string) ($get('currency') ?: (data_get(Filament::auth()->user(), 'default_currency', 'CZK'))))
                                    ->visible(fn (Get $get): bool => self::resolvePricingModelValue($get('pricing_model')) === ProjectPricingModel::Fixed->value),
                                TextInput::make('estimated_hours')
                                    ->numeric()
                                    ->minValue(0),
                                TextInput::make('estimated_value')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => (string) ($get('currency') ?: (data_get(Filament::auth()->user(), 'default_currency', 'CZK')))),
                                TextInput::make('actual_value')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => (string) ($get('currency') ?: (data_get(Filament::auth()->user(), 'default_currency', 'CZK')))),
                                DateTimePicker::make('last_activity_at'),
                            ]),
                        Section::make('System')
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->state(fn (?Project $record): ?string => $record?->created_at?->diffForHumans()),
                                TextEntry::make('updated_at')
                                    ->label('Last modified')
                                    ->state(fn (?Project $record): ?string => $record?->updated_at?->diffForHumans()),
                            ])
                            ->hidden(fn (?Project $record): bool => ! $record instanceof Project),
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

    private static function resolvePricingModelValue(mixed $value): string
    {
        if ($value instanceof ProjectPricingModel) {
            return $value->value;
        }

        return (string) $value;
    }
}
