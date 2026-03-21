<?php

namespace App\Filament\Resources\ClientContacts\Schemas;

use App\Models\ClientContact;
use App\Support\Filament\FilteredByOwner;
use App\Support\Filament\MetadataSection;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ClientContactForm
{
    public static function configure(Schema $schema): Schema
    {
        $ownerId = FilteredByOwner::ownerId();

        return $schema
            ->components([
                Group::make()
                    ->schema([
                        Tabs::make('Contact Workspace')
                            ->tabs([
                                Tab::make('Profile')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->schema([
                                        Section::make('Contact Profile')
                                            ->schema([
                                                Hidden::make('owner_id')
                                                    ->default($ownerId),
                                                Select::make('customer_id')
                                                    ->label('Customer')
                                                    ->relationship(
                                                        name: 'customer',
                                                        titleAttribute: 'name',
                                                        modifyQueryUsing: FilteredByOwner::closure(),
                                                    )
                                                    ->required()
                                                    ->searchable()
                                                    ->preload(),
                                                TextInput::make('full_name')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpanFull(),
                                                TextInput::make('job_title')
                                                    ->maxLength(255),
                                                TextInput::make('email')
                                                    ->email()
                                                    ->maxLength(255),
                                                TextInput::make('phone')
                                                    ->tel()
                                                    ->maxLength(255),
                                                DateTimePicker::make('last_contacted_at'),
                                            ])
                                            ->columns(1),
                                    ]),
                                Tab::make('Role')
                                    ->icon(Heroicon::OutlinedIdentification)
                                    ->schema([
                                        Section::make('Role & Priority')
                                            ->schema([
                                                Toggle::make('is_primary')
                                                    ->default(false),
                                                Toggle::make('is_billing_contact')
                                                    ->default(false),
                                            ])
                                            ->columns(1),
                                    ]),
                            ]),
                    ])
                    ->columnSpan([
                        'lg' => 8,
                    ]),
                Group::make()
                    ->schema([
                        MetadataSection::make(ClientContact::class),
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
