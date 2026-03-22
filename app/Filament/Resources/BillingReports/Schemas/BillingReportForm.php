<?php

namespace App\Filament\Resources\BillingReports\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BillingReportForm
{
    /**
     * @return list<TextInput|Select|Textarea>
     */
    public static function coreFields(bool $live = false): array
    {
        return [
            TextInput::make('title')
                ->label(__('Title'))
                ->required()
                ->maxLength(255)
                ->placeholder(__('e.g. Alpen Digital – March 2026')),

            Select::make('customer_id')
                ->label(__('Customer'))
                ->relationship('customer', 'name')
                ->searchable()
                ->preload()
                ->required()
                ->live($live),

            TextInput::make('reference')
                ->label(__('Invoice reference'))
                ->placeholder(__('e.g. FAK-2026-001'))
                ->maxLength(255)
                ->helperText(__('External invoice number from your accounting tool')),

            Textarea::make('notes')
                ->label(__('Notes'))
                ->rows(3),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Report details'))
                    ->columns(1)
                    ->schema(self::coreFields()),
            ]);
    }
}
