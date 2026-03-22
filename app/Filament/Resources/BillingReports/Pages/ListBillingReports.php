<?php

namespace App\Filament\Resources\BillingReports\Pages;

use App\Filament\Resources\BillingReports\BillingReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBillingReports extends ListRecords
{
    protected static string $resource = BillingReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
