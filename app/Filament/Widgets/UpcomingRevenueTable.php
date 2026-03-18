<?php

namespace App\Filament\Widgets;

use App\Enums\RecurringServiceBillingModel;
use App\Enums\RecurringServiceStatus;
use App\Filament\Resources\RecurringServices\RecurringServiceResource;
use App\Filament\Widgets\Concerns\InteractsWithCurrencyConversion;
use App\Models\RecurringService;
use App\Models\UserSetting;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingRevenueTable extends TableWidget
{
    use InteractsWithCurrencyConversion;
    use InteractsWithPageFilters;

    protected static ?int $sort = -3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        [$rangeStart, $rangeEnd] = $this->resolvedRange();
        $ownerId = Filament::auth()->id();
        $dateFormat = UserSetting::dateFormatForUser($ownerId);

        return $table
            ->query(fn (): Builder => RecurringService::query()
                ->where('status', RecurringServiceStatus::Active)
                ->whereNotNull('next_due_on')
                ->whereBetween('next_due_on', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
                ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId))
                ->where(function (Builder $query) use ($rangeStart): void {
                    $query->whereNull('ends_on')
                        ->orWhereDate('ends_on', '>=', $rangeStart->toDateString());
                })
                ->with([
                    'customer:id,name,owner_id,billing_currency,hourly_rate',
                    'project:id,name,owner_id,customer_id,currency,hourly_rate',
                    'owner:id,default_currency,default_hourly_rate',
                ]))
            ->heading($this->headingForRange($rangeStart, $rangeEnd, $dateFormat))
            ->columns([
                TextColumn::make('name')
                    ->label(__('Service'))
                    ->searchable()
                    ->limit(50),
                TextColumn::make('customer.name')
                    ->label(__('Customer'))
                    ->searchable()
                    ->placeholder(__('N/A'))
                    ->limit(36),
                TextColumn::make('project.name')
                    ->label(__('Project'))
                    ->searchable()
                    ->placeholder(__('N/A'))
                    ->limit(36),
                TextColumn::make('next_due_on')
                    ->label(__('Due'))
                    ->date($dateFormat)
                    ->sortable(),
                TextColumn::make('amount')
                    ->label(__('Expected'))
                    ->state(fn (RecurringService $record): string => $this->formattedExpectedAmount($record)),
            ])
            ->recordActions([
                Action::make('open')
                    ->label(__('Open'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (RecurringService $record): string => RecurringServiceResource::getUrl('edit', ['record' => $record])),
            ])
            ->defaultSort('next_due_on')
            ->paginated([5, 10, 25]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolvedRange(): array
    {
        $rawStartDate = $this->pageFilters['startDate'] ?? null;
        $rawEndDate = $this->pageFilters['endDate'] ?? null;
        $fallbackStart = CarbonImmutable::today()->startOfDay();
        $fallbackEnd = CarbonImmutable::today()->addDays(14)->endOfDay();

        if (! is_string($rawStartDate) || ! is_string($rawEndDate)) {
            return [$fallbackStart, $fallbackEnd];
        }

        $startDate = CarbonImmutable::parse($rawStartDate)->startOfDay();
        $endDate = CarbonImmutable::parse($rawEndDate)->endOfDay();

        if ($startDate->greaterThan($endDate)) {
            return [$fallbackStart, $fallbackEnd];
        }

        return [$startDate, $endDate];
    }

    private function headingForRange(CarbonImmutable $rangeStart, CarbonImmutable $rangeEnd, string $dateFormat): string
    {
        return __('Upcoming revenue (:start - :end)', [
            'start' => $rangeStart->format($dateFormat),
            'end' => $rangeEnd->format($dateFormat),
        ]);
    }

    private function formattedExpectedAmount(RecurringService $service): string
    {
        $amount = $this->expectedAmount($service);

        if ($amount === null) {
            return __('N/A');
        }

        $currency = $service->effectiveCurrency();

        if ($currency === null) {
            return number_format($amount, 0, '.', ' ');
        }

        $converted = $this->convertAmount($amount, $currency);

        return $this->formatAmountWithCurrency($converted);
    }

    private function expectedAmount(RecurringService $service): ?float
    {
        $rawBillingModel = $service->getAttribute('billing_model');
        $billingModel = $rawBillingModel instanceof RecurringServiceBillingModel
            ? $rawBillingModel->value
            : (string) $rawBillingModel;

        if ($billingModel === RecurringServiceBillingModel::Fixed->value) {
            return $service->fixed_amount !== null
                ? (float) $service->fixed_amount
                : null;
        }

        if ($service->hourly_rate === null) {
            return null;
        }

        $quantity = $service->included_quantity !== null
            ? (float) $service->included_quantity
            : 1.0;

        return (float) $service->hourly_rate * $quantity;
    }
}
