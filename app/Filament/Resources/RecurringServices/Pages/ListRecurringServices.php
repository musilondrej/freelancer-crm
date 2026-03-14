<?php

namespace App\Filament\Resources\RecurringServices\Pages;

use App\Enums\RecurringServiceStatus;
use App\Filament\Resources\RecurringServices\RecurringServiceResource;
use App\Models\RecurringService;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListRecurringServices extends ListRecords
{
    protected static string $resource = RecurringServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $today = today();

        return [
            'active' => Tab::make('Active')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->badge($this->countByStatus(RecurringServiceStatus::Active))
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RecurringServiceStatus::Active->value)),
            'overdue' => Tab::make('Overdue')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->badge($this->baseQuery()
                    ->where('status', RecurringServiceStatus::Active->value)
                    ->whereDate('next_due_on', '<', $today->toDateString())
                    ->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', RecurringServiceStatus::Active->value)
                    ->whereDate('next_due_on', '<', $today->toDateString())),
            'paused' => Tab::make('Paused')
                ->icon(Heroicon::OutlinedPauseCircle)
                ->badge($this->countByStatus(RecurringServiceStatus::Paused))
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RecurringServiceStatus::Paused->value)),
            'cancelled' => Tab::make('Cancelled')
                ->icon(Heroicon::OutlinedNoSymbol)
                ->badge($this->countByStatus(RecurringServiceStatus::Cancelled))
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', RecurringServiceStatus::Cancelled->value)),
            'all' => Tab::make('All')
                ->icon(Heroicon::OutlinedQueueList)
                ->badge($this->baseQuery()->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'active';
    }

    private function countByStatus(RecurringServiceStatus $status): int
    {
        return $this->baseQuery()
            ->where('status', $status->value)
            ->count();
    }

    private function baseQuery(): Builder
    {
        $ownerId = Filament::auth()->id();

        return RecurringService::query()
            ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId));
    }
}
