<?php

namespace App\Filament\Widgets;

use App\Models\ProjectActivityStatusOption;
use App\Models\UserSetting;
use App\Models\Worklog;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OverdueActivitiesTable extends TableWidget
{
    protected static ?int $sort = -5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $ownerId = Filament::auth()->id();
        $openStatuses = ProjectActivityStatusOption::openCodesForOwner($ownerId);
        $dateFormat = UserSetting::dateFormatForUser($ownerId);

        return $table
            ->query(fn (): Builder => Worklog::query()
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', today())
                ->whereIn('status', $openStatuses)
                ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId))
                ->with(['project:id,name']))
            ->heading('Overdue Worklogs')
            ->columns([
                TextColumn::make('title')
                    ->label('Worklog')
                    ->searchable()
                    ->limit(45),
                TextColumn::make('project.name')
                    ->label('Project')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('due_date')
                    ->label('Due')
                    ->date($dateFormat)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('type')
                    ->badge(),
            ])
            ->defaultSort('due_date')
            ->paginated([5, 10, 25]);
    }
}
