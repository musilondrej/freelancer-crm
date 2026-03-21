<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Enums\CustomerStatus;
use App\Filament\Resources\Notes\Schemas\NoteRepeater;
use App\Filament\Resources\Tags\Schemas\TagsSelect;
use App\Models\Customer;
use App\Support\Filament\FilteredByOwner;
use App\Support\Filament\HourlyRateCurrencyFields;
use App\Support\Filament\MetadataSection;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = FilteredByOwner::ownerId();

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make(__('Customer details'))
                            ->schema([
                                Hidden::make('owner_id')
                                    ->default($ownerId),
                                TextInput::make('name')
                                    ->label(__('Client name'))
                                    ->hint(__('Short name used throughout the app'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                TextInput::make('legal_name')
                                    ->label(__('Legal name'))
                                    ->hint(__('Full registered company name, used on invoices'))
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                TextInput::make('registration_number')
                                    ->label(__('Registration number'))
                                    ->maxLength(255),
                                TextInput::make('vat_id')
                                    ->label(__('Tax ID'))
                                    ->maxLength(255)
                                    ->unique(
                                        Customer::class,
                                        'vat_id',
                                        ignoreRecord: true,
                                        modifyRuleUsing: fn (Unique $rule): Unique => $rule
                                            ->where('owner_id', FilteredByOwner::ownerId())
                                            ->whereNull('deleted_at'),
                                    ),
                                Textarea::make('internal_summary')
                                    ->label(__('Internal summary'))
                                    ->rows(4)
                                    ->columnSpanFull(),
                            ])
                            ->columns(1),

                        Section::make(__('Contact details'))
                            ->schema([
                                TextInput::make('email')
                                    ->label(__('E-mail'))
                                    ->email()
                                    ->maxLength(255)
                                    ->unique(
                                        Customer::class,
                                        'email',
                                        ignoreRecord: true,
                                        modifyRuleUsing: fn (Unique $rule): Unique => $rule
                                            ->where('owner_id', FilteredByOwner::ownerId())
                                            ->whereNull('deleted_at'),
                                    ),
                                TextInput::make('phone')
                                    ->label(__('Phone'))
                                    ->tel()
                                    ->maxLength(255),
                                TextInput::make('website')
                                    ->label(__('Website'))
                                    ->url()
                                    ->maxLength(255),
                                ...HourlyRateCurrencyFields::make(
                                    currencyField: 'billing_currency',
                                    rateField: 'hourly_rate',
                                ),
                            ])
                            ->columns(1),

                        Section::make(__('Contacts'))
                            ->schema([
                                Repeater::make('contacts')
                                    ->relationship('contacts')
                                    ->schema([
                                        Hidden::make('owner_id')
                                            ->default($ownerId),
                                        TextInput::make('full_name')
                                            ->label(__('Full name'))
                                            ->required()
                                            ->maxLength(255),
                                        TextInput::make('job_title')
                                            ->label(__('Job title'))
                                            ->maxLength(255),
                                        TextInput::make('email')
                                            ->label(__('E-mail'))
                                            ->email()
                                            ->maxLength(255),
                                        TextInput::make('phone')
                                            ->label(__('Phone'))
                                            ->tel()
                                            ->maxLength(255),
                                        Toggle::make('is_primary')
                                            ->label(__('Is primary contact'))
                                            ->default(false),
                                    ])
                                    ->columns(1)
                                    ->addActionLabel(__('Add contact'))
                                    ->defaultItems(0)
                                    ->collapsed()
                                    ->reorderable(false)
                                    ->itemLabel(fn (array $state): string => Str::limit((string) ($state['full_name'] ?? __('Contact')), 64)),
                            ]),

                        Section::make(__('Notes'))
                            ->schema([
                                NoteRepeater::make($ownerId),
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

                        Section::make(__('Status'))
                            ->schema([
                                Select::make('status')
                                    ->options(CustomerStatus::class)
                                    ->default(CustomerStatus::Lead)
                                    ->required(),
                                DateTimePicker::make('next_follow_up_at'),
                            ]),

                        MetadataSection::make(Customer::class),
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
