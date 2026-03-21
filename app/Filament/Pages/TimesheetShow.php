<?php

namespace App\Filament\Pages;

use App\Enums\NavigationGroup;
use App\Filament\Exports\TimeEntryExporter;
use App\Models\TimeEntry;
use Filament\Actions\ExportAction;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Url;
use UnitEnum;

class TimesheetShow extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.timesheet-show';

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Reports;

    protected static bool $shouldRegisterNavigation = false;

    #[Url]
    public string $invoiceReference = '';

    public function getTitle(): string|Htmlable
    {
        return $this->invoiceReference;
    }

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make('export')
                ->label(__('Export'))
                ->exporter(TimeEntryExporter::class)
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->modifyQueryUsing(fn ($query) => $query->where('invoice_reference', $this->invoiceReference)),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TimeEntry::query()
                    ->where('invoice_reference', $this->invoiceReference)
                    ->with(['project.customer', 'task'])
                    ->oldest('started_at')
            )
            ->columns([
                TextColumn::make('project.customer.name')
                    ->label(__('Customer'))
                    ->sortable(),
                TextColumn::make('project.name')
                    ->label(__('Project'))
                    ->sortable(),
                TextColumn::make('task.title')
                    ->label(__('Task'))
                    ->placeholder(__('No task')),
                TextColumn::make('description')
                    ->label(__('Description'))
                    ->placeholder('—')
                    ->limit(60)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('started_at')
                    ->label(__('Date'))
                    ->date()
                    ->sortable(),
                TextColumn::make('hours')
                    ->label(__('Hours'))
                    ->state(fn (TimeEntry $record): string => number_format($record->resolvedHours(), 1, '.', ' ').' h')
                    ->tooltip(fn (TimeEntry $record): string => sprintf('%d min', $record->resolvedMinutes())),
                TextColumn::make('hourly_rate')
                    ->label(__('Rate'))
                    ->state(fn (TimeEntry $record): string => $record->effectiveHourlyRate() !== null
                        ? number_format($record->effectiveHourlyRate(), 0, '.', ' ').' '.($record->effectiveCurrency() ?? '')
                        : '—'),
                TextColumn::make('amount')
                    ->label(__('Amount'))
                    ->state(fn (TimeEntry $record): string => $record->calculatedAmount() !== null
                        ? number_format($record->calculatedAmount(), 2, '.', ' ').' '.($record->effectiveCurrency() ?? '')
                        : '—')
                    ->weight('semibold'),
            ])
            ->striped()
            ->paginated(false);
    }
}
