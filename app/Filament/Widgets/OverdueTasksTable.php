<?php

namespace App\Filament\Widgets;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\UserSetting;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class OverdueTasksTable extends TableWidget
{
    protected static ?int $sort = -5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $ownerId = Filament::auth()->id();
        $dateFormat = UserSetting::dateFormatForUser($ownerId);

        return $table
            ->query(fn (): Builder => Task::query()
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', today())
                ->whereIn('status', TaskStatus::openValues())
                ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId))
                ->with(['project:id,name']))
            ->heading(__('Overdue Tasks'))
            ->columns([
                TextColumn::make('title')
                    ->label(__('Task'))
                    ->searchable()
                    ->limit(45),
                TextColumn::make('project.name')
                    ->label(__('Project'))
                    ->searchable()
                    ->placeholder(__('N/A')),
                TextColumn::make('due_date')
                    ->label(__('Due'))
                    ->date($dateFormat)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('billing_model')
                    ->label(__('Billing'))
                    ->badge(),
            ])
            ->defaultSort('due_date')
            ->paginated([5, 10, 25]);
    }
}
