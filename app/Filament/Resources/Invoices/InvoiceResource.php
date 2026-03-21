<?php

namespace App\Filament\Resources\Invoices;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Filament\Resources\Invoices\Tables\InvoicesTable;
use App\Models\Invoice;
use App\Support\Filament\FilteredByOwner;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Reports;

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('Invoices');
    }

    public static function getModelLabel(): string
    {
        return __('Invoice');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Invoices');
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return FilteredByOwner::applyTo(
            parent::getEloquentQuery()->with(['customer', 'project', 'items'])
        );
    }
}
