<?php

namespace App\Filament\Resources\Leads\Pages;

use App\Enums\LeadStatus;
use App\Filament\Resources\Leads\LeadResource;
use App\Models\Lead;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListLeads extends ListRecords
{
    protected static string $resource = LeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Active')
                ->icon(Heroicon::OutlinedSparkles)
                ->badge($this->countByStatuses([
                    LeadStatus::New,
                    LeadStatus::Contacted,
                    LeadStatus::Qualified,
                    LeadStatus::Proposal,
                ]))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    LeadStatus::New->value,
                    LeadStatus::Contacted->value,
                    LeadStatus::Qualified->value,
                    LeadStatus::Proposal->value,
                ])),
            'new' => Tab::make('New')
                ->icon(Heroicon::OutlinedBellAlert)
                ->badge($this->countByStatuses([LeadStatus::New]))
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', LeadStatus::New->value)),
            'won' => Tab::make('Won')
                ->icon(Heroicon::OutlinedTrophy)
                ->badge($this->countByStatuses([LeadStatus::Won]))
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', LeadStatus::Won->value)),
            'lost' => Tab::make('Lost')
                ->icon(Heroicon::OutlinedXCircle)
                ->badge($this->countByStatuses([LeadStatus::Lost]))
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', LeadStatus::Lost->value)),
            'all' => Tab::make('All')
                ->icon(Heroicon::OutlinedQueueList)
                ->badge($this->baseLeadQuery()->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'active';
    }

    /**
     * @param  array<int, LeadStatus>  $statuses
     */
    private function countByStatuses(array $statuses): int
    {
        return $this->baseLeadQuery()
            ->whereIn('status', array_map(static fn (LeadStatus $status): string => $status->value, $statuses))
            ->count();
    }

    private function baseLeadQuery(): Builder
    {
        $ownerId = Filament::auth()->id();

        return Lead::query()
            ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId));
    }
}
