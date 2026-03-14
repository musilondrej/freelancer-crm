<?php

namespace App\Filament\Widgets;

use App\Enums\LeadStatus;
use App\Enums\ProjectActivityType;
use App\Filament\Widgets\Concerns\InteractsWithCurrencyConversion;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectActivityStatusOption;
use App\Models\ProjectStatusOption;
use App\Models\Worklog;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Cache;

class DashboardMetricsBoard extends BaseWidget
{
    use InteractsWithCurrencyConversion;
    use InteractsWithPageFilters;

    protected static ?int $sort = -11;

    protected ?string $heading = 'Overview';

    protected ?string $pollingInterval = '60s';

    /**
     * @var int | array<string, ?int> | null
     */
    protected int|array|null $columns = [
        'md' => 2,
        'xl' => 4,
    ];

    /**
     * @var list<string>
     */
    public array $metricKeys = [];

    /**
     * @var array<string, float|int|string|null>|null
     */
    private ?array $snapshotCache = null;

    /**
     * @return array<string, string>
     */
    public static function metricOptions(): array
    {
        return [
            'revenue_month' => 'Revenue this month',
            'revenue_week' => 'Revenue this week',
            'revenue_today' => 'Revenue today',
            'unbilled_done' => 'Unbilled done work',
            'money_in_flight' => 'Money in flight',
            'utilization_month' => 'Billable utilization (month)',
            'billable_hours_month' => 'Billable hours (month)',
            'worked_hours_month' => 'Worked hours (month)',
            'open_projects' => 'Open projects',
            'overdue_activities' => 'Overdue worklogs',
            'open_leads' => 'Open leads',
        ];
    }

    /**
     * @return list<string>
     */
    public static function defaultMetricKeys(): array
    {
        return [
            'revenue_month',
            'revenue_week',
            'revenue_today',
            'unbilled_done',
            'money_in_flight',
            'utilization_month',
            'overdue_activities',
        ];
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $snapshot = $this->snapshot();

        return array_map(
            fn (string $metricKey): Stat => $this->buildStat($metricKey, $snapshot),
            $this->resolvedMetricKeys(),
        );
    }

    /**
     * @return list<string>
     */
    private function resolvedMetricKeys(): array
    {
        $validMetricKeys = array_keys(self::metricOptions());

        $selectedMetricKeys = array_values(array_filter(
            $this->metricKeys,
            fn (string $metricKey): bool => in_array($metricKey, $validMetricKeys, true),
        ));

        if ($selectedMetricKeys === []) {
            return self::defaultMetricKeys();
        }

        return $selectedMetricKeys;
    }

    /**
     * @param  array<string, float|int|string|null>  $snapshot
     */
    private function buildStat(string $metricKey, array $snapshot): Stat
    {
        return match ($metricKey) {
            'revenue_month' => Stat::make('Revenue This Month', $this->formatMoneyMetric($snapshot['revenue_month']))
                ->icon('heroicon-o-banknotes')
                ->color('warning'),
            'revenue_week' => Stat::make('Revenue This Week', $this->formatMoneyMetric($snapshot['revenue_week']))
                ->icon('heroicon-o-banknotes')
                ->color('info'),
            'revenue_today' => Stat::make('Revenue Today', $this->formatMoneyMetric($snapshot['revenue_today']))
                ->icon('heroicon-o-banknotes')
                ->color('success'),
            'unbilled_done' => Stat::make('Unbilled Done Work', $this->formatMoneyMetric($snapshot['unbilled_done']))
                ->icon('heroicon-o-receipt-percent')
                ->color('danger'),
            'money_in_flight' => Stat::make('Money In Flight', $this->formatMoneyMetric($snapshot['money_in_flight']))
                ->icon('heroicon-o-cloud')
                ->color('warning'),
            'utilization_month' => Stat::make('Billable Utilization', $this->formatPercentageMetric($snapshot['utilization_month']))
                ->icon('heroicon-o-chart-pie')
                ->color('info'),
            'billable_hours_month' => Stat::make('Billable Hours This Month', $this->formatHoursMetric($snapshot['billable_hours_month']))
                ->icon('heroicon-o-clock')
                ->color('primary'),
            'worked_hours_month' => Stat::make('Worked Hours This Month', $this->formatHoursMetric($snapshot['worked_hours_month']))
                ->icon('heroicon-o-clock')
                ->color('gray'),
            'open_projects' => Stat::make('Open Projects', $this->formatCountMetric($snapshot['open_projects']))
                ->icon('heroicon-o-briefcase')
                ->color('warning'),
            'overdue_activities' => Stat::make('Overdue Worklogs', $this->formatCountMetric($snapshot['overdue_activities']))
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
            'open_leads' => Stat::make('Open Leads', $this->formatCountMetric($snapshot['open_leads']))
                ->icon('heroicon-o-sparkles')
                ->color('gray'),
            default => Stat::make('Metric', '-')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray'),
        };
    }

