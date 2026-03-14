<?php

namespace App\Filament\Resources\Projects\Pages;

use App\Filament\Resources\Projects\ProjectResource;
use App\Models\Project;
use App\Models\ProjectStatusOption;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $ownerId = Filament::auth()->id();
        $definitions = ProjectStatusOption::definitionsForOwner($ownerId);
        $openStatusCodes = ProjectStatusOption::openCodesForOwner($ownerId);
        $tabs = [
            'delivery' => Tab::make('Delivery')
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->badge($this->countByStatuses($openStatusCodes))
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', $openStatusCodes)),
        ];

        foreach ($definitions as $definition) {
            $statusCode = (string) $definition['code'];
            $statusLabel = (string) $definition['label'];
            $statusColor = (string) $definition['color'];

            $tabs[$statusCode] = Tab::make($statusLabel)
                ->icon($this->statusTabIcon($definition))
                ->badge($this->countByStatuses([$statusCode]))
                ->badgeColor($statusColor)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', $statusCode));
        }

        $tabs['all'] = Tab::make('All')
            ->icon(Heroicon::OutlinedQueueList)
            ->badge($this->baseQuery()->count());

        return $tabs;
    }

    public function getDefaultActiveTab(): string|int|null
    {
        $ownerId = Filament::auth()->id();
        $openStatusCodes = ProjectStatusOption::openCodesForOwner($ownerId);

        if ($openStatusCodes !== []) {
            return 'delivery';
        }

        return ProjectStatusOption::defaultCodeForOwner($ownerId);
    }

    /**
     * @param  list<string>  $statusCodes
     */
    private function countByStatuses(array $statusCodes): int
    {
        return $this->baseQuery()
            ->whereIn('status', $statusCodes)
            ->count();
    }

    private function baseQuery(): Builder
    {
        $ownerId = Filament::auth()->id();

        return Project::query()
            ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId));
    }

    /**
     * @param  array{
     *     code: string,
     *     label: string,
     *     color: string,
     *     icon: string|null,
     *     sort_order: int,
     *     is_default: bool,
     *     is_open: bool,
     *     is_trackable: bool
     * }  $definition
     */
    private function statusTabIcon(array $definition): Heroicon|string
    {
        $customIcon = $definition['icon'];

        if (is_string($customIcon) && $customIcon !== '') {
            return $customIcon;
        }

        if (! $definition['is_open'] && ! $definition['is_trackable']) {
            return Heroicon::OutlinedNoSymbol;
        }

        if (! $definition['is_open']) {
            return Heroicon::OutlinedCheckCircle;
        }

        if ($definition['color'] === 'danger') {
            return Heroicon::OutlinedExclamationTriangle;
        }

        if ($definition['color'] === 'warning') {
            return Heroicon::OutlinedPlayCircle;
        }

        if ($definition['is_trackable']) {
            return Heroicon::OutlinedCalendarDays;
        }

        return Heroicon::OutlinedAdjustmentsHorizontal;
    }
}
