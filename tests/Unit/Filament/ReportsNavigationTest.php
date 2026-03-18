<?php

use App\Enums\NavigationGroup;
use App\Filament\Resources\Invoices\InvoiceResource;
use App\Filament\Resources\TimeEntries\TimeEntryResource;

test('places invoice and reporting timesheet resources into reports navigation group', function (): void {
    expect(InvoiceResource::getNavigationGroup())->toBe(NavigationGroup::Reports->getLabel())
        ->and(TimeEntryResource::getNavigationGroup())->toBe(NavigationGroup::Reports->getLabel())
        ->and(TimeEntryResource::getNavigationLabel())->toBe(__('Reporting Timesheets'));
});
