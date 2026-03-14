<?php

namespace App\Filament\Resources\Customers\Schemas;

use App\Enums\CustomerStatus;
use App\Filament\Resources\Notes\Schemas\NoteRepeater;
use App\Filament\Resources\Tags\Schemas\TagsSelect;
use App\Models\Customer;
use Filament\Facades\Filament;
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
                        Tabs::make('Customer Workspace')
                            ->tabs([
                                Tab::make('Profile')
                                    ->icon(Heroicon::OutlinedBuildingOffice2)
                                    ->schema([
                                        Section::make('Company Profile')
                                            ->schema([
                                                Hidden::make('owner_id')
                                                    ->default($ownerId),
                                                TextInput::make('name')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpanFull(),
                                                TextInput::make('legal_name')
                                                    ->maxLength(255)
                                                    ->columnSpanFull(),
                                                TextInput::make('company_id')
                                                    ->label('Company ID')
                                                    ->maxLength(255),
                                                TextInput::make('vat_id')
                                                    ->label('VAT ID')
                                                    ->maxLength(255)
                                                    ->unique(
                                                        Customer::class,
                                                        'vat_id',
                                                        ignoreRecord: true,
                                                        modifyRuleUsing: fn (Unique $rule): Unique => $rule
                                                            ->where('owner_id', Filament::auth()->id())
                                                            ->whereNull('deleted_at'),
                                                    ),
                                                TextInput::make('dic')
                                                    ->label('DIC')
                                                    ->maxLength(255),
                                                Textarea::make('internal_summary')
                                                    ->rows(5)
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1),
                                    ]),
                                Tab::make('Billing')
                                    ->icon(Heroicon::OutlinedCreditCard)
                                    ->schema([
                                        Section::make('Contact & Billing')
                                            ->schema([
                                                TextInput::make('email')
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
                                                    ->tel()
                                                    ->maxLength(255),
                                                TextInput::make('website')
                                                    ->url()
                                                    ->maxLength(255),
                                                TextInput::make('timezone')
                                                    ->maxLength(255),
                                                Select::make('billing_currency')
                                                    ->label('Billing currency')
                                                    ->options([
                                                        'CZK' => 'CZK (Kč)',
                                                        'EUR' => 'EUR (€)',
                                                        'USD' => 'USD ($)',
                                                    ]),
                                                TextInput::make('hourly_rate')
                                                    ->numeric()
                                                    ->minValue(0)
                                                    ->suffix(fn (Get $get): string => (string) ($get('billing_currency') ?: (data_get(Filament::auth()->user(), 'default_currency', 'CZK')))),
                                            ])
                                            ->columns(1),
                                    ]),
                                Tab::make('Contacts')
                                    ->icon(Heroicon::OutlinedUserGroup)
                                    ->schema([
                                        Section::make('Key Contacts')
                                            ->description('Use inline contacts for day-to-day CRM work. Keep relation tabs focused on delivery records.')
                                            ->schema([
                                                Repeater::make('contacts')
                                                    ->relationship('contacts')
                                                    ->schema([
                                                        Hidden::make('owner_id')
                                                            ->default($ownerId),
                                                        TextInput::make('full_name')
                                                            ->required()
                                                            ->maxLength(255),
                                                        TextInput::make('job_title')
                                                            ->maxLength(255),
                                                        TextInput::make('email')
                                                            ->email()
                                                            ->maxLength(255),
                                                        TextInput::make('phone')
                                                            ->tel()
                                                            ->maxLength(255),
                                                        Toggle::make('is_primary')
                                                            ->default(false),
                                                        Toggle::make('is_billing_contact')
                                                            ->default(false),
                                                        DateTimePicker::make('last_contacted_at'),
                                                        KeyValue::make('meta')
                                                            ->columnSpanFull(),
                                                    ])
                                                    ->columns(1)
                                                    ->addActionLabel('Add contact')
                                                    ->defaultItems(0)
                                                    ->collapsed()
                                                    ->reorderable(false)
                                                    ->itemLabel(fn (array $state): string => Str::limit((string) ($state['full_name'] ?? 'Contact'), 64)),
                                            ]),
                                    ]),
                            ]),
                        Section::make('Quick Notes')
                            ->description('Inline relationship CRUD for fast account context.')
                            ->schema([
                                NoteRepeater::make($ownerId),
                            ]),
                    ])
                    ->columnSpan([
                        'lg' => 8,
                    ]),
                Group::make()
                    ->schema([
                        Section::make('Tags')
                            ->schema([
                                TagsSelect::make($ownerId),
                            ]),

                        Section::make('Lifecycle')
                            ->schema([
                                Select::make('status')
                                    ->options(CustomerStatus::class)
                                    ->default(CustomerStatus::Lead)
                                    ->required(),
                                TextInput::make('source')
                                    ->maxLength(255),
                                DateTimePicker::make('last_contacted_at'),
                                DateTimePicker::make('next_follow_up_at'),
                            ]),
                        Section::make('System')
                            ->schema([
                                TextEntry::make('created_at')
                                    ->label('Created')
                                    ->state(fn (?Customer $record): ?string => $record?->created_at?->diffForHumans()),
                                TextEntry::make('updated_at')
                                    ->label('Last modified')
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
