<?php

namespace App\Filament\Resources\Projects\Widgets;

use App\Enums\Currency;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Models\Project;
use App\Support\CurrencyConverter;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class ProjectStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected ?string $pollingInterval = null;

    protected function getTablePage(): string
    {
        return ListProjects::class;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $query = $this->getPageTableQuery();
        /** @var Builder<Project> $query */
        $displayCurrency = Currency::userDefault();

        $openProjects = (clone $query)
            ->open()
            ->count();

        $projects = (clone $query)
            ->with('customer')
            ->get(['id', 'customer_id', 'currency', 'estimated_value', 'actual_value', 'estimated_hours', 'actual_hours_minutes']);

        $totalBudget = (float) $projects->sum(function (Project $project) use ($displayCurrency): float {
            $amount = (float) ($project->estimated_value ?? 0);
            $fromCurrency = $project->effectiveCurrency();

            if ($amount <= 0 || $fromCurrency === null) {
                return $amount;
            }

            return CurrencyConverter::convert($amount, $fromCurrency, $displayCurrency->value);
        });

        return [
            Stat::make(__('Open projects'), number_format($openProjects))
                ->color('success'),
            Stat::make(__('Total budget'), CurrencyConverter::format($totalBudget, $displayCurrency->value, 0))
                ->color('info'),
        ];
    }
}
