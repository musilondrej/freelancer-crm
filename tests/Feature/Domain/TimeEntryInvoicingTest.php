<?php

use App\Enums\ProjectPipelineStage;
use App\Enums\ProjectPricingModel;
use App\Enums\TaskBillingModel;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function buildTimeEntryContext(): array
{
    $owner = User::factory()->create([
        'default_hourly_rate' => 1400,
    ]);

    $customer = Customer::factory()->for($owner, 'owner')->create([
        'hourly_rate' => 1100,
    ]);

    $project = Project::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => $customer->id,
        'name' => 'Tracked Work',
        'status' => 'in_progress',
        'pipeline_stage' => ProjectPipelineStage::Won,
        'pricing_model' => ProjectPricingModel::Hourly,
    ]);

    $activity = Activity::query()->create([
        'owner_id' => $owner->id,
        'project_id' => $project->id,
        'name' => 'Implementation',
        'is_billable' => true,
        'is_active' => true,
    ]);

    $task = Task::query()->create([
        'owner_id' => $owner->id,
        'project_id' => $project->id,
        'activity_id' => $activity->id,
        'title' => 'Build reporting endpoint',
        'billing_model' => TaskBillingModel::Hourly,
        'status' => TaskStatus::Done,
        'is_billable' => true,
        'track_time' => true,
        'hourly_rate_override' => 1800,
    ]);

    return [
        'owner' => $owner,
        'customer' => $customer,
        'task' => $task,
    ];
}

it('uses custom time entry hourly rate before all inherited sources', function (): void {
    $context = buildTimeEntryContext();

    $timeEntry = TimeEntry::query()->create([
        'owner_id' => $context['task']->owner_id,
        'task_id' => $context['task']->id,
        'hourly_rate_override' => 2500,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
        'minutes' => 60,
    ]);

    expect($timeEntry->effectiveHourlyRate())->toBe(2500.0)
        ->and($timeEntry->calculatedAmount())->toBe(2500.0);
});

it('inherits hourly rate from task then customer then owner default', function (): void {
    $context = buildTimeEntryContext();

    $taskRateEntry = TimeEntry::query()->create([
        'owner_id' => $context['task']->owner_id,
        'task_id' => $context['task']->id,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
        'minutes' => 60,
    ]);

    expect($taskRateEntry->effectiveHourlyRate())->toBe(1800.0);

    $context['task']->update(['hourly_rate_override' => null]);

    $customerRateEntry = TimeEntry::query()->create([
        'owner_id' => $context['task']->owner_id,
        'task_id' => $context['task']->id,
        'started_at' => now()->subMinutes(30),
        'ended_at' => now(),
        'minutes' => 30,
    ]);

    expect($customerRateEntry->effectiveHourlyRate())->toBe(1100.0);

    $context['customer']->update(['hourly_rate' => null]);

    $ownerRateEntry = TimeEntry::query()->create([
        'owner_id' => $context['task']->owner_id,
        'task_id' => $context['task']->id,
        'started_at' => now()->subMinutes(45),
        'ended_at' => now(),
        'minutes' => 45,
    ]);

    expect($ownerRateEntry->effectiveHourlyRate())->toBe(1400.0);
});

it('inherits hourly rate from project before customer and owner defaults', function (): void {
    $context = buildTimeEntryContext();

    $context['task']->project()->update([
        'hourly_rate' => 1600,
    ]);
    $context['task']->update([
        'hourly_rate_override' => null,
    ]);

    $timeEntry = TimeEntry::query()->create([
        'owner_id' => $context['task']->owner_id,
        'task_id' => $context['task']->id,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
        'minutes' => 60,
    ]);

    expect($timeEntry->effectiveHourlyRate())->toBe(1600.0);
});

it('marks a finished billable time entry as invoiced', function (): void {
    $context = buildTimeEntryContext();

    $timeEntry = TimeEntry::query()->create([
        'owner_id' => $context['task']->owner_id,
        'task_id' => $context['task']->id,
        'started_at' => now()->subHour(),
        'ended_at' => now(),
        'minutes' => 60,
    ]);

    expect($timeEntry->isReadyToInvoice())->toBeTrue();

    $invoiceDate = CarbonImmutable::parse('2026-03-31 12:00:00');

    $timeEntry->markAsInvoiced('INV-2026-003', $invoiceDate);
    $timeEntry->refresh();

    expect($timeEntry->isInvoiced())->toBeTrue()
        ->and($timeEntry->isReadyToInvoice())->toBeFalse()
        ->and($timeEntry->resolvedInvoiceReference())->toBe('INV-2026-003')
        ->and($timeEntry->resolvedInvoicedAt()?->toDateTimeString())->toBe($invoiceDate->toDateTimeString())
        ->and($timeEntry->currentInvoiceItem)->not->toBeNull()
        ->and($timeEntry->currentInvoiceItem?->invoice)->toBeInstanceOf(Invoice::class);
});

it('requires invoice reference when marking time entries as invoiced', function (): void {
    $context = buildTimeEntryContext();

    $timeEntry = TimeEntry::query()->create([
        'owner_id' => $context['task']->owner_id,
        'task_id' => $context['task']->id,
        'started_at' => now()->subMinutes(30),
        'ended_at' => now(),
        'minutes' => 30,
    ]);

    expect(fn () => $timeEntry->markAsInvoiced('   '))
        ->toThrow(InvalidArgumentException::class, 'Invoice reference is required for time entry invoicing.');

    $timeEntry->refresh();

    expect($timeEntry->isInvoiced())->toBeFalse()
        ->and($timeEntry->resolvedInvoiceReference())->toBeNull()
        ->and($timeEntry->resolvedInvoicedAt())->toBeNull();
});

it('does not consider running or non-billable time entries ready to invoice', function (): void {
    $context = buildTimeEntryContext();

    $runningEntry = TimeEntry::query()->create([
        'owner_id' => $context['task']->owner_id,
        'task_id' => $context['task']->id,
        'started_at' => now()->subMinutes(15),
        'ended_at' => null,
        'minutes' => null,
    ]);

    $nonBillableEntry = TimeEntry::query()->create([
        'owner_id' => $context['task']->owner_id,
        'task_id' => $context['task']->id,
        'is_billable_override' => false,
        'started_at' => now()->subMinutes(45),
        'ended_at' => now(),
        'minutes' => 45,
    ]);

    expect($runningEntry->isReadyToInvoice())->toBeFalse()
        ->and($nonBillableEntry->isReadyToInvoice())->toBeFalse();
});
