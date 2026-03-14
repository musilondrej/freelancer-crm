<?php

use App\Enums\ProjectActivityType;
use App\Enums\ProjectPipelineStage;
use App\Enums\ProjectPricingModel;
use App\Models\Activity;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Models\Worklog;
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

    auth()->login($owner);

    $customer = Customer::factory()->for($owner, 'owner')->create([
        'billing_currency' => 'EUR',
        'hourly_rate' => 1100,
    ]);

    $project = Project::query()->create([
        'owner_id' => $owner->id,
        'client_id' => $customer->id,
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

it('calculates one-time amount from flat amount', function (): void {
    $context = buildActivityCalculationContext();

    $activity = Worklog::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'One-time setup',
        'type' => ProjectActivityType::OneTime,
        'status' => 'done',
        'is_billable' => true,
        'flat_amount' => 2500,
    ]);

    expect($activity->calculatedAmount())->toBe(2500.0);
});

it('calculates one-time amount from unit rate and quantity when flat amount is missing', function (): void {
    $context = buildActivityCalculationContext();

    $activity = Worklog::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'One-time adjustment',
        'type' => ProjectActivityType::OneTime,
        'status' => 'done',
        'is_billable' => true,
        'unit_rate' => 1200,
        'quantity' => 1.5,
    ]);

    expect($activity->calculatedAmount())->toBe(1800.0);
});

it('calculates hourly amount from explicit unit rate and tracked minutes', function (): void {
    $context = buildActivityCalculationContext();

    $activity = Worklog::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'Tracked coding',
        'type' => ProjectActivityType::Hourly,
        'status' => 'done',
        'is_billable' => true,
        'unit_rate' => 1800,
        'tracked_minutes' => 90,
    ]);

    expect($activity->effectiveUnitRate())->toBe(1800.0)
        ->and($activity->calculatedAmount())->toBe(2700.0);
});

it('calculates hourly amount from explicit unit rate and quantity', function (): void {
    $context = buildActivityCalculationContext();

    $activity = Worklog::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'Quantity based hourly',
        'type' => ProjectActivityType::Hourly,
        'status' => 'done',
        'is_billable' => true,
        'unit_rate' => 950,
        'quantity' => 2.25,
    ]);

    expect($activity->effectiveUnitRate())->toBe(950.0)
        ->and($activity->calculatedAmount())->toBe(2137.5);
});

it('falls back to activity default hourly rate when unit rate is missing', function (): void {
    $context = buildActivityCalculationContext();

    $context['activity']->update([
        'default_hourly_rate' => 1600,
    ]);

    $projectActivity = Worklog::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'Fallback to activity rate',
        'type' => ProjectActivityType::Hourly,
        'status' => 'done',
        'is_billable' => true,
        'tracked_minutes' => 60,
        'unit_rate' => null,
    ]);

    expect($projectActivity->effectiveUnitRate())->toBe(1600.0)
        ->and($projectActivity->calculatedAmount())->toBe(1600.0);
});

it('falls back to project, customer and owner rates in order', function (): void {
    $context = buildActivityCalculationContext();
    $context['project']->update(['hourly_rate' => null]);
    $context['customer']->update(['hourly_rate' => null]);
    $context['activity']->update(['default_hourly_rate' => null]);

    $projectActivity = Worklog::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'Fallback to owner rate',
        'type' => ProjectActivityType::Hourly,
        'status' => 'done',
        'is_billable' => true,
        'tracked_minutes' => 120,
        'unit_rate' => null,
    ]);

    expect($projectActivity->effectiveUnitRate())->toBe(1400.0)
        ->and($projectActivity->calculatedAmount())->toBe(2800.0);
});

it('returns zero for non-billable activities', function (): void {
    $context = buildActivityCalculationContext();

    $activity = Worklog::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'Internal work',
        'type' => ProjectActivityType::Hourly,
        'status' => 'done',
        'is_billable' => false,
        'tracked_minutes' => 200,
        'unit_rate' => 1200,
    ]);

    expect($activity->calculatedAmount())->toBe(0.0);
});

it('returns null for hourly amount when no time quantity is available', function (): void {
    $context = buildActivityCalculationContext();

    $activity = Worklog::query()->create([
        'owner_id' => $context['owner']->id,
        'project_id' => $context['project']->id,
        'activity_id' => $context['activity']->id,
        'title' => 'Missing measure',
        'type' => ProjectActivityType::Hourly,
        'status' => 'planned',
        'is_billable' => true,
        'unit_rate' => 1000,
        'tracked_minutes' => null,
        'quantity' => null,
    ]);

    expect($activity->calculatedAmount())->toBeNull();
});
