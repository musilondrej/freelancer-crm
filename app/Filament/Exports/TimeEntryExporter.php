<?php

namespace App\Filament\Exports;

use App\Models\TimeEntry;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class TimeEntryExporter extends Exporter
{
    protected static ?string $model = TimeEntry::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('invoice_reference')
                ->label(__('Invoice reference')),
            ExportColumn::make('invoiced_at')
                ->label(__('Invoiced at')),
            ExportColumn::make('project.customer.name')
                ->label(__('Customer')),
            ExportColumn::make('project.name')
                ->label(__('Project')),
            ExportColumn::make('task.title')
                ->label(__('Task')),
            ExportColumn::make('description')
                ->label(__('Description')),
            ExportColumn::make('started_at')
                ->label(__('Date')),
            ExportColumn::make('hours')
                ->label(__('Hours'))
                ->state(fn (TimeEntry $record): float => $record->resolvedHours()),
            ExportColumn::make('hourly_rate')
                ->label(__('Hourly rate'))
                ->state(fn (TimeEntry $record): ?float => $record->effectiveHourlyRate()),
            ExportColumn::make('amount')
                ->label(__('Amount'))
                ->state(fn (TimeEntry $record): ?float => $record->calculatedAmount()),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = __('Your timesheet export has completed and :count :rows exported.', [
            'count' => Number::format($export->successful_rows),
            'rows' => str('row')->plural($export->successful_rows),
        ]);

        if (($failedRowsCount = $export->getFailedRowsCount()) !== 0) {
            $body .= ' '.__(':count :rows failed to export.', [
                'count' => Number::format($failedRowsCount),
                'rows' => str('row')->plural($failedRowsCount),
            ]);
        }

        return $body;
    }
}
