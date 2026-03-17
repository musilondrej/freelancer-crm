<?php

namespace App\Filament\Widgets;

use App\Enums\ProjectActivityStatus;
use App\Filament\Widgets\Concerns\InteractsWithCurrencyConversion;
use App\Models\Worklog;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class RevenueTrendChart extends ChartWidget
{
    use InteractsWithCurrencyConversion;
    use InteractsWithPageFilters;

    protected static ?int $sort = -9;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected ?string $heading = 'Revenue Trend';

    protected ?string $maxHeight = '360px';

    protected ?string $pollingInterval = '90s';

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function getData(): array
    {
        $series = $this->seriesData();

        return [
            'datasets' => [
                [
                    'label' => __('Current period'),
                    'data' => $series['current'],
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.18)',
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 2,
                    'pointHoverRadius' => 4,
                ],
                [
                    'label' => __('Previous period'),
                    'data' => $series['previous'],
                    'borderColor' => '#94a3b8',
                    'backgroundColor' => 'rgba(148, 163, 184, 0.1)',
                    'fill' => false,
                    'tension' => 0.35,
                    'borderDash' => [6, 4],
                    'pointRadius' => 1,
                ],
            ],
            'labels' => $series['labels'],
        ];
    }

    public function getHeading(): ?string
    {
        return __('Revenue Trend (:currency)', ['currency' => $this->resolveDisplayCurrency()->value]);
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>|RawJs|null
     */
    protected function getOptions(): array|RawJs|null
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                ],
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function getCachedData(): array
    {
        return $this->getData();
    }

    /**
     * @return array{labels: list<string>, current: list<float>, previous: list<float>}
     */
    private function seriesData(): array
    {
        [$currentStart, $currentEnd] = $this->resolvedCurrentRange();
        $displayCurrency = $this->resolveDisplayCurrency();
        $ownerId = Filament::auth()->id();
        $doneStatuses = ProjectActivityStatus::doneValues();
        $cacheKey = sprintf(
            'dashboard.revenue-trend.owner.%s.start.%s.end.%s.currency.%s.statuses.%s',
            $ownerId ?? 'guest',
            $currentStart->toDateString(),
            $currentEnd->toDateString(),
            strtolower($displayCurrency->value),
            md5(implode('|', $doneStatuses)),
        );

        return Cache::remember($cacheKey, now()->addSeconds(90), function () use ($currentStart, $currentEnd, $displayCurrency, $ownerId, $doneStatuses): array {
            $periodDays = max($currentStart->diffInDays($currentEnd) + 1, 1);
            $previousStart = $currentStart->subDays($periodDays);

            /**
             * @var list<string> $labels
             */
            $labels = [];
            $current = array_fill(0, $periodDays, 0.0);
            $previous = array_fill(0, $periodDays, 0.0);

            for ($index = 0; $index < $periodDays; $index++) {
                $labels[] = $currentStart->addDays($index)->format('d.m.');
            }

            Worklog::query()
                ->where('is_billable', true)
                ->whereIn('status', $doneStatuses)
                ->whereNotNull('finished_at')
                ->whereBetween('finished_at', [$previousStart, $currentEnd])
                ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId))
                ->with([
                    'project:id,owner_id,client_id,currency,hourly_rate',
                    'project.customer:id,owner_id,billing_currency,hourly_rate',
                    'project.customer.owner:id,default_currency,default_hourly_rate',
                ])
                ->select([
                    'project_id',
                    'currency',
                    'type',
                    'unit_rate',
                    'quantity',
                    'flat_amount',
                    'tracked_minutes',
                    'is_billable',
                    'finished_at',
                ])
                ->get()
                ->each(function (Worklog $activity) use (
                    $displayCurrency,
                    $currentStart,
                    $previousStart,
                    $periodDays,
                    &$current,
                    &$previous,
                ): void {
                    $rawFinishedAt = $activity->getAttribute('finished_at');

                    if ($rawFinishedAt === null) {
                        return;
                    }

                    $currency = $activity->effectiveCurrency();
                    $amount = $activity->calculatedAmount();

                    if ($amount === null || $currency === null) {
                        return;
                    }

                    $convertedAmount = $this->convertAmount($amount, $currency, $displayCurrency);

                    $finishedAt = $rawFinishedAt instanceof CarbonInterface
                        ? CarbonImmutable::instance($rawFinishedAt)
                        : CarbonImmutable::parse((string) $rawFinishedAt);

                    $currentIndex = (int) $currentStart->diffInDays($finishedAt->startOfDay(), false);

                    if ($currentIndex >= 0 && $currentIndex < $periodDays) {
                        $current[$currentIndex] = ($current[$currentIndex] ?? 0.0) + $convertedAmount;

                        return;
                    }

                    $previousIndex = (int) $previousStart->diffInDays($finishedAt->startOfDay(), false);

                    if ($previousIndex >= 0 && $previousIndex < $periodDays) {
                        $previous[$previousIndex] = ($previous[$previousIndex] ?? 0.0) + $convertedAmount;
                    }
                });

            return [
                'labels' => $labels,
                'current' => array_map(
                    round(...),
                    $current,
                ),
                'previous' => array_map(
                    round(...),
                    $previous,
                ),
            ];
        });
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolvedCurrentRange(): array
    {
        $rawStartDate = $this->pageFilters['startDate'] ?? null;
        $rawEndDate = $this->pageFilters['endDate'] ?? null;
        $fallbackStart = CarbonImmutable::now()->startOfMonth()->startOfDay();
        $fallbackEnd = CarbonImmutable::today()->endOfDay();

        $startDate = is_string($rawStartDate)
            ? CarbonImmutable::parse($rawStartDate)->startOfDay()
            : $fallbackStart;
        $endDate = is_string($rawEndDate)
            ? CarbonImmutable::parse($rawEndDate)->endOfDay()
            : $fallbackEnd;

        if ($startDate->greaterThan($endDate)) {
            return [$fallbackStart, $fallbackEnd];
        }

        return [$startDate, $endDate];
    }
}
