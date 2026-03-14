<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\LeadPipelineStage;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\Tag;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = Filament::auth()->id();

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Hidden::make('owner_id')
                            ->default($ownerId),
                        Tabs::make('Lead Workspace')
                            ->tabs([
                                Tab::make('Contact')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->schema([
                                        Section::make('Primary Contact')
                                            ->schema([
                                                TextInput::make('full_name')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->prefixIcon(Heroicon::OutlinedUser)
                                                    ->columnSpanFull(),
                                                TextInput::make('company_name')
                                                    ->maxLength(255)
                                                    ->prefixIcon(Heroicon::OutlinedBuildingOffice2),
                                                TextInput::make('email')
                                                    ->email()
                                                    ->maxLength(255)
                                                    ->prefixIcon(Heroicon::OutlinedEnvelope),
                                                TextInput::make('phone')
                                                    ->tel()
                                                    ->maxLength(255)
                                                    ->prefixIcon(Heroicon::OutlinedPhone),
                                                TextInput::make('website')
                                                    ->url()
                                                    ->maxLength(255)
                                                    ->prefixIcon(Heroicon::OutlinedGlobeAlt)
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(2),
                                        Section::make('Problem & Context')
                                            ->schema([
                                                Textarea::make('summary')
                                                    ->rows(7)
                                                    ->autosize()
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                                Tab::make('Pipeline')
                                    ->icon(Heroicon::OutlinedFunnel)
                                    ->schema([
                                        Section::make('Qualification')
                                            ->schema([
                                                Select::make('lead_source_id')
                                                    ->relationship(
                                                        name: 'leadSource',
                                                        titleAttribute: 'name',
                                                        modifyQueryUsing: fn (Builder $query): Builder => $ownerId !== null
                                                            ? $query->where('owner_id', $ownerId)
                                                            : $query,
                                                    )
                                                    ->searchable()
                                                    ->preload(),
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
                                                Select::make('status')
                                                    ->options(LeadStatus::class)
                                                    ->default(LeadStatus::New)
                                                    ->required()
                                                    ->live()
                                                    ->afterStateUpdated(function (mixed $state, Set $set): void {
                                                        if (in_array(self::resolveLeadStatusValue($state), [LeadStatus::Won->value, LeadStatus::Lost->value, LeadStatus::Archived->value], true)) {
                                                            $set('pipeline_stage', LeadPipelineStage::Closed->value);
                                                        }
                                                    }),
                                                Select::make('pipeline_stage')
                                                    ->options(LeadPipelineStage::class)
                                                    ->default(LeadPipelineStage::Inbox)
                                                    ->required(),
                                                Select::make('priority')
                                                    ->options([
                                                        5 => 'Critical',
                                                        4 => 'High',
                                                        3 => 'Normal',
                                                        2 => 'Low',
                                                        1 => 'Backlog',
                                                    ])
                                                    ->default(3)
                                                    ->required(),
                                            ])
                                            ->columns(2),
                                        Section::make('Commercial')
                                            ->schema([
                                                Select::make('currency')
                                                    ->options([
                                                        'CZK' => 'CZK (Kč)',
                                                        'EUR' => 'EUR (€)',
                                                        'USD' => 'USD ($)',
                                                    ])
                                                    ->default('CZK')
                                                    ->required()
                                                    ->live(),
                                                TextInput::make('estimated_value')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->suffix(fn (Get $get): string => self::resolveCurrencyCode($get('currency')))
                                                    ->placeholder('0'),
                                                DatePicker::make('expected_close_date')
                                                    ->native(false),
                                            ])
                                            ->columns(3),
                                        Section::make('Activity Tracking')
                                            ->schema([
                                                DateTimePicker::make('contacted_at')
                                                    ->native(false),
                                                DateTimePicker::make('last_activity_at')
                                                    ->native(false),
                                            ])
                                            ->columns(2),
                                    ]),
                                Tab::make('Notes')
                                    ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                                    ->schema([
                                        Section::make('Quick Notes')
                                            ->schema([
                                                Repeater::make('quick_notes')
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
                                                            ->rows(4)
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
                                    ])
                                    ->hidden(fn (?Lead $record): bool => ! $record instanceof Lead),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpan([
                        'lg' => 8,
                    ]),
                Group::make()
                    ->schema([
                        Section::make('Snapshot')
                            ->schema([
                                Placeholder::make('status_preview')
                                    ->label('Status')
                                    ->content(fn (Get $get): string => self::leadStatusLabel($get('status'))),
                                Placeholder::make('stage_preview')
                                    ->label('Pipeline stage')
                                    ->content(fn (Get $get): string => self::leadPipelineStageLabel($get('pipeline_stage'))),
                                Placeholder::make('estimated_value_preview')
                                    ->label('Estimated value')
                                    ->content(fn (Get $get): string => self::formatEstimatedValue(self::resolveCurrencyCode($get('currency')), $get('estimated_value'))),
                                Placeholder::make('expected_close_preview')
                                    ->label('Expected close')
                                    ->content(function (Get $get): string {
                                        $expectedCloseDate = Date::make($get('expected_close_date'));

                                        if (! $expectedCloseDate instanceof Carbon) {
                                            return 'No target date';
                                        }

                                        return $expectedCloseDate->format('j M Y');
                                    }),
                            ]),
                        Section::make('System')
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->state(fn (?Lead $record): ?string => $record?->created_at?->diffForHumans()),
                                TextEntry::make('updated_at')
                                    ->label('Last modified')
                                    ->state(fn (?Lead $record): ?string => $record?->updated_at?->diffForHumans()),
                            ])
                            ->hidden(fn (?Lead $record): bool => ! $record instanceof Lead),
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

    private static function formatEstimatedValue(string $currency, mixed $estimatedValue): string
    {
        if ($estimatedValue === null || $estimatedValue === '') {
            return 'No estimate';
        }

        $numericValue = (float) $estimatedValue;
        $formattedValue = number_format($numericValue, 0, '.', ' ');

        return match (strtoupper($currency)) {
            'EUR' => '€ '.$formattedValue,
            'USD' => '$ '.$formattedValue,
            default => $formattedValue.' Kč',
        };
    }

    private static function resolveLeadStatusValue(mixed $value): string
    {
        if ($value instanceof LeadStatus) {
            return $value->value;
        }

        return (string) $value;
    }

    private static function resolveCurrencyCode(mixed $value): string
    {
        return strtoupper((string) $value);
    }

    private static function leadStatusLabel(mixed $value): string
    {
        if ($value instanceof LeadStatus) {
            return $value->getLabel();
        }

        if (is_string($value)) {
            return LeadStatus::tryFrom($value)?->getLabel() ?? 'Not set';
        }

        return 'Not set';
    }

    private static function leadPipelineStageLabel(mixed $value): string
    {
        if ($value instanceof LeadPipelineStage) {
            return $value->getLabel();
        }

        if (is_string($value)) {
            return LeadPipelineStage::tryFrom($value)?->getLabel() ?? 'Not set';
        }

        return 'Not set';
    }
}
