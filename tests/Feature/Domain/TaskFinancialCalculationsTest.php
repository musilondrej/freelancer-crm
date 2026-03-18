<?php

use App\Enums\Priority;
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

/**
 * @return array{owner: User, customer: Customer, project: Project, activity: Activity}
 */
function buildActivityCalculationContext(): array
{
    $owner = User::factory()->create([
        'default_currency' => 'CZK',
        'default_hourly_rate' => 1400,
    ]);

    $customer = Customer::factory()->for($owner, 'owner')->create([
        'billing_currency' => 'EUR',
        'hourly_rate' => 1100,
    ]);

    $project = Project::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => $customer->id,
        'name' => 'Billing Core',
        'status' => 'in_progress',
        'pipeline_stage' => ProjectPipelineStage::Won,
        'pricing_model' => ProjectPricingModel::Hourly,
        'currency' => 'USD',
        'hourly_rate' => 1200,
    ]);

    $activity = Activity::query()->create([
        'owner_id' => $owner->id,
        'project_id' => $project->id,
        'name' => 'Implementation',
        'default_hourly_rate' => 1300,
        'is_billable' => true,
        'is_active' => true,
    ]);

    return [
        'owner' => $owner,
        'customer' => $customer,
        'project' => $project,
        'activity' => $activity,
    ];
}

function logTaskTime(Task $task, int $minutes, ?bool $isBillableOverride = null): void
{
    TimeEntry::query()->create([
        'owner_id' => $task->owner_id,
        'task_id' => $task->id,
        'is_billable_override' => $isBillableOverride,
        'started_at' => now()->subMinutes($minutes),
        'ended_at' => now(),
        'minutes' => $minutes,
    ]);
}

it('calculates one-time amount from flat amount', function (): void {
    $context = buildActivityCalculationContext();

    $task = Task::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'One-time setup',
        'billing_model' => TaskBillingModel::FixedPrice,
        'status' => TaskStatus::Done,
        'is_billable' => true,
        'fixed_price' => 2500,
    ]);

    expect($task->calculatedAmount())->toBe(2500.0);
});

it('defaults task priority to backlog when omitted', function (): void {
    $context = buildActivityCalculationContext();

    $task = Task::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'Priority fallback task',
        'billing_model' => TaskBillingModel::Hourly,
        'status' => TaskStatus::Planned,
        'is_billable' => true,
    ]);

    expect($task->priority)->toBe(Priority::Backlog);
});

it('calculates one-time amount from unit rate and quantity when flat amount is missing', function (): void {
    $context = buildActivityCalculationContext();

    $task = Task::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'One-time adjustment',
        'billing_model' => TaskBillingModel::FixedPrice,
        'status' => TaskStatus::Done,
        'is_billable' => true,
        'fixed_price' => null,
    ]);

    expect($task->calculatedAmount())->toBeNull();
});

it('calculates hourly amount from explicit unit rate and tracked minutes', function (): void {
    $context = buildActivityCalculationContext();

    $task = Task::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'Tracked coding',
        'billing_model' => TaskBillingModel::Hourly,
        'status' => TaskStatus::Done,
        'is_billable' => true,
        'hourly_rate_override' => 1800,
    ]);

    logTaskTime($task, 90);

    expect($task->effectiveHourlyRate())->toBe(1800.0)
        ->and($task->calculatedAmount())->toBe(2700.0);
});

it('uses individual time entry hourly rates for task amount calculation', function (): void {
    $context = buildActivityCalculationContext();

    $task = Task::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'Mixed-rate implementation',
        'billing_model' => TaskBillingModel::Hourly,
        'status' => TaskStatus::Done,
        'is_billable' => true,
        'hourly_rate_override' => 1800,
    ]);

    TimeEntry::query()->create([
        'owner_id' => $task->owner_id,
        'task_id' => $task->id,
        'hourly_rate_override' => 2200,
        'started_at' => now()->subHour(),
        'ended_at' => now()->subMinutes(30),
        'minutes' => 30,
    ]);

    TimeEntry::query()->create([
        'owner_id' => $task->owner_id,
        'task_id' => $task->id,
        'started_at' => now()->subMinutes(30),
        'ended_at' => now(),
        'minutes' => 30,
    ]);

    expect($task->calculatedAmount())->toBe(2000.0);
});

