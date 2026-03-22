<?php

use App\Enums\Priority;
use App\Enums\ProjectPipelineStage;
use App\Enums\ProjectPricingModel;
use App\Enums\TaskBillingModel;
use App\Enums\TaskStatus;
use App\Models\BillingReport;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * @return array{owner: User, customer: Customer, project: Project}
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

    return [
        'owner' => $owner,
        'customer' => $customer,
        'project' => $project,
    ];
}

function logTaskTime(Task $task, int $minutes, ?bool $isBillableOverride = null): void
{
    TimeEntry::query()->create([
        'owner_id' => $task->owner_id,
        'project_id' => $task->project_id,
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
        'title' => 'Priority fallback task',
        'billing_model' => TaskBillingModel::Hourly,
        'status' => TaskStatus::Todo,
        'is_billable' => true,
    ]);

    expect($task->priority)->toBe(Priority::Normal);
});

it('calculates one-time amount from unit rate and quantity when flat amount is missing', function (): void {
    $context = buildActivityCalculationContext();

    $task = Task::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
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

it('ignores quantity for hourly tasks and requires time entries as source of truth', function (): void {
    $context = buildActivityCalculationContext();

    $task = Task::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'title' => 'Quantity based hourly',
        'billing_model' => TaskBillingModel::Hourly,
        'status' => TaskStatus::Done,
        'is_billable' => true,
        'hourly_rate_override' => 950,
        'quantity' => 2.25,
    ]);

    expect($task->effectiveHourlyRate())->toBe(950.0)
        ->and($task->quantity)->toBeNull()
        ->and($task->calculatedAmount())->toBeNull();
});

it('uses project rate for hourly amount calculation when task rate is missing', function (): void {
    $context = buildActivityCalculationContext();

    $projectActivity = Task::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'title' => 'Fallback to project rate',
        'billing_model' => TaskBillingModel::Hourly,
        'status' => TaskStatus::Done,
        'is_billable' => true,
        'hourly_rate_override' => null,
    ]);

    logTaskTime($projectActivity, 60);

    expect($projectActivity->effectiveHourlyRate())->toBe(1200.0)
        ->and($projectActivity->calculatedAmount())->toBe(1200.0);
});

it('falls back to project, customer and owner rates in order', function (): void {
    $context = buildActivityCalculationContext();
    $context['project']->update(['hourly_rate' => null]);
    $context['customer']->update(['hourly_rate' => null]);

    $projectActivity = Task::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
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
        'title' => 'Internal work',
        'billing_model' => TaskBillingModel::Hourly,
        'status' => TaskStatus::Done,
        'is_billable' => false,
        'hourly_rate_override' => 1200,
    ]);

    logTaskTime($task, 200);

    expect($task->calculatedAmount())->toBe(0.0);
});

it('returns null for hourly amount when no tracked time is available', function (): void {
    $context = buildActivityCalculationContext();

    $task = Task::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'title' => 'Missing measure',
        'billing_model' => TaskBillingModel::Hourly,
        'status' => TaskStatus::InProgress,
        'is_billable' => true,
        'hourly_rate_override' => 1000,
    ]);

    expect($task->calculatedAmount())->toBeNull();
});

it('blocks switching a task to fixed price when time entries already exist', function (): void {
    $context = buildActivityCalculationContext();

    $task = Task::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'title' => 'Tracked implementation',
        'billing_model' => TaskBillingModel::Hourly,
        'status' => TaskStatus::InProgress,
        'is_billable' => true,
        'hourly_rate_override' => 1400,
    ]);

    logTaskTime($task, 30);

    expect(fn (): bool => $task->update([
        'billing_model' => TaskBillingModel::FixedPrice,
        'fixed_price' => 2500,
    ]))->toThrow(ValidationException::class);
});

it('marks a ready task as invoiced once its billing report is finalized', function (): void {
    $context = buildActivityCalculationContext();

    $task = Task::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'title' => 'Monthly optimization batch',
        'billing_model' => TaskBillingModel::FixedPrice,
        'status' => TaskStatus::Done,
        'is_billable' => true,
        'fixed_price' => 5000,
        'completed_at' => now(),
    ]);

    $this->actingAs($context['owner']);

    $report = BillingReport::factory()
        ->for($context['owner'], 'owner')
        ->create(['customer_id' => $context['customer']->id]);

    $report->addFixedPriceTask($task);

    expect($task->fresh()->isInvoiced())->toBeFalse()
        ->and($task->fresh()->isReadyToInvoice())->toBeFalse();

    $report->finalize('FAK-2026-001');

    expect($task->fresh()->isInvoiced())->toBeTrue()
        ->and($task->fresh()->isReadyToInvoice())->toBeFalse();
});
