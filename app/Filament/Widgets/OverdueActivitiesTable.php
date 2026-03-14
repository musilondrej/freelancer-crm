<?php

namespace App\Filament\Widgets;

use App\Models\ProjectActivity;
use App\Models\ProjectActivityStatusOption;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OverdueActivitiesTable extends TableWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = -5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $ownerId = Filament::auth()->id();
        [$startDate, $endDate] = $this->resolvedDateRange();
        $openStatuses = ProjectActivityStatusOption::openCodesForOwner($ownerId);

        return $table
            ->query(fn (): Builder => ProjectActivity::query()
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', today())
                ->whereIn('status', $openStatuses)
                ->when(
                    $startDate !== null && $endDate !== null,
                    fn (Builder $query): Builder => $query->whereBetween('due_date', [$startDate->toDateString(), $endDate->toDateString()]),
                )
                ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId))
                ->with(['project:id,name']))
            ->heading('Flagged Activities')
            ->columns([
                TextColumn::make('title')
                    ->label('Activity')
                    ->searchable()
                    ->limit(45),
                TextColumn::make('project.name')
                    ->label('Project')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('due_date')
                    ->label('Due')
                    ->date('d.m.Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('type')
                    ->badge(),
            ])
            ->defaultSort('due_date')
            ->paginated([5, 10, 25]);
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
