<?php

namespace App\Filament\Widgets;

use App\Models\TimeEntry;
use App\Support\Filament\FilteredByOwner;
use Carbon\CarbonImmutable;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Facades\Cache;

class WorkHoursTimelineChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = -10;

    protected int|string|array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected ?string $heading = 'Work hours';

    public function getHeading(): ?string
    {
        return __('Work hours');
    }

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
                    'label' => __('Worked hours'),
                    'data' => $series['values'],
                    'backgroundColor' => '#22c55e',
                    'borderColor' => '#4ade80',
                    'borderWidth' => 1,
                    'borderRadius' => 6,
                    'borderSkipped' => false,
                    'maxBarThickness' => 24,
                ],
            ],
            'labels' => $series['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>|RawJs|null
     */
    protected function getOptions(): array|RawJs|null
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
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
                    'ticks' => [
                        'precision' => 1,
                    ],
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
     * @return array{labels: list<string>, values: list<float>}
     */
    private function seriesData(): array
    {
        [$rangeStart, $rangeEnd, $bucket] = $this->resolvedRange();
        $ownerId = FilteredByOwner::ownerId();

        $cacheKey = sprintf(
            'dashboard.work-hours.owner.%s.start.%s.end.%s.bucket.%s',
            $ownerId ?? 'guest',
            $rangeStart->toDateString(),
            $rangeEnd->toDateString(),
            $bucket,
        );

        return Cache::remember($cacheKey, now()->addSeconds(90), function () use ($rangeStart, $rangeEnd, $bucket, $ownerId): array {
            /**
             * @var array<string, array{label: string, hours: float}> $points
             */
            $points = [];
            $cursor = $bucket === 'month'
                ? $rangeStart->startOfMonth()
                : $rangeStart->startOfDay();
            $rangeLimit = $bucket === 'month'
                ? $rangeEnd->startOfMonth()
                : $rangeEnd->startOfDay();

            while ($cursor->lessThanOrEqualTo($rangeLimit)) {
                $bucketKey = $bucket === 'month'
                    ? $cursor->format('Y-m')
                    : $cursor->toDateString();

                $points[$bucketKey] = [
                    'label' => $bucket === 'month'
                        ? $cursor->format('m/Y')
                        : $cursor->format('d.m.'),
                    'hours' => 0.0,
                ];

                $cursor = $bucket === 'month'
                    ? $cursor->addMonth()
                    : $cursor->addDay();
            }

            TimeEntry::query()
                ->whereNotNull('ended_at')
                ->whereBetween('ended_at', [$rangeStart, $rangeEnd])
                ->when($ownerId !== null, fn ($query) => $query->where('owner_id', $ownerId))
                ->select(['ended_at', 'minutes'])
                ->get()
                ->each(function (TimeEntry $timeEntry) use (&$points, $bucket): void {
                    $rawEndedAt = $timeEntry->getAttribute('ended_at');

                    if ($rawEndedAt === null) {
                        return;
                    }

                    $endedAt = CarbonImmutable::parse((string) $rawEndedAt);

                    $bucketKey = $bucket === 'month'
                        ? $endedAt->format('Y-m')
                        : $endedAt->toDateString();

                    if (! array_key_exists($bucketKey, $points)) {
                        return;
                    }

                    $points[$bucketKey]['hours'] += max(((int) ($timeEntry->minutes ?? 0)) / 60, 0.0);
                });

            return [
                'labels' => array_values(array_map(
                    fn (array $point): string => $point['label'],
                    $points,
                )),
                'values' => array_values(array_map(
                    fn (array $point): float => round($point['hours'], 2),
                    $points,
                )),
            ];
        });
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: 'day'|'month'}
     */
    private function resolvedRange(): array
    {
        $rawStartDate = $this->pageFilters['startDate'] ?? null;
        $rawEndDate = $this->pageFilters['endDate'] ?? null;

        $startDate = is_string($rawStartDate)
            ? CarbonImmutable::parse($rawStartDate)->startOfDay()
            : CarbonImmutable::today()->subDays(29)->startOfDay();
        $endDate = is_string($rawEndDate)
            ? CarbonImmutable::parse($rawEndDate)->endOfDay()
            : CarbonImmutable::today()->endOfDay();

        if ($startDate->greaterThan($endDate)) {
            return [
                CarbonImmutable::today()->subDays(29)->startOfDay(),
                CarbonImmutable::today()->endOfDay(),
                'day',
            ];
        }

        $bucket = $startDate->diffInMonths($endDate) >= 4
            ? 'month'
            : 'day';

        return [$startDate, $endDate, $bucket];
    }
}
