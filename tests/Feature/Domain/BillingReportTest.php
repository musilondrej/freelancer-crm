<?php

use App\Enums\BillingReportStatus;
use App\Enums\TaskBillingModel;
use App\Enums\TaskStatus;
use App\Models\BillingReport;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

function buildBillingContext(): array
{
    $owner = User::factory()->create(['default_hourly_rate' => 1000]);
    $customer = Customer::factory()->for($owner, 'owner')->create(['billing_currency' => 'CZK']);
    $project = Project::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => $customer->id,
        'name' => 'Test Project',
        'status' => 'in_progress',
    ]);

    return ['owner' => $owner, 'customer' => $customer, 'project' => $project];
}

function makeHourlyTask(array $ctx, int $hourlyRate = 1000): Task
{
    return Task::query()->create([
        'owner_id' => $ctx['owner']->id,
        'project_id' => $ctx['project']->id,
        'title' => 'Dev work',
        'billing_model' => TaskBillingModel::Hourly,
        'is_billable' => true,
        'hourly_rate_override' => $hourlyRate,
        'status' => TaskStatus::Done,
    ]);
}

function makeFixedPriceTask(array $ctx, float $price = 5000): Task
{
    return Task::query()->create([
        'owner_id' => $ctx['owner']->id,
        'project_id' => $ctx['project']->id,
        'title' => 'Záloha 30%',
        'billing_model' => TaskBillingModel::FixedPrice,
        'is_billable' => true,
        'fixed_price' => $price,
        'status' => TaskStatus::Done,
    ]);
}

function makeTimeEntry(Task $task, int $minutes = 60): TimeEntry
{
    return TimeEntry::query()->create([
        'owner_id' => $task->owner_id,
        'project_id' => $task->project_id,
        'task_id' => $task->id,
        'started_at' => CarbonImmutable::now()->subHours(2),
        'ended_at' => CarbonImmutable::now()->subHours(2)->addMinutes($minutes),
        'hourly_rate_override' => $task->hourly_rate_override ?? 1000,
    ]);
}

// ─── BillingReport creation ───────────────────────────────────────────────────

it('creates a draft billing report', function (): void {
    ['owner' => $owner, 'customer' => $customer] = buildBillingContext();

    $this->actingAs($owner);

    $report = BillingReport::factory()
        ->for($owner, 'owner')
        ->create(['customer_id' => $customer->id, 'status' => BillingReportStatus::Draft]);

    expect($report->isDraft())->toBeTrue()
        ->and($report->isFinalized())->toBeFalse()
        ->and($report->totalAmount())->toBe(0.0);
});

// ─── Adding hourly tasks ──────────────────────────────────────────────────────

it('adds an hourly task and attaches unbilled time entries', function (): void {
    $ctx = buildBillingContext();
    $this->actingAs($ctx['owner']);

    $task = makeHourlyTask($ctx, hourlyRate: 1000);
    makeTimeEntry($task, minutes: 60);   // 1h
    makeTimeEntry($task, minutes: 90);   // 1.5h

    $report = BillingReport::factory()->for($ctx['owner'], 'owner')->create([
        'customer_id' => $ctx['customer']->id,
    ]);

    $line = $report->addHourlyTask($task);

    expect($line->task_id)->toBe($task->id)
        ->and($line->description)->toBe($task->title)
        ->and((float) $line->quantity)->toBe(2.5)
        ->and((float) $line->unit_price)->toBe(1000.0)
        ->and((float) $line->total_amount)->toBe(2500.0)
        ->and($line->timeEntries()->count())->toBe(2);
});

it('derives period from attached time entries', function (): void {
    $ctx = buildBillingContext();
    $this->actingAs($ctx['owner']);

    $task = makeHourlyTask($ctx);

    TimeEntry::query()->create([
        'owner_id' => $task->owner_id,
        'project_id' => $task->project_id,
        'task_id' => $task->id,
        'started_at' => CarbonImmutable::parse('2026-03-10 10:00'),
        'ended_at' => CarbonImmutable::parse('2026-03-10 11:00'),
        'hourly_rate_override' => 1000,
    ]);

    TimeEntry::query()->create([
        'owner_id' => $task->owner_id,
        'project_id' => $task->project_id,
        'task_id' => $task->id,
        'started_at' => CarbonImmutable::parse('2026-03-25 10:00'),
        'ended_at' => CarbonImmutable::parse('2026-03-25 11:00'),
        'hourly_rate_override' => 1000,
    ]);

    $report = BillingReport::factory()->for($ctx['owner'], 'owner')->create([
        'customer_id' => $ctx['customer']->id,
    ]);

    $report->addHourlyTask($task);

    expect($report->period_from?->toDateString())->toBe('2026-03-10')
        ->and($report->period_to?->toDateString())->toBe('2026-03-25');
});

