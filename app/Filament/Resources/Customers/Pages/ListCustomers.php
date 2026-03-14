<?php

namespace App\Filament\Resources\Customers\Pages;

use App\Enums\CustomerStatus;
use App\Filament\Resources\Customers\CustomerResource;
use App\Models\Customer;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

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
                ->icon(Heroicon::OutlinedCheckCircle)
                ->badge($this->countByStatus(CustomerStatus::Active))
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', CustomerStatus::Active->value)),
            'lead' => Tab::make('Lead')
                ->icon(Heroicon::OutlinedBolt)
                ->badge($this->countByStatus(CustomerStatus::Lead))
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', CustomerStatus::Lead->value)),
            'inactive' => Tab::make('Inactive')
                ->icon(Heroicon::OutlinedPauseCircle)
                ->badge($this->countByStatus(CustomerStatus::Inactive))
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', CustomerStatus::Inactive->value)),
            'all' => Tab::make('All')
                ->icon(Heroicon::OutlinedQueueList)
                ->badge($this->baseCustomerQuery()->count()),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'active';
    }

    private function countByStatus(CustomerStatus $status): int
    {
        return $this->baseCustomerQuery()
            ->where('status', $status->value)
            ->count();
    }

    private function baseCustomerQuery(): Builder
    {
        $ownerId = Filament::auth()->id();

        return Customer::query()
            ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId));
    }
}
