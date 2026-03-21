<?php

use App\Enums\ProjectPipelineStage;
use App\Enums\ProjectPricingModel;
use App\Enums\TaskBillingModel;
use App\Enums\TaskStatus;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Support\Invoicing\InvoiceIssuer;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates one invoice with polymorphic items for records from the same customer', function (): void {
    $owner = User::factory()->create([
        'default_currency' => 'CZK',
        'default_hourly_rate' => 1500,
    ]);

    $customer = Customer::factory()->for($owner, 'owner')->create([
        'billing_currency' => 'EUR',
    ]);

    $project = Project::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => $customer->id,
        'name' => 'Invoice domain rewrite',
        'status' => 'in_progress',
        'pipeline_stage' => ProjectPipelineStage::Won,
        'pricing_model' => ProjectPricingModel::Hourly,
        'currency' => 'EUR',
    ]);

    $fixedPriceTask = Task::query()->create([
        'owner_id' => $owner->id,
        'project_id' => $project->id,
        'title' => 'Fixed-price discovery',
        'billing_model' => TaskBillingModel::FixedPrice,
        'status' => TaskStatus::Done,
        'is_billable' => true,
        'fixed_price' => 6500,
        'completed_at' => now(),
    ]);

    $taskForTimeEntry = Task::query()->create([
        'owner_id' => $owner->id,
        'project_id' => $project->id,
        'title' => 'Tracked implementation',
        'billing_model' => TaskBillingModel::Hourly,
        'status' => TaskStatus::Done,
        'is_billable' => true,
        'track_time' => true,
        'hourly_rate_override' => 2000,
        'completed_at' => now(),
    ]);

    $timeEntry = TimeEntry::query()->create([
        'owner_id' => $owner->id,
        'task_id' => $taskForTimeEntry->id,
        'description' => 'Implement API endpoint',
        'started_at' => now()->subMinutes(90),
        'ended_at' => now(),
        'minutes' => 90,
    ]);

    $invoices = resolve(InvoiceIssuer::class)->issue([
        $fixedPriceTask,
        $timeEntry,
    ], 'INV-2026-004', CarbonImmutable::parse('2026-03-18 09:00:00'));

    expect($invoices)->toHaveCount(1);

    $invoice = $invoices->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice?->reference)->toBe('INV-2026-004')
        ->and($invoice?->customer_id)->toBe($customer->id)
        ->and($invoice?->items)->toHaveCount(2)
        ->and($invoice?->items->pluck('invoiceable_type')->all())->toContain(Task::class, TimeEntry::class);
});

it('creates separate invoices per customer', function (): void {
    $owner = User::factory()->create([
        'default_currency' => 'CZK',
        'default_hourly_rate' => 1500,
    ]);

    $firstCustomer = Customer::factory()->for($owner, 'owner')->create();
    $secondCustomer = Customer::factory()->for($owner, 'owner')->create();

    $firstProject = Project::factory()->for($owner, 'owner')->create([
        'customer_id' => $firstCustomer->id,
    ]);
    $secondProject = Project::factory()->for($owner, 'owner')->create([
        'customer_id' => $secondCustomer->id,
    ]);

    $firstTask = Task::query()->create([
        'owner_id' => $owner->id,
        'project_id' => $firstProject->id,
        'title' => 'First customer task',
        'billing_model' => TaskBillingModel::FixedPrice,
        'status' => TaskStatus::Done,
        'is_billable' => true,
        'fixed_price' => 1000,
        'completed_at' => now(),
    ]);

    $secondTask = Task::query()->create([
        'owner_id' => $owner->id,
        'project_id' => $secondProject->id,
        'title' => 'Second customer task',
        'billing_model' => TaskBillingModel::FixedPrice,
        'status' => TaskStatus::Done,
        'is_billable' => true,
        'fixed_price' => 2000,
        'completed_at' => now(),
    ]);

    $invoices = resolve(InvoiceIssuer::class)->issue([$firstTask, $secondTask], 'INV-2026-BATCH');

    expect($invoices)->toHaveCount(2)
        ->and($invoices->pluck('customer_id')->all())->toContain($firstCustomer->id, $secondCustomer->id);
});