// ─── Adding fixed-price tasks ─────────────────────────────────────────────────

it('adds a fixed-price task as a single line', function (): void {
    $ctx = buildBillingContext();
    $this->actingAs($ctx['owner']);

    $task = makeFixedPriceTask($ctx, price: 15000);

    $report = BillingReport::factory()->for($ctx['owner'], 'owner')->create([
        'customer_id' => $ctx['customer']->id,
    ]);

    $line = $report->addFixedPriceTask($task);

    expect($line->task_id)->toBe($task->id)
        ->and((float) $line->quantity)->toBe(1.0)
        ->and((float) $line->unit_price)->toBe(15000.0)
        ->and((float) $line->total_amount)->toBe(15000.0)
        ->and($line->timeEntries()->count())->toBe(0);
});

// ─── Custom lines ─────────────────────────────────────────────────────────────

it('adds a custom line without a task reference', function (): void {
    $ctx = buildBillingContext();
    $this->actingAs($ctx['owner']);

    $report = BillingReport::factory()->for($ctx['owner'], 'owner')->create([
        'customer_id' => $ctx['customer']->id,
    ]);

    $line = $report->addCustomLine('Travel expenses', 1, 350.0);

    expect($line->isCustom())->toBeTrue()
        ->and($line->task_id)->toBeNull()
        ->and((float) $line->total_amount)->toBe(350.0);
});

// ─── totalAmount ──────────────────────────────────────────────────────────────

it('computes total amount from all lines', function (): void {
    $ctx = buildBillingContext();
    $this->actingAs($ctx['owner']);

    $report = BillingReport::factory()->for($ctx['owner'], 'owner')->create([
        'customer_id' => $ctx['customer']->id,
    ]);

    $task = makeFixedPriceTask($ctx, price: 5000);
    $report->addFixedPriceTask($task);
    $report->addCustomLine('Extra', 2, 500);

    expect($report->totalAmount())->toBe(6000.0);
});

// ─── Line total_amount auto-computation ───────────────────────────────────────

it('recomputes total_amount when line quantity is updated', function (): void {
    $ctx = buildBillingContext();
    $this->actingAs($ctx['owner']);

    $report = BillingReport::factory()->for($ctx['owner'], 'owner')->create([
        'customer_id' => $ctx['customer']->id,
    ]);
    $line = $report->addCustomLine('Test', 3, 100);

    $line->update(['quantity' => 5]);

    expect((float) $line->fresh()->total_amount)->toBe(500.0);
});

// ─── Finalize ─────────────────────────────────────────────────────────────────

it('finalizes the report and marks tasks and time entries as invoiced', function (): void {
    $ctx = buildBillingContext();
    $this->actingAs($ctx['owner']);

    $task = makeHourlyTask($ctx);
    $entry = makeTimeEntry($task, minutes: 60);

    $report = BillingReport::factory()->for($ctx['owner'], 'owner')->create([
        'customer_id' => $ctx['customer']->id,
    ]);
    $report->addHourlyTask($task);

    $report->finalize('FAK-2026-001');

    expect($report->fresh()->isFinalized())->toBeTrue()
        ->and($report->fresh()->reference)->toBe('FAK-2026-001')
        ->and($report->fresh()->finalized_at)->not->toBeNull()
        ->and($task->fresh()->isInvoiced())->toBeTrue()
        ->and($entry->fresh()->isInvoiced())->toBeTrue();
});