    /**
     * @return array<string, float|int|string|null>
     */
    private function snapshot(): array
    {
        if ($this->snapshotCache !== null) {
            return $this->snapshotCache;
        }

        $ownerId = Filament::auth()->id();
        $displayCurrency = $this->resolveDisplayCurrency();

        if ($ownerId === null) {
            return $this->snapshotCache = $this->emptySnapshot($displayCurrency);
        }

        $cacheKey = sprintf(
            'dashboard.metrics.owner.%d.currency.%s',
            $ownerId,
            strtolower($displayCurrency),
        );

        return $this->snapshotCache = Cache::remember(
            $cacheKey,
            now()->addSeconds(60),
            fn (): array => $this->buildSnapshot(),
        );
    }

    /**
     * @return array<string, float|int|string|null>
     */
    private function buildSnapshot(): array
    {
        $ownerId = Filament::auth()->id();
        $displayCurrency = $this->resolveDisplayCurrency();

        if ($ownerId === null) {
            return $this->emptySnapshot($displayCurrency);
        }

        $now = CarbonImmutable::now();
        $startOfDay = $now->startOfDay();
        $startOfWeek = $now->startOfWeek();
        $startOfMonth = $now->startOfMonth();
        $startOfYear = $now->startOfYear();

        $revenueToday = 0.0;
        $revenueWeek = 0.0;
        $revenueMonth = 0.0;
        $unbilledDone = 0.0;
        $moneyInFlight = 0.0;
        $workedHoursMonth = 0.0;
        $billableHoursMonth = 0.0;

        $yearlyBillableActivities = $this->loadBillableDoneActivitiesForRange(
            $ownerId,
            $startOfYear,
            $now,
        );

        $yearlyBillableActivities->each(function (Worklog $activity) use (
            $displayCurrency,
            $startOfDay,
            $startOfWeek,
            $startOfMonth,
            &$revenueToday,
            &$revenueWeek,
            &$revenueMonth,
            &$billableHoursMonth,
        ): void {
            $finishedAt = $this->resolvedActivityDate($activity->getAttribute('finished_at'));

            if (! $finishedAt instanceof CarbonImmutable) {
                return;
            }

            $amount = $this->resolvedConvertedAmount($activity, $displayCurrency);

            if ($amount === null) {
                return;
            }

            if ($finishedAt->greaterThanOrEqualTo($startOfMonth)) {
                $revenueMonth += $amount;

                if ($this->isHourlyActivity($activity)) {
                    $hours = $this->resolvedHourlyAmount($activity);

                    if ($hours > 0) {
                        $billableHoursMonth += $hours;
                    }
                }
            }

            if ($finishedAt->greaterThanOrEqualTo($startOfWeek)) {
                $revenueWeek += $amount;
            }

            if ($finishedAt->greaterThanOrEqualTo($startOfDay)) {
                $revenueToday += $amount;
            }
        });

        $this->loadReadyToInvoiceActivities($ownerId)
            ->each(function (Worklog $activity) use ($displayCurrency, &$unbilledDone): void {
                $amount = $this->resolvedConvertedAmount($activity, $displayCurrency);

                if ($amount !== null) {
                    $unbilledDone += $amount;
                }
            });

        $this->loadPipelineActivities($ownerId)->each(function (Worklog $activity) use ($displayCurrency, &$moneyInFlight): void {
            $amount = $this->resolvedConvertedAmount($activity, $displayCurrency);

            if ($amount !== null) {
                $moneyInFlight += $amount;
            }
        });

        $this->loadWorkedHourlyActivitiesForRange($ownerId, $startOfMonth, $now)
            ->each(function (Worklog $activity) use (&$workedHoursMonth): void {
                $hours = $this->resolvedHourlyAmount($activity);

                if ($hours > 0) {
                    $workedHoursMonth += $hours;
                }
            });

        $utilization = $workedHoursMonth > 0
            ? ($billableHoursMonth / $workedHoursMonth) * 100
            : null;

        return [
            'display_currency' => $displayCurrency,
            'revenue_today' => $revenueToday,
            'revenue_week' => $revenueWeek,
            'revenue_month' => $revenueMonth,
            'unbilled_done' => $unbilledDone,
            'money_in_flight' => $moneyInFlight,
            'worked_hours_month' => $workedHoursMonth,
            'billable_hours_month' => $billableHoursMonth,
            'utilization_month' => $utilization,
            'open_projects' => $this->openProjectsCount($ownerId),
            'open_leads' => $this->openLeadsCount($ownerId),
            'overdue_activities' => $this->overdueActivitiesCount($ownerId),
        ];
    }

