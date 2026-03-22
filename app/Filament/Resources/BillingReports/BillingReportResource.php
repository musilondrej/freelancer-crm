<?php

namespace App\Filament\Resources\BillingReports;

use App\Enums\NavigationGroup;
use App\Filament\Resources\BillingReports\Pages\CreateBillingReport;
use App\Filament\Resources\BillingReports\Pages\EditBillingReport;
use App\Filament\Resources\BillingReports\Pages\ListBillingReports;
use App\Filament\Resources\BillingReports\RelationManagers\LinesRelationManager;
use App\Filament\Resources\BillingReports\Schemas\BillingReportForm;
use App\Filament\Resources\BillingReports\Tables\BillingReportsTable;
use App\Models\BillingReport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class BillingReportResource extends Resource
{
    protected static ?string $model = BillingReport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Reports;

    protected static ?int $navigationSort = 2;

    public static function getNavigationLabel(): string
    {
        return __('Billing Reports');
    }

    public static function getModelLabel(): string
    {
        return __('Billing Report');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Billing Reports');
    }

    public static function form(Schema $schema): Schema
    {
        return BillingReportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BillingReportsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            LinesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBillingReports::route('/'),
            'create' => CreateBillingReport::route('/create'),
            'edit' => EditBillingReport::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['customer'])
            ->withSum('lines', 'total_amount');
    }

    /**
     * Draft reports can be deleted; finalized ones are locked.
     */
    public static function canDelete(Model $record): bool
    {
        return $record instanceof BillingReport && $record->isDraft();
    }
}
