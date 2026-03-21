<?php

namespace App\Filament\Resources\Tasks\Widgets;

use App\Filament\Resources\Tasks\Pages\ListTasks;
use App\Models\Task;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Builder;

class TaskStats extends BaseWidget
{
    use InteractsWithPageTable;

    protected ?string $pollingInterval = null;

    protected function getTablePage(): string
    {
        return ListTasks::class;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        /** @var Builder<Task> $query */
        $query = $this->getPageTableQuery();

        $open = (clone $query)->open()->count();

        $overdue = (clone $query)
            ->open()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', today())
            ->count();

        $readyToInvoice = (clone $query)->readyToInvoice()->count();

        return [
            Stat::make(__('Open tasks'), number_format($open))
                ->color('primary'),
            Stat::make(__('Overdue tasks'), number_format($overdue))
                ->color($overdue > 0 ? 'danger' : 'gray'),
            Stat::make(__('Ready to invoice'), number_format($readyToInvoice))
                ->color($readyToInvoice > 0 ? 'warning' : 'gray'),
        ];
    }
}
