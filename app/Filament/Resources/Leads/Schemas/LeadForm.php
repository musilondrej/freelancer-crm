<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\Currency;
use App\Enums\LeadPipelineStage;
use App\Enums\LeadStatus;
use App\Filament\Resources\Tags\Schemas\TagsSelect;
use App\Models\Lead;
use Filament\Facades\Filament;
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
                        Section::make(__('Lead details'))
                            ->schema([
                                TextInput::make('full_name')
                                    ->label(__('Full name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::OutlinedUser)
                                    ->columnSpanFull(),
                                TextInput::make('company_name')
                                    ->label(__('Company name'))
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::OutlinedBuildingOffice2),
                                TextInput::make('email')
                                    ->label(__('E-mail'))
                                    ->email()
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::OutlinedEnvelope),
                                TextInput::make('phone')
                                    ->label(__('Phone'))
                                    ->tel()
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::OutlinedPhone),
                                TextInput::make('website')
                                    ->label(__('Website'))
                                    ->url()
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::OutlinedGlobeAlt)
                                    ->columnSpanFull(),
                                Textarea::make('summary')
                                    ->hiddenLabel(true)
                                    ->rows(7)
                                    ->autosize()
                                    ->columnSpanFull(),
                            ])
                            ->columns(1),
                        Section::make(__('Sales details'))
                            ->schema([
                                Select::make('lead_source_id')
                                    ->label(__('Lead source'))
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
                                    ->label(__('Customer'))
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
                                    ->label(__('Status'))
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
                                    ->label(__('Priority'))
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
                            ->columns(1),
                        Section::make(__('Sales details'))
                            ->schema([
                                Select::make('currency')
                                    ->label(__('Currency'))
                                    ->options(Currency::class)
                                    ->default(Currency::CZK)
                                    ->required()
                                    ->live(),
                                TextInput::make('estimated_value')
                                    ->label(__('Estimated value'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => Currency::resolveFromForm($get))
                                    ->placeholder('0'),
                                DatePicker::make('expected_close_date')
                                    ->label(__('Expected close date'))
                                    ->native(false),
                            ])
                            ->columns(3),

                        Section::make(__('Notes'))
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
                                    ->columns(1)
                                    ->addActionLabel(__('Add note'))
                                    ->defaultItems(0)
                                    ->collapsed()
                                    ->reorderable(false)
                                    ->itemLabel(fn (array $state): string => Str::limit((string) ($state['body'] ?? 'Note'), 64)),
                            ]),

                    ])

                    ->columnSpan([
                        'lg' => 8,
                    ]),
                Group::make()
                    ->schema([
                        Section::make(__('Tags'))
                            ->schema([
                                TagsSelect::make($ownerId),
                            ]),
                        Section::make(__('Activity details'))
                            ->schema([
                                DateTimePicker::make('contacted_at')
                                    ->label(__('Contacted at'))
                                    ->native(false),
                                DateTimePicker::make('last_activity_at')
                                    ->label(__('Last activity at'))
                                    ->native(false),
                            ]),
                        Section::make(__('Overview'))
                            ->schema([
                                Placeholder::make('status_preview')
                                    ->label(__('Status'))
                                    ->content(fn (Get $get): string => self::leadStatusLabel($get('status'))),
                                Placeholder::make('stage_preview')
                                    ->label(__('Pipeline stage'))
                                    ->content(fn (Get $get): string => self::leadPipelineStageLabel($get('pipeline_stage'))),
                                Placeholder::make('estimated_value_preview')
                                    ->label(__('Estimated value'))
                                    ->content(function (Get $get): string {
                                        $estimatedValue = $get('estimated_value');

                                        if ($estimatedValue === null || $estimatedValue === '') {
                                            return 'No estimate';
                                        }

                                        $currency = $get('currency');

                                        return $currency->format((float) $estimatedValue);
                                    }),
                                Placeholder::make('expected_close_preview')
                                    ->label(__('Expected close'))
                                    ->content(function (Get $get): string {
                                        $expectedCloseDate = Date::make($get('expected_close_date'));

                                        if (! $expectedCloseDate instanceof Carbon) {
                                            return 'No target date';
                                        }

                                        return $expectedCloseDate->format('j M Y');
                                    }),
                            ]),
                        Section::make(__('Metadata'))
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label(__('Created at'))
                                    ->state(fn (?Lead $record): ?string => $record?->created_at?->diffForHumans()),
                                TextEntry::make('updated_at')
                                    ->label(__('Last modified at'))
                                    ->state(fn (?Lead $record): ?string => $record?->updated_at?->diffForHumans()),
                            ])
                            ->hidden(fn (?Lead $record): bool => ! $record instanceof Lead),
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

    private static function resolveLeadStatusValue(mixed $value): string
    {
        if ($value instanceof LeadStatus) {
            return $value->value;
        }

        return (string) $value;
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
