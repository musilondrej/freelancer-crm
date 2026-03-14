<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Worklogs\WorklogResource;
use App\Filament\Widgets\Concerns\InteractsWithCurrencyConversion;
use App\Models\UserSetting;
use App\Models\Worklog;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class UnbilledDoneWorkTable extends TableWidget
{
    use InteractsWithCurrencyConversion;
    use InteractsWithPageFilters;

    protected static ?int $sort = -4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        [$startDate, $endDate] = $this->resolvedDateRange();
        $ownerId = Filament::auth()->id();
        $dateTimeFormat = UserSetting::dateTimeFormatForUser($ownerId);
        $timezone = UserSetting::timezoneForUser($ownerId);

        return $table
            ->query(fn (): Builder => Worklog::query()
                ->readyToInvoice($ownerId)
                ->whereNotNull('finished_at')
                ->when(
                    $startDate !== null && $endDate !== null,
                    fn (Builder $query): Builder => $query->whereBetween('finished_at', [$startDate, $endDate]),
                )
                ->with([
                    'project:id,name,client_id,owner_id,currency,hourly_rate',
                    'project.customer:id,name,owner_id,billing_currency,hourly_rate',
                    'project.customer.owner:id,default_currency,default_hourly_rate',
                ]))
            ->columns([
                TextColumn::make('title')
                    ->label('Worklog')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('project.name')
                    ->label('Project')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('project.customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->placeholder('-')
                    ->limit(40),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->state(function (Worklog $record): string {
                        $amount = $record->calculatedAmount();
                        $currency = $record->effectiveCurrency();

                        if ($amount === null || $currency === null) {
                            return '-';
                        }

                        $converted = $this->convertAmount($amount, $currency);

                        return $this->formatAmountWithCurrency($converted);
                    }),
                TextColumn::make('finished_at')
                    ->label('Done at')
                    ->dateTime($dateTimeFormat, timezone: $timezone)
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Worklog $record): string => WorklogResource::getUrl('edit', ['record' => $record])),
            ])
            ->defaultSort('finished_at', 'desc')
            ->paginated([5, 10, 25]);
    }

    public function getHeading(): ?string
    {
        return 'Unbilled Done Worklogs';
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: ?CarbonImmutable}
     */
    private function resolvedDateRange(): array
    {
        $rawStartDate = $this->pageFilters['startDate'] ?? null;
        $rawEndDate = $this->pageFilters['endDate'] ?? null;

        if (! is_string($rawStartDate) || ! is_string($rawEndDate)) {
            return [null, null];
        }

        $startDate = CarbonImmutable::parse($rawStartDate)->startOfDay();
        $endDate = CarbonImmutable::parse($rawEndDate)->endOfDay();

        if ($startDate->greaterThan($endDate)) {
            return [null, null];
        }

        return [$startDate, $endDate];
    }
}
