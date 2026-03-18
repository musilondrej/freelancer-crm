<?php

namespace App\Filament\Widgets;

use App\Enums\Currency;
use App\Enums\LeadStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskBillingModel;
use App\Enums\TaskStatus;
use App\Filament\Widgets\Concerns\InteractsWithCurrencyConversion;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Task;
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

    protected function getHeading(): ?string
    {
        return __('Overview');
    }

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
            'revenue_month' => __('Revenue this month'),
            'revenue_week' => __('Revenue this week'),
            'revenue_today' => __('Revenue today'),
            'unbilled_done' => __('Unbilled done work'),
            'money_in_flight' => __('Money In flight'),
            'billable_hours_month' => __('Billable hours (month)'),
            'worked_hours_month' => __('Worked hours (month)'),
            'open_projects' => __('Open projects'),
            'overdue_activities' => __('Overdue tasks'),
            'open_leads' => __('Open leads'),
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
            'revenue_month' => Stat::make(__('Revenue this month'), $this->formatMoneyMetric($snapshot['revenue_month']))
                ->icon('heroicon-o-banknotes')
                ->color('warning'),
            'revenue_week' => Stat::make(__('Revenue this week'), $this->formatMoneyMetric($snapshot['revenue_week']))
                ->icon('heroicon-o-banknotes')
                ->color('info'),
            'revenue_today' => Stat::make(__('Revenue today'), $this->formatMoneyMetric($snapshot['revenue_today']))
                ->icon('heroicon-o-banknotes')
                ->color('success'),
            'unbilled_done' => Stat::make(__('Unbilled done work'), $this->formatMoneyMetric($snapshot['unbilled_done']))
                ->icon('heroicon-o-receipt-percent')
                ->color('danger'),
            'money_in_flight' => Stat::make(__('Money In flight'), $this->formatMoneyMetric($snapshot['money_in_flight']))
                ->icon('heroicon-o-cloud')
                ->color('warning'),
            'billable_hours_month' => Stat::make(__('Billable hours this month'), $this->formatHoursMetric($snapshot['billable_hours_month']))
                ->icon('heroicon-o-clock')
                ->color('primary'),
            'worked_hours_month' => Stat::make(__('Worked hours this month'), $this->formatHoursMetric($snapshot['worked_hours_month']))
                ->icon('heroicon-o-clock')
                ->color('gray'),
            'open_projects' => Stat::make(__('Open projects'), $this->formatCountMetric($snapshot['open_projects']))
                ->icon('heroicon-o-briefcase')
                ->color('warning'),
            'overdue_activities' => Stat::make(__('Overdue tasks'), $this->formatCountMetric($snapshot['overdue_activities']))
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
            'open_leads' => Stat::make(__('Open leads'), $this->formatCountMetric($snapshot['open_leads']))
                ->icon('heroicon-o-sparkles')
                ->color('gray'),
            default => Stat::make(__('Metric'), '-')
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
            strtolower($displayCurrency->value),
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

        $yearlyBillableTasks = $this->loadBillableDoneTasksForRange(
            $ownerId,
            $startOfYear,
            $now,
        );

        $yearlyBillableTasks->each(function (Task $task) use (
            $displayCurrency,
            $startOfDay,
            $startOfWeek,
            $startOfMonth,
            &$revenueToday,
            &$revenueWeek,
            &$revenueMonth,
            &$billableHoursMonth,
        ): void {
            $completedAt = $this->resolvedTaskDate($task->getAttribute('completed_at'));

            if (! $completedAt instanceof CarbonImmutable) {
                return;
            }

            $amount = $this->resolvedConvertedAmount($task, $displayCurrency);

            if ($amount === null) {
                return;
            }

            if ($completedAt->greaterThanOrEqualTo($startOfMonth)) {
                $revenueMonth += $amount;

                if ($this->isHourlyTask($task)) {
                    $hours = $this->resolvedHourlyAmount($task, billableOnly: true);

                    if ($hours > 0) {
                        $billableHoursMonth += $hours;
                    }
                }
            }

            if ($completedAt->greaterThanOrEqualTo($startOfWeek)) {
                $revenueWeek += $amount;
            }

            if ($completedAt->greaterThanOrEqualTo($startOfDay)) {
                $revenueToday += $amount;
            }
        });

        $this->loadReadyToInvoiceTasks($ownerId)
            ->each(function (Task $task) use ($displayCurrency, &$unbilledDone): void {
                $amount = $this->resolvedConvertedAmount($task, $displayCurrency);

                if ($amount !== null) {
                    $unbilledDone += $amount;
                }
            });

        $this->loadPipelineTasks($ownerId)->each(function (Task $task) use ($displayCurrency, &$moneyInFlight): void {
            $amount = $this->resolvedConvertedAmount($task, $displayCurrency);

            if ($amount !== null) {
                $moneyInFlight += $amount;
            }
        });

        $this->loadWorkedHourlyTasksForRange($ownerId, $startOfMonth, $now)
            ->each(function (Task $task) use (&$workedHoursMonth): void {
                $hours = $this->resolvedHourlyAmount($task);

                if ($hours > 0) {
                    $workedHoursMonth += $hours;
                }
            });

        $utilization = $workedHoursMonth > 0
            ? ($billableHoursMonth / $workedHoursMonth) * 100
            : null;

        return [
            'display_currency' => $displayCurrency->value,
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
    private function emptySnapshot(Currency $displayCurrency): array
    {
        return [
            'display_currency' => $displayCurrency->value,
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

    private function formatCountMetric(float|int|string|null $value): string
    {
        if (! is_numeric($value)) {
            return '0';
        }

        return number_format((int) round((float) $value), 0, '.', ' ');
    }

    /**
     * @return EloquentCollection<int, Task>
     */
    private function loadBillableDoneTasksForRange(
        int $ownerId,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): EloquentCollection {
        return $this->amountActivityQuery($ownerId)
            ->whereIn('status', $this->doneTaskStatusCodes())
            ->where('is_billable', true)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->get();
    }

    /**
     * @return EloquentCollection<int, Task>
     */
    private function loadReadyToInvoiceTasks(int $ownerId): EloquentCollection
    {
        return $this->amountActivityQuery($ownerId)
            ->readyToInvoice($ownerId)
            ->whereNotNull('completed_at')
            ->get();
    }

    /**
     * @return EloquentCollection<int, Task>
     */
    private function loadPipelineTasks(int $ownerId): EloquentCollection
    {
        return $this->amountActivityQuery($ownerId)
            ->whereIn('status', $this->openTaskStatusCodes())
            ->where('is_billable', true)
            ->get();
    }

    /**
     * @return EloquentCollection<int, Task>
     */
    private function loadWorkedHourlyTasksForRange(
        int $ownerId,
        CarbonImmutable $startDate,
        CarbonImmutable $endDate,
    ): EloquentCollection {
        return Task::query()
            ->where('owner_id', $ownerId)
            ->whereIn('status', $this->doneTaskStatusCodes())
            ->where('billing_model', TaskBillingModel::Hourly->value)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$startDate, $endDate])
            ->with('timeEntries:id,task_id,is_billable_override,minutes')
            ->select([
                'billing_model',
                'quantity',
                'completed_at',
                'is_billable',
            ])
            ->get();
    }

    /**
     * @return Builder<Task>
     */
    private function amountActivityQuery(int $ownerId): Builder
    {
        return Task::query()
            ->where('owner_id', $ownerId)
            ->with([
                'project:id,owner_id,customer_id,currency,hourly_rate',
                'project.customer:id,owner_id,billing_currency,hourly_rate',
                'project.customer.owner:id,default_currency,default_hourly_rate',
                'timeEntries:id,task_id,is_billable_override,minutes',
            ])
            ->select([
                'project_id',
                'billing_model',
                'status',
                'is_billable',
                'currency',
                'quantity',
                'hourly_rate_override',
                'fixed_price',
                'completed_at',
            ]);
    }

    private function resolvedConvertedAmount(Task $task, Currency $displayCurrency): ?float
    {
        $amount = $task->calculatedAmount();
        $currency = $task->effectiveCurrency();

        if ($amount === null || $currency === null) {
            return null;
        }

        return $this->convertAmount($amount, $currency, $displayCurrency);
    }

    private function resolvedTaskDate(mixed $rawDate): ?CarbonImmutable
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

    private function isHourlyTask(Task $task): bool
    {
        $billingModel = $task->getAttribute('billing_model');

        if ($billingModel instanceof TaskBillingModel) {
            return $billingModel === TaskBillingModel::Hourly;
        }

        return (string) $billingModel === TaskBillingModel::Hourly->value;
    }

    private function resolvedHourlyAmount(Task $task, bool $billableOnly = false): float
    {
        if (! $this->isHourlyTask($task)) {
            return 0.0;
        }

        $trackedMinutes = $billableOnly
            ? $task->billableTrackedMinutes()
            : $task->totalTrackedMinutes();

        if ($trackedMinutes > 0) {
            return max($trackedMinutes / 60, 0.0);
        }

        if ($task->quantity !== null) {
            return max((float) $task->quantity, 0.0);
        }

        return 0.0;
    }

    private function openProjectsCount(int $ownerId): int
    {
        return Project::query()
            ->where('owner_id', $ownerId)
            ->whereIn('status', ProjectStatus::openValues())
            ->count();
    }

    private function openLeadsCount(int $ownerId): int
    {
        return Lead::query()
            ->where('owner_id', $ownerId)
            ->whereIn('status', LeadStatus::openValues())
            ->count();
    }

    private function overdueActivitiesCount(int $ownerId): int
    {
        return Task::query()
            ->where('owner_id', $ownerId)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereIn('status', $this->openTaskStatusCodes())
            ->count();
    }

    /**
     * @return list<string>
     */
    private function doneTaskStatusCodes(): array
    {
        return TaskStatus::doneValues();
    }

    /**
     * @return list<string>
     */
    private function openTaskStatusCodes(): array
    {
        return TaskStatus::openValues();
    }
}
