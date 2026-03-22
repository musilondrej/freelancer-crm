<?php

namespace App\Filament\Resources\BillingReports\Schemas;

use App\Enums\TaskBillingModel;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Support\CurrencyConverter;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CreateBillingReportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('Report details'))
                    ->columns(1)
                    ->schema(BillingReportForm::coreFields(live: true)),

                Section::make(__('Fixed-price tasks'))
                    ->description(__('Each selected task will become one line at its fixed price.'))
                    ->visible(fn (Get $get): bool => filled($get('customer_id')) && self::fixedPriceTaskOptions((int) $get('customer_id')) !== [])
                    ->schema([
                        CheckboxList::make('selected_task_ids')
                            ->hiddenLabel()
                            ->options(fn (Get $get): array => self::fixedPriceTaskOptions((int) $get('customer_id')))
                            ->columns(1)
                            ->bulkToggleable()
                            ->searchable()
                            ->noSearchResultsMessage(__('No fixed-price tasks found for this customer.')),
                    ]),

                Section::make(__('Time entries'))
                    ->description(__('Selected entries will be grouped by task into lines.'))
                    ->visible(fn (Get $get): bool => filled($get('customer_id')) && self::timeEntryOptions((int) $get('customer_id')) !== [])
                    ->schema([
                        CheckboxList::make('selected_time_entry_ids')
                            ->hiddenLabel()
                            ->options(fn (Get $get): array => self::timeEntryOptions((int) $get('customer_id')))
                            ->columns(1)
                            ->bulkToggleable()
                            ->searchable()
                            ->noSearchResultsMessage(__('No billable time entries found for this customer.')),
                    ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function fixedPriceTaskOptions(int $customerId): array
    {
        if ($customerId === 0) {
            return [];
        }

        return Task::query()
            ->where('billing_model', TaskBillingModel::FixedPrice)
            ->where('is_billable', true)
            ->whereDoesntHave('billingReportLine')
            ->whereHas('project', fn (Builder $q): Builder => $q->where('customer_id', $customerId))
            ->with('project')
            ->orderBy('title')
            ->get()
            ->mapWithKeys(fn (Task $task): array => [
                $task->id => sprintf(
                    '%s  ·  %s  ·  %s',
                    $task->title,
                    $task->project->name ?? '?',
                    $task->fixed_price !== null
                        ? CurrencyConverter::format((float) $task->fixed_price, $task->effectiveCurrency() ?? 'EUR')
                        : __('No price set'),
                ),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function timeEntryOptions(int $customerId): array
    {
        if ($customerId === 0) {
            return [];
        }

        return TimeEntry::readyToInvoice()
            ->with(['task', 'project'])
            ->whereHas('project', fn (Builder $q): Builder => $q->where('customer_id', $customerId))
            ->orderBy('project_id')
            ->orderBy('task_id')
            ->oldest('started_at')
            ->get()
            ->mapWithKeys(fn (TimeEntry $entry): array => [
                $entry->id => sprintf(
                    '%s  ·  %s  ·  %s  ·  %s',
                    $entry->task->title ?? __('— No task —'),
                    $entry->project->name ?? '?',
                    $entry->started_at !== null ? CarbonImmutable::parse($entry->started_at)->format('d.m.Y') : '?',
                    number_format($entry->resolvedHours(), 2).'h'
                    .($entry->calculatedAmount() !== null
                        ? '  ('.CurrencyConverter::format($entry->calculatedAmount(), $entry->effectiveCurrency() ?? 'EUR').')'
                        : ''),
                ),
            ])
            ->all();
    }
}
