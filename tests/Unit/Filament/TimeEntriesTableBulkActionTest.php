<?php

use App\Filament\Resources\TimeEntries\Tables\TimeEntriesTable;
use Filament\Actions\BulkAction;

test('defines a reusable bulk invoice action for time entries', function (): void {
    $action = TimeEntriesTable::invoiceBulkAction();

    expect($action)->toBeInstanceOf(BulkAction::class)
        ->and($action->getName())->toBe('invoice_selected');
});