    /**
     * @return array<string, float|int|string|null>
     */
    private function emptySnapshot(string $displayCurrency): array
    {
        return [
            'display_currency' => $displayCurrency,
            'revenue_today' => 0.0,
            'revenue_week' => 0.0,
            'revenue_month' => 0.0,
            'unbilled_done' => 0.0,
            'money_in_flight' => 0.0,
            'worked_hours_month' => 0.0,
            'billable_hours_month' => 0.0,
            'utilization_month' => null,
            'open_projects' => 0,
            'open_leads' => 0,
            'overdue_activities' => 0,
        ];
    }

    private function formatMoneyMetric(float|int|string|null $value): string
    {
        if (! is_numeric($value)) {
            return '-';
        }

        return $this->formatAmountWithCurrency((float) $value);
    }

    private function formatHoursMetric(float|int|string|null $value): string
    {
        if (! is_numeric($value)) {
            return '0 h';
        }

        return number_format(round((float) $value), 0, '.', ' ').' h';
    }

    private function formatPercentageMetric(float|int|string|null $value): string
    {
        if (! is_numeric($value)) {
            return '-';
        }

        return number_format(round((float) $value), 0, '.', ' ').' %';
    }

    private function formatCountMetric(float|int|string|null $value): string
    {
        if (! is_numeric($value)) {
            return '0';
        }

        return number_format((int) round((float) $value), 0, '.', ' ');
    }

    /**
     * @return EloquentCollection<int, Worklog>
     */
    private function loadBillableDoneActivitiesForRange(
        int $ownerId,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): EloquentCollection {
        return $this->amountActivityQuery($ownerId)
            ->whereIn('status', $this->doneActivityStatusCodes($ownerId))
            ->where('is_billable', true)
            ->whereNotNull('finished_at')
            ->whereBetween('finished_at', [$startDate, $endDate])
            ->get();
    }

    /**
     * @return EloquentCollection<int, Worklog>
     */
    private function loadReadyToInvoiceActivities(int $ownerId): EloquentCollection
    {
        return $this->amountActivityQuery($ownerId)
            ->readyToInvoice($ownerId)
            ->whereNotNull('finished_at')
            ->get();
    }

    /**
     * @return EloquentCollection<int, Worklog>
     */
    private function loadPipelineActivities(int $ownerId): EloquentCollection
    {
        return $this->amountActivityQuery($ownerId)
            ->whereIn('status', $this->openActivityStatusCodes($ownerId))
            ->where('is_billable', true)
            ->get();
    }

