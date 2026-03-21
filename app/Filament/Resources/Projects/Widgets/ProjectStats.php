<?php

namespace App\Filament\Resources\Projects\Widgets;

use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Models\Project;
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
        /** @var Builder<Project> $query */
        $query = $this->getPageTableQuery();

        $openCount = (clone $query)->open()->count();

        $overdueCount = (clone $query)
            ->open()
            ->whereNotNull('target_end_date')
            ->whereDate('target_end_date', '<', today())
            ->count();

        return [
            Stat::make(__('Open projects'), number_format($openCount))
                ->color('primary'),
            Stat::make(__('Overdue projects'), number_format($overdueCount))
                ->color($overdueCount > 0 ? 'danger' : 'gray'),
        ];
    }
}
