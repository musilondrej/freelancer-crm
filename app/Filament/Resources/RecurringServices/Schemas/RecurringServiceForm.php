<?php

namespace App\Filament\Resources\RecurringServices\Schemas;

use App\Enums\RecurringServiceBillingModel;
use App\Enums\RecurringServiceCadenceUnit;
use App\Enums\RecurringServiceStatus;
use App\Models\RecurringService;
use App\Models\RecurringServiceType as RecurringServiceTypeModel;
use App\Models\Tag;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
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
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

class RecurringServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = Filament::auth()->id();

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Tabs::make('Recurring Service Workspace')
                            ->tabs([
                                Tab::make('Service')
                                    ->icon(Heroicon::OutlinedArrowPathRoundedSquare)
                                    ->schema([
                                        Section::make('Service Definition')
                                            ->schema([
                                                Hidden::make('owner_id')
                                                    ->default($ownerId),
                                                TextInput::make('name')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpanFull(),
                                                Select::make('customer_id')
                                                    ->relationship(
                                                        name: 'customer',
                                                        titleAttribute: 'name',
                                                        modifyQueryUsing: fn (Builder $query): Builder => $ownerId !== null
                                                            ? $query->where('owner_id', $ownerId)
                                                            : $query,
                                                    )
                                                    ->searchable()
                                                    ->preload(),
                                                Select::make('project_id')
                                                    ->relationship(
                                                        name: 'project',
                                                        titleAttribute: 'name',
                                                        modifyQueryUsing: fn (Builder $query): Builder => $ownerId !== null
                                                            ? $query->where('owner_id', $ownerId)
                                                            : $query,
                                                    )
                                                    ->searchable()
                                                    ->preload(),
                                                Select::make('service_type_id')
                                                    ->label('Service category')
                                                    ->relationship(
                                                        name: 'serviceType',
                                                        titleAttribute: 'name',
                                                        modifyQueryUsing: fn (Builder $query): Builder => $ownerId !== null
                                                            ? $query
                                                                ->where('owner_id', $ownerId)
                                                                ->orderBy('sort_order')
                                                                ->orderBy('name')
                                                            : $query,
                                                    )
                                                    ->searchable()
                                                    ->preload()
                                                    ->createOptionForm([
                                                        TextInput::make('name')
                                                            ->required()
                                                            ->maxLength(255)
                                                            ->live(onBlur: true)
                                                            ->afterStateUpdated(fn (?string $state, Set $set): mixed => $set('slug', Str::slug((string) $state))),
                                                        TextInput::make('slug')
                                                            ->required()
                                                            ->maxLength(255),
                                                        Toggle::make('is_active')
                                                            ->default(true),
                                                    ])
                                                    ->createOptionUsing(function (array $data) use ($ownerId): int {
                                                        $resolvedOwnerId = (int) ($ownerId ?? Filament::auth()->id());

                                                        $serviceType = RecurringServiceTypeModel::query()->firstOrCreate(
                                                            [
                                                                'owner_id' => $resolvedOwnerId,
                                                                'slug' => (string) ($data['slug'] ?? ''),
                                                            ],
                                                            [
                                                                'name' => (string) ($data['name'] ?? ''),
                                                                'is_active' => (bool) ($data['is_active'] ?? true),
                                                                'sort_order' => (int) ($data['sort_order']
                                                                    ?? (RecurringServiceTypeModel::query()
                                                                        ->where('owner_id', $resolvedOwnerId)
                                                                        ->max('sort_order') + 10)),
                                                            ],
                                                        );

                                                        return $serviceType->id;
                                                    })
                                                    ->required(),
                                                Textarea::make('notes')
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
                                                Repeater::make('quick_notes')
                                                    ->relationship('quickNotes')
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
                        Section::make('Billing')
                            ->schema([
                                Select::make('billing_model')
                                    ->options(RecurringServiceBillingModel::class)
                                    ->default(RecurringServiceBillingModel::Fixed)
                                    ->required()
                                    ->live(),
                                Select::make('currency')
                                    ->options([
                                        'CZK' => 'CZK (Kč)',
                                        'EUR' => 'EUR (€)',
                                        'USD' => 'USD ($)',
                                    ]),
                                TextInput::make('fixed_amount')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => (string) ($get('currency') ?: (data_get(Filament::auth()->user(), 'default_currency', 'CZK'))))
                                    ->visible(fn (Get $get): bool => self::resolveBillingModelValue($get('billing_model')) === RecurringServiceBillingModel::Fixed->value),
                                TextInput::make('hourly_rate')
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => (string) ($get('currency') ?: (data_get(Filament::auth()->user(), 'default_currency', 'CZK'))))
                                    ->visible(fn (Get $get): bool => self::resolveBillingModelValue($get('billing_model')) === RecurringServiceBillingModel::Hourly->value),
                                TextInput::make('included_quantity')
                                    ->numeric()
                                    ->minValue(0)
                                    ->visible(fn (Get $get): bool => self::resolveBillingModelValue($get('billing_model')) === RecurringServiceBillingModel::Hourly->value),
                            ]),
                        Section::make('Cadence')
                            ->schema([
                                Select::make('cadence_unit')
                                    ->options(RecurringServiceCadenceUnit::class)
                                    ->default(RecurringServiceCadenceUnit::Month)
                                    ->required(),
                                TextInput::make('cadence_interval')
                                    ->numeric()
                                    ->minValue(1)
                                    ->default(1)
                                    ->helperText('Cadence is the source of truth for schedule progression.')
                                    ->required(),
                                DatePicker::make('starts_on')
                                    ->required(),
                                DatePicker::make('next_due_on')
                                    ->helperText('One-time override. After cadence updates, this date is recalculated automatically.'),
                                DatePicker::make('last_invoiced_on'),
                                DatePicker::make('ends_on'),
                                Select::make('status')
                                    ->options(RecurringServiceStatus::class)
                                    ->default(RecurringServiceStatus::Active)
                                    ->required(),
                                Toggle::make('auto_renew')
                                    ->default(true),
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
                            ]),
                        Section::make('System')
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->state(fn (?RecurringService $record): ?string => $record?->created_at?->diffForHumans()),
                                TextEntry::make('updated_at')
                                    ->label('Last modified')
                                    ->state(fn (?RecurringService $record): ?string => $record?->updated_at?->diffForHumans()),
                                TextEntry::make('last_reminded_at')
                                    ->label('Last reminder sent')
                                    ->state(fn (?RecurringService $record): string => $record?->last_reminded_at !== null
                                        ? Date::parse($record->last_reminded_at)->diffForHumans()
                                        : '-'),
                                TextEntry::make('last_reminded_for_due_on')
                                    ->label('Last reminder due date')
                                    ->state(fn (?RecurringService $record): string => $record?->last_reminded_for_due_on !== null
                                        ? Date::parse($record->last_reminded_for_due_on)->format('d.m.Y')
                                        : '-'),
                            ])
                            ->hidden(fn (?RecurringService $record): bool => ! $record instanceof RecurringService),
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

    private static function resolveBillingModelValue(mixed $value): string
    {
        if ($value instanceof RecurringServiceBillingModel) {
            return $value->value;
        }

        return (string) $value;
    }
}