it('is idempotent: finalizing an already-finalized report is a no-op', function (): void {
    $ctx = buildBillingContext();
    $this->actingAs($ctx['owner']);

    $report = BillingReport::factory()->for($ctx['owner'], 'owner')->create([
        'customer_id' => $ctx['customer']->id,
        'status' => BillingReportStatus::Finalized,
        'finalized_at' => now(),
        'reference' => 'FAK-2026-001',
    ]);

    $report->finalize('SHOULD-NOT-OVERWRITE');

    expect($report->fresh()->reference)->toBe('FAK-2026-001');
});

// ─── Double-billing protection ────────────────────────────────────────────────

it('excludes time entries in a draft report from readyToInvoice scope', function (): void {
    $ctx = buildBillingContext();
    $this->actingAs($ctx['owner']);

    $task = makeHourlyTask($ctx);
    $entry = makeTimeEntry($task, minutes: 60);

    $report = BillingReport::factory()->for($ctx['owner'], 'owner')->create([
        'customer_id' => $ctx['customer']->id,
    ]);
    $report->addHourlyTask($task);

    $readyCount = TimeEntry::readyToInvoice()->where('id', $entry->id)->count();

    expect($readyCount)->toBe(0);
});

it('prevents a time entry from being added to two reports', function (): void {
    $ctx = buildBillingContext();
    $this->actingAs($ctx['owner']);

    $task = makeHourlyTask($ctx);
    makeTimeEntry($task);

    $report1 = BillingReport::factory()->for($ctx['owner'], 'owner')->create(['customer_id' => $ctx['customer']->id]);
    $report2 = BillingReport::factory()->for($ctx['owner'], 'owner')->create(['customer_id' => $ctx['customer']->id]);

    $line1 = $report1->addHourlyTask($task);
    $line2 = $report2->addHourlyTask($task);  // entry already reserved

    expect($line1->timeEntries()->count())->toBe(1)
        ->and($line2->timeEntries()->count())->toBe(0);
});

// ─── addSpecificEntries ───────────────────────────────────────────────────────

it('adds specific entries grouped by task', function (): void {
    $ctx = buildBillingContext();
    $this->actingAs($ctx['owner']);

    $task = makeHourlyTask($ctx, hourlyRate: 500);
    $entry1 = makeTimeEntry($task, minutes: 60);
    $entry2 = makeTimeEntry($task, minutes: 120);

    $report = BillingReport::factory()->for($ctx['owner'], 'owner')->create(['customer_id' => $ctx['customer']->id]);
    $attached = $report->addSpecificEntries(new EloquentCollection([$entry1, $entry2]));

    expect($attached)->toBe(2)
        ->and($report->lines()->count())->toBe(1)
        ->and((float) $report->lines()->first()->quantity)->toBe(3.0)
        ->and((float) $report->lines()->first()->unit_price)->toBe(500.0)
        ->and((float) $report->lines()->first()->total_amount)->toBe(1500.0);
});

it('creates separate lines for entries from different tasks', function (): void {
    $ctx = buildBillingContext();
    $this->actingAs($ctx['owner']);

    $taskA = makeHourlyTask($ctx, hourlyRate: 1000);
    $taskB = makeHourlyTask($ctx, hourlyRate: 500);
    $entryA = makeTimeEntry($taskA, minutes: 60);
    $entryB = makeTimeEntry($taskB, minutes: 60);

    $report = BillingReport::factory()->for($ctx['owner'], 'owner')->create(['customer_id' => $ctx['customer']->id]);
    $attached = $report->addSpecificEntries(new EloquentCollection([$entryA, $entryB]));

    expect($attached)->toBe(2)
        ->and($report->lines()->count())->toBe(2);
});

it('skips already-assigned entries when using addSpecificEntries', function (): void {
    $ctx = buildBillingContext();
    $this->actingAs($ctx['owner']);

    $task = makeHourlyTask($ctx);
    $entry = makeTimeEntry($task, minutes: 60);

    $report1 = BillingReport::factory()->for($ctx['owner'], 'owner')->create(['customer_id' => $ctx['customer']->id]);
    $report2 = BillingReport::factory()->for($ctx['owner'], 'owner')->create(['customer_id' => $ctx['customer']->id]);

    $report1->addSpecificEntries(new EloquentCollection([$entry]));
    $attached = $report2->addSpecificEntries(new EloquentCollection([$entry]));  // should be skipped

    expect($attached)->toBe(0)
        ->and($report2->lines()->count())->toBe(0);
});
