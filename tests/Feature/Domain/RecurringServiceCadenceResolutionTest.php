<?php

use App\Enums\RecurringServiceCadenceUnit;
use App\Models\RecurringService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('calculates next due date from cadence when missing', function (): void {
    $lastInvoicedOn = CarbonImmutable::today()->subDays(10);

    $service = RecurringService::factory()->create([
        'cadence_unit' => RecurringServiceCadenceUnit::Month,
        'cadence_interval' => 1,
        'starts_on' => $lastInvoicedOn->toDateString(),
        'last_invoiced_on' => $lastInvoicedOn->toDateString(),
        'next_due_on' => null,
    ]);

    expect($service->next_due_on?->toDateString())
        ->toBe($lastInvoicedOn->addMonthsNoOverflow(1)->toDateString());
});

it('recalculates next due date when cadence changes and no manual override is provided', function (): void {
    $lastInvoicedOn = CarbonImmutable::today();

    $service = RecurringService::factory()->create([
        'cadence_unit' => RecurringServiceCadenceUnit::Month,
        'cadence_interval' => 1,
        'starts_on' => $lastInvoicedOn->toDateString(),
        'last_invoiced_on' => $lastInvoicedOn->toDateString(),
        'next_due_on' => null,
    ]);

    $service->update([
        'cadence_interval' => 2,
    ]);

    $service->refresh();

    expect($service->next_due_on?->toDateString())
        ->toBe($lastInvoicedOn->addMonthsNoOverflow(2)->toDateString());
});

it('keeps manually overridden next due date when saved together with cadence changes', function (): void {
    $lastInvoicedOn = CarbonImmutable::today();
    $manualNextDueOn = CarbonImmutable::today()->addDays(5);

    $service = RecurringService::factory()->create([
        'cadence_unit' => RecurringServiceCadenceUnit::Month,
        'cadence_interval' => 1,
        'starts_on' => $lastInvoicedOn->toDateString(),
        'last_invoiced_on' => $lastInvoicedOn->toDateString(),
        'next_due_on' => null,
    ]);

    $service->update([
        'cadence_interval' => 3,
        'next_due_on' => $manualNextDueOn->toDateString(),
    ]);

    $service->refresh();

    expect($service->next_due_on?->toDateString())
        ->toBe($manualNextDueOn->toDateString());
});