    /**
     * @return EloquentCollection<int, Worklog>
     */
    private function loadWorkedHourlyActivitiesForRange(
        int $ownerId,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): EloquentCollection {
        return Worklog::query()
            ->where('owner_id', $ownerId)
            ->whereIn('status', $this->doneActivityStatusCodes($ownerId))
            ->where('type', ProjectActivityType::Hourly->value)
            ->whereNotNull('finished_at')
            ->whereBetween('finished_at', [$startDate, $endDate])
            ->select([
                'type',
                'tracked_minutes',
                'quantity',
                'finished_at',
                'is_billable',
            ])
            ->get();
    }

    /**
     * @return Builder<Worklog>
     */
    private function amountActivityQuery(int $ownerId): Builder
    {
        return Worklog::query()
            ->where('owner_id', $ownerId)
            ->with([
                'project:id,owner_id,client_id,currency,hourly_rate',
                'project.customer:id,owner_id,billing_currency,hourly_rate',
                'project.customer.owner:id,default_currency,default_hourly_rate',
            ])
            ->select([
                'project_id',
                'type',
                'status',
                'is_billable',
                'is_invoiced',
                'invoice_reference',
                'invoiced_at',
                'currency',
                'quantity',
                'unit_rate',
                'flat_amount',
                'tracked_minutes',
                'finished_at',
            ]);
    }

    private function resolvedConvertedAmount(Worklog $activity, string $displayCurrency): ?float
    {
        $amount = $activity->calculatedAmount();
        $currency = $activity->effectiveCurrency();

        if ($amount === null || $currency === null) {
            return null;
        }

        return $this->convertAmount($amount, $currency, $displayCurrency);
    }

    private function resolvedActivityDate(mixed $rawDate): ?CarbonImmutable
    {
        if ($rawDate === null) {
            return null;
        }

        if ($rawDate instanceof CarbonImmutable) {
            return $rawDate;
        }

        if ($rawDate instanceof CarbonInterface) {
            return CarbonImmutable::instance($rawDate);
        }

        return CarbonImmutable::parse((string) $rawDate);
    }

    private function isHourlyActivity(Worklog $activity): bool
    {
        $type = $activity->getAttribute('type');

        if ($type instanceof ProjectActivityType) {
            return $type === ProjectActivityType::Hourly;
        }

        return (string) $type === ProjectActivityType::Hourly->value;
    }

    private function resolvedHourlyAmount(Worklog $activity): float
    {
        if (! $this->isHourlyActivity($activity)) {
            return 0.0;
        }

        if ($activity->tracked_minutes !== null) {
            return max(((float) $activity->tracked_minutes) / 60, 0.0);
        }

        if ($activity->quantity !== null) {
            return max((float) $activity->quantity, 0.0);
        }

        return 0.0;
    }

    private function openProjectsCount(int $ownerId): int
    {
        return Project::query()
            ->where('owner_id', $ownerId)
            ->whereIn('status', ProjectStatusOption::openCodesForOwner($ownerId))
            ->count();
    }

    private function openLeadsCount(int $ownerId): int
    {
        return Lead::query()
            ->where('owner_id', $ownerId)
            ->whereIn('status', [
                LeadStatus::New->value,
                LeadStatus::Contacted->value,
                LeadStatus::Qualified->value,
                LeadStatus::Proposal->value,
            ])
            ->count();
    }

    private function overdueActivitiesCount(int $ownerId): int
    {
        return Worklog::query()
            ->where('owner_id', $ownerId)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereIn('status', $this->openActivityStatusCodes($ownerId))
            ->count();
    }

    /**
     * @return list<string>
     */
    private function doneActivityStatusCodes(int $ownerId): array
    {
        return ProjectActivityStatusOption::doneCodesForOwner($ownerId);
    }

    /**
     * @return list<string>
     */
    private function openActivityStatusCodes(int $ownerId): array
    {
        return ProjectActivityStatusOption::openCodesForOwner($ownerId);
    }
}
