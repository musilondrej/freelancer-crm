<?php

namespace App\Filament\Pages\Timesheets;

use App\Filament\Pages\TimesheetShow;
use App\Models\TimeEntry;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TimesheetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(self::invoiceSummaryQuery())
            ->recordUrl(fn (TimeEntry $record): string => TimesheetShow::getUrl([
                'invoiceReference' => $record->invoice_reference,
            ]))
            ->columns(self::columns())
            ->filters(self::filters())
            ->defaultSort('invoiced_at', 'desc')
            ->paginated([10, 25, 50]);
    }

    /**
     * Returns an Eloquent Builder wrapping an aggregated subquery (one row per invoice).
     * The subquery handles GROUP BY, so the outer query can freely ORDER BY any column.
     *
     * @return Builder<TimeEntry>
     */
    private static function invoiceSummaryQuery(): Builder
    {
        $ownerId = auth()->id();

        $subquery = DB::table('time_entries')
            ->select([
                DB::raw('MIN(time_entries.id) as id'),
                'time_entries.invoice_reference',
                DB::raw('MIN(time_entries.invoiced_at) as invoiced_at'),
                DB::raw('COUNT(*) as entries_count'),
                DB::raw('SUM(time_entries.minutes) as total_minutes'),
                DB::raw("STRING_AGG(DISTINCT clients.name, ', ' ORDER BY clients.name) as customer_names"),
            ])
            ->join('projects', 'time_entries.project_id', '=', 'projects.id')
            ->join('clients', 'projects.customer_id', '=', 'clients.id')
            ->where('time_entries.is_invoiced', true)
            ->whereNotNull('time_entries.invoice_reference')
            ->where('time_entries.invoice_reference', '!=', '')
            ->where('time_entries.owner_id', $ownerId)
            ->whereNull('time_entries.deleted_at')
            ->groupBy('time_entries.invoice_reference');

        /** @var Builder<TimeEntry> $query */
        $query = TimeEntry::query()
            ->withoutGlobalScopes()
            ->fromSub($subquery, 'invoice_summaries');

        $query->getModel()->setTable('invoice_summaries');

        return $query;
    }

    /**
     * @return list<TextColumn>
     */
    private static function columns(): array
    {
        return [
            TextColumn::make('invoice_reference')
                ->label(__('Invoice reference'))
                ->searchable()
                ->sortable()
                ->weight('semibold'),
            TextColumn::make('customer_names')
                ->label(__('Customer'))
                ->searchable(),
            TextColumn::make('invoiced_at')
                ->label(__('Invoiced at'))
                ->date()
                ->sortable(),
            TextColumn::make('entries_count')
                ->label(__('Entries'))
                ->numeric()
                ->sortable(),
            TextColumn::make('total_hours')
                ->label(__('Total hours'))
                ->state(fn (TimeEntry $record): string => number_format((int) $record->getAttribute('total_minutes') / 60, 1, '.', ' ').' h')
                ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('total_minutes', $direction)),
        ];
    }

    /**
     * @return list<Filter>
     */
    private static function filters(): array
    {
        return [
            Filter::make('invoiced_period')
                ->form([
                    DatePicker::make('invoiced_from')
                        ->label(__('Invoiced from'))
                        ->native(false),
                    DatePicker::make('invoiced_until')
                        ->label(__('Invoiced until'))
                        ->native(false),
                ])
                ->query(fn (Builder $query, array $data): Builder => $query
                    ->when(
                        $data['invoiced_from'],
                        fn (Builder $builder, string $date): Builder => $builder->whereDate('invoiced_at', '>=', $date)
                    )
                    ->when(
                        $data['invoiced_until'],
                        fn (Builder $builder, string $date): Builder => $builder->whereDate('invoiced_at', '<=', $date)
                    )),
        ];
    }
}
