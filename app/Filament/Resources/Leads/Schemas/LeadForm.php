<?php

namespace App\Filament\Resources\Leads\Schemas;

use App\Enums\Currency;
use App\Enums\LeadStatus;
use App\Enums\Priority;
use App\Filament\Resources\Tags\Schemas\TagsSelect;
use App\Models\Lead;
use App\Support\Filament\FilteredByOwner;
use App\Support\Filament\MetadataSection;
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
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

class LeadForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = FilteredByOwner::ownerId();

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
                                Select::make('priority')
                                    ->label(__('Priority'))
                                    ->options(Priority::class)
                                    ->default(Priority::defaultCase())
                                    ->required(),
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
                                    ->label(__('Summary'))
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
                                        modifyQueryUsing: FilteredByOwner::closure(),
                                    )
                                    ->searchable()
                                    ->preload(),
                                Select::make('customer_id')
                                    ->label(__('Customer'))
                                    ->relationship(
                                        name: 'customer',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: FilteredByOwner::closure(),
                                    )
                                    ->searchable()
                                    ->preload(),
                                Select::make('status')
                                    ->label(__('Status'))
                                    ->options(LeadStatus::class)
                                    ->default(LeadStatus::New)
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
                        MetadataSection::make(Lead::class),
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
}
