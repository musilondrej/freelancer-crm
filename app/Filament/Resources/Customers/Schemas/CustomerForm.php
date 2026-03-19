<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Enums\Currency;
use App\Enums\CustomerStatus;
use App\Filament\Resources\Notes\Schemas\NoteRepeater;
use App\Filament\Resources\Tags\Schemas\TagsSelect;
use App\Models\Customer;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
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
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class CustomerForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = Filament::auth()->id();

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Section::make(__('Customer details'))
                            ->schema([
                                Hidden::make('owner_id')
                                    ->default($ownerId),
                                TextInput::make('name')
                                    ->label(__('Company name'))
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                TextInput::make('legal_name')
                                    ->label(__('Legal name'))
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
                                            ->where('owner_id', Filament::auth()->id())
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
                                            ->where('owner_id', Filament::auth()->id())
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
                                Toggle::make('use_custom_billing_currency')
                                    ->label(__('Override inherited currency'))
                                    ->dehydrated(false)
                                    ->live()
                                    ->default(fn (?Customer $record): bool => $record?->billing_currency !== null)
                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                        if (! (bool) $state) {
                                            $set('billing_currency', null);
                                        }
                                    })
                                    ->helperText(__('When disabled, customer uses your profile default currency.')),
                                Select::make('billing_currency')
                                    ->label(__('Currency'))
                                    ->options(Currency::class)
                                    ->visible(fn (Get $get): bool => (bool) $get('use_custom_billing_currency')),
                                Toggle::make('use_custom_hourly_rate')
                                    ->label(__('Override inherited hourly rate'))
                                    ->dehydrated(false)
                                    ->live()
                                    ->default(fn (?Customer $record): bool => $record?->hourly_rate !== null)
                                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                                        if (! (bool) $state) {
                                            $set('hourly_rate', null);
                                        }
                                    })
                                    ->helperText(__('When disabled, customer uses your profile default hourly rate.')),
                                TextInput::make('hourly_rate')
                                    ->label(__('Hourly rate'))
                                    ->numeric()
                                    ->minValue(0)
                                    ->suffix(fn (Get $get): string => Currency::resolveFromForm($get, 'billing_currency'))
                                    ->visible(fn (Get $get): bool => (bool) $get('use_custom_hourly_rate')),
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

                        Section::make(__('Metadata'))
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label(__('Created at'))
                                    ->state(fn (?Customer $record): ?string => $record?->created_at?->diffForHumans()),
                                TextEntry::make('updated_at')
                                    ->label(__('Last modified at'))
                                    ->state(fn (?Customer $record): ?string => $record?->updated_at?->diffForHumans()),
                            ])
                            ->hidden(fn (?Customer $record): bool => ! $record instanceof Customer),
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
