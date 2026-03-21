<?php

namespace App\Filament\Resources\TimeEntries\Widgets;

use App\Enums\Currency;
use App\Filament\Resources\TimeEntries\Pages\ListTimeEntries;
use App\Models\TimeEntry;
use App\Support\CurrencyConverter;
use Carbon\CarbonImmutable;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TimeEntryStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected ?string $pollingInterval = null;

    protected function getTablePage(): string
    {
        return ListTimeEntries::class;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $query = $this->getPageTableQuery();
        $now = CarbonImmutable::now();
        $displayCurrency = Currency::userDefault();

        $minutesThisWeek = (int) (clone $query)
            ->whereBetween('started_at', [$now->startOfWeek(), $now->endOfWeek()])
            ->sum('minutes');

        $hoursThisWeek = ((float) $minutesThisWeek) / 60;

        $entriesLastThirtyDays = (clone $query)
            ->where('started_at', '>=', $now->subDays(30))
            ->get(['started_at', 'minutes']);

        $averageHoursPerDay = (float) ($entriesLastThirtyDays
            ->groupBy(function (Model $model): string {
                if (! $model instanceof TimeEntry) {
                    return '__unknown';
                }

                $startedAt = $model->getAttribute('started_at');

                if ($startedAt === null) {
                    return '__unknown';
                }

                return CarbonImmutable::parse((string) $startedAt)->toDateString();
            })
            ->filter(fn ($entries, ?string $date): bool => $date !== null)
            ->map(fn ($entries): float => ((float) $entries->sum('minutes')) / 60)
            ->avg() ?? 0.0);

        $totalEntries = (int) (clone $query)->count();

        $billableEntries = (int) (clone $query)
            ->where(function (Builder $builder): void {
                $builder->where('is_billable_override', true)
                    ->orWhereNull('is_billable_override');
            })
            ->count();

        $billablePercent = $totalEntries > 0
            ? round(($billableEntries / $totalEntries) * 100)
            : 0;

        $totalRevenue = (clone $query)
            ->with(['project.customer', 'project.owner', 'task.project.customer', 'task.owner', 'owner'])
            ->get()
            ->sum(function (Model $model) use ($displayCurrency): float {
                if (! $model instanceof TimeEntry) {
                    return 0.0;
                }

                $amount = $model->calculatedAmount() ?? 0.0;
                $fromCurrency = $model->effectiveCurrency();

                if ($amount <= 0 || $fromCurrency === null) {
                    return $amount;
                }

                return CurrencyConverter::convert($amount, $fromCurrency, $displayCurrency->value);
            });

        return [
            Stat::make(__('Average Hours/Day (30d)'), number_format($averageHoursPerDay, 1, '.', ' ').' '.__('h'))
                ->color('warning'),
            Stat::make(__('Hours This Week'), number_format($hoursThisWeek, 1, '.', ' ')),
            Stat::make(__('Billable %'), $billablePercent.'%')
                ->color('info'),
            Stat::make(__('Total Revenue'), CurrencyConverter::format($totalRevenue, $displayCurrency->value, 0))
                ->color('primary'),
        ];
    }
}
