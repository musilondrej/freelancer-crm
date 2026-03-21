<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Filament\Pages\Timesheets\TimesheetsTable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

class Timesheets extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.timesheets';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Reports;

    protected static ?int $navigationSort = 2;

    public static function getNavigationIcon(): string|Heroicon|null
    {
        return Heroicon::OutlinedDocumentText;
    }

    public static function getNavigationLabel(): string
    {
        return __('Timesheets');
    }

    public function getTitle(): string
    {
        return __('Timesheets');
    }

    public function table(Table $table): Table
    {
        return TimesheetsTable::configure($table);
    }
}