it('calculates hourly amount from explicit unit rate and quantity', function (): void {
    $context = buildActivityCalculationContext();

    $task = Task::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'Quantity based hourly',
        'billing_model' => TaskBillingModel::Hourly,
        'status' => TaskStatus::Done,
        'is_billable' => true,
        'hourly_rate_override' => 950,
        'quantity' => 2.25,
    ]);

    expect($task->effectiveHourlyRate())->toBe(950.0)
        ->and($task->calculatedAmount())->toBe(2137.5);
});

it('uses the time entry inheritance chain for hourly amount calculation when task rate is missing', function (): void {
    $context = buildActivityCalculationContext();

    $context['activity']->update([
        'default_hourly_rate' => 1600,
    ]);

    $projectActivity = Task::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'Fallback to activity rate',
        'billing_model' => TaskBillingModel::Hourly,
        'status' => TaskStatus::Done,
        'is_billable' => true,
        'hourly_rate_override' => null,
    ]);

    logTaskTime($projectActivity, 60);

    expect($projectActivity->effectiveHourlyRate())->toBe(1600.0)
        ->and($projectActivity->calculatedAmount())->toBe(1100.0);
});

it('falls back to project, customer and owner rates in order', function (): void {
    $context = buildActivityCalculationContext();
    $context['project']->update(['hourly_rate' => null]);
    $context['customer']->update(['hourly_rate' => null]);
    $context['activity']->update(['default_hourly_rate' => null]);

    $projectActivity = Task::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'Fallback to owner rate',
        'billing_model' => TaskBillingModel::Hourly,
        'status' => TaskStatus::Done,
        'is_billable' => true,
        'hourly_rate_override' => null,
    ]);

    logTaskTime($projectActivity, 120);

    expect($projectActivity->effectiveHourlyRate())->toBe(1400.0)
        ->and($projectActivity->calculatedAmount())->toBe(2800.0);
});

it('returns zero for non-billable activities', function (): void {
    $context = buildActivityCalculationContext();

    $task = Task::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'Internal work',
        'billing_model' => TaskBillingModel::Hourly,
        'status' => TaskStatus::Done,
        'is_billable' => false,
        'hourly_rate_override' => 1200,
    ]);

    logTaskTime($task, 200);

    expect($task->calculatedAmount())->toBe(0.0);
});

it('returns null for hourly amount when no time quantity is available', function (): void {
    $context = buildActivityCalculationContext();

    $task = Task::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'Missing measure',
        'billing_model' => TaskBillingModel::Hourly,
        'status' => TaskStatus::InProgress,
        'is_billable' => true,
        'hourly_rate_override' => 1000,
        'quantity' => null,
    ]);

    expect($task->calculatedAmount())->toBeNull();
});

it('marks a ready task as invoiced with a shared invoice reference', function (): void {
    $context = buildActivityCalculationContext();

    $task = Task::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'Monthly optimization batch',
        'billing_model' => TaskBillingModel::FixedPrice,
        'status' => TaskStatus::Done,
        'is_billable' => true,
        'fixed_price' => 5000,
        'completed_at' => now(),
    ]);

    $invoiceDate = CarbonImmutable::parse('2026-03-31 09:00:00');

    $task->markAsInvoiced('INV-2026-003', $invoiceDate);
    $task->refresh();

    expect($task->isInvoiced())->toBeTrue()
        ->and($task->isReadyToInvoice())->toBeFalse()
        ->and($task->resolvedInvoiceReference())->toBe('INV-2026-003')
        ->and($task->resolvedInvoicedAt()?->toDateTimeString())->toBe($invoiceDate->toDateTimeString())
        ->and($task->currentInvoiceItem)->not->toBeNull()
        ->and($task->currentInvoiceItem?->invoice)->toBeInstanceOf(Invoice::class);
});

it('normalizes blank invoice references when marking a task as invoiced', function (): void {
    $context = buildActivityCalculationContext();

    $task = Task::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'Hourly maintenance review',
        'billing_model' => TaskBillingModel::Hourly,
        'status' => TaskStatus::Done,
        'is_billable' => true,
        'hourly_rate_override' => 1200,
        'completed_at' => now(),
    ]);

    logTaskTime($task, 90);

    $task->markAsInvoiced('   ', '2026-03-18');
    $task->refresh();

    expect($task->isInvoiced())->toBeTrue()
        ->and($task->resolvedInvoiceReference())->toBeNull()
        ->and($task->resolvedInvoicedAt())->not->toBeNull();
});
