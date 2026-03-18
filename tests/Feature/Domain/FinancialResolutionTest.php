<?php

use App\Enums\ProjectPipelineStage;
use App\Enums\ProjectPricingModel;
use App\Enums\RecurringServiceBillingModel;
use App\Enums\RecurringServiceCadenceUnit;
use App\Enums\RecurringServiceStatus;
use App\Models\Customer;
use App\Models\Project;
use App\Models\RecurringService;
use App\Models\RecurringServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('resolves customer financial defaults from owner when missing', function (): void {
    $owner = User::factory()->create([
        'default_currency' => 'USD',
        'default_hourly_rate' => 120,
    ]);

    $customerWithOwnValues = Customer::factory()->for($owner, 'owner')->create([
        'billing_currency' => 'EUR',
        'hourly_rate' => 95,
    ]);

    $customerUsingOwnerDefaults = Customer::factory()->for($owner, 'owner')->create([
        'billing_currency' => null,
        'hourly_rate' => null,
    ]);

    expect($customerWithOwnValues->effectiveCurrency())->toBe('EUR')
        ->and($customerWithOwnValues->effectiveHourlyRate())->toBe(95.0)
        ->and($customerUsingOwnerDefaults->effectiveCurrency())->toBe('USD')
        ->and($customerUsingOwnerDefaults->effectiveHourlyRate())->toBe(120.0);
});

it('resolves project rates and currency from customer when project values are missing', function (): void {
    $owner = User::factory()->create([
        'default_currency' => 'CZK',
        'default_hourly_rate' => 1400,
    ]);

    $customer = Customer::factory()->for($owner, 'owner')->create([
        'billing_currency' => 'EUR',
        'hourly_rate' => 100,
    ]);

    $projectWithOwnValues = Project::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => $customer->id,
        'name' => 'Project Own Values',
        'status' => 'in_progress',
        'pipeline_stage' => ProjectPipelineStage::Won,
        'pricing_model' => ProjectPricingModel::Hourly,
        'currency' => 'USD',
        'hourly_rate' => 130,
    ]);

    $projectUsingCustomerDefaults = Project::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => $customer->id,
        'name' => 'Project Customer Defaults',
        'status' => 'in_progress',
        'pipeline_stage' => ProjectPipelineStage::Proposal,
        'pricing_model' => ProjectPricingModel::Hourly,
        'currency' => null,
        'hourly_rate' => null,
    ]);

    expect($projectWithOwnValues->effectiveCurrency())->toBe('USD')
        ->and($projectWithOwnValues->effectiveHourlyRate())->toBe(130.0)
        ->and($projectUsingCustomerDefaults->effectiveCurrency())->toBe('EUR')
        ->and($projectUsingCustomerDefaults->effectiveHourlyRate())->toBe(100.0);
});

it('resolves recurring service currency by precedence service -> project -> customer -> owner', function (): void {
    $owner = User::factory()->create([
        'default_currency' => 'CZK',
    ]);

    $customer = Customer::factory()->for($owner, 'owner')->create([
        'billing_currency' => 'EUR',
    ]);

    $project = Project::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => $customer->id,
        'name' => 'Recurring Project',
        'status' => 'in_progress',
        'pipeline_stage' => ProjectPipelineStage::Won,
        'pricing_model' => ProjectPricingModel::Hourly,
        'currency' => 'USD',
        'hourly_rate' => 1200,
    ]);

    $serviceType = RecurringServiceType::query()->create([
        'owner_id' => $owner->id,
        'name' => 'Hosting',
        'slug' => 'hosting',
        'is_active' => true,
    ]);

    $serviceWithOwnCurrency = RecurringService::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => $customer->id,
        'project_id' => $project->id,
        'name' => 'Service Own Currency',
        'service_type_id' => $serviceType->id,
        'billing_model' => RecurringServiceBillingModel::Fixed,
        'currency' => 'GBP',
        'fixed_amount' => 100,
        'cadence_unit' => RecurringServiceCadenceUnit::Month,
        'cadence_interval' => 1,
        'starts_on' => now()->toDateString(),
        'status' => RecurringServiceStatus::Active,
    ]);

    $serviceUsingProjectCurrency = RecurringService::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => $customer->id,
        'project_id' => $project->id,
        'name' => 'Service Project Currency',
        'service_type_id' => $serviceType->id,
        'billing_model' => RecurringServiceBillingModel::Fixed,
        'currency' => null,
        'fixed_amount' => 120,
        'cadence_unit' => RecurringServiceCadenceUnit::Month,
        'cadence_interval' => 1,
        'starts_on' => now()->toDateString(),
        'status' => RecurringServiceStatus::Active,
    ]);

    $projectWithoutCurrency = Project::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => $customer->id,
        'name' => 'Project Customer Currency',
        'status' => 'in_progress',
        'pipeline_stage' => ProjectPipelineStage::New,
        'pricing_model' => ProjectPricingModel::Fixed,
        'currency' => null,
    ]);

    $serviceUsingCustomerCurrency = RecurringService::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => $customer->id,
        'project_id' => $projectWithoutCurrency->id,
        'name' => 'Service Customer Currency',
        'service_type_id' => $serviceType->id,
        'billing_model' => RecurringServiceBillingModel::Fixed,
        'currency' => null,
        'fixed_amount' => 90,
        'cadence_unit' => RecurringServiceCadenceUnit::Month,
        'cadence_interval' => 1,
        'starts_on' => now()->toDateString(),
        'status' => RecurringServiceStatus::Active,
    ]);

    $customerWithoutCurrency = Customer::factory()->for($owner, 'owner')->create([
        'billing_currency' => null,
    ]);

    $projectWithoutCurrencyAndCustomerCurrency = Project::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => $customerWithoutCurrency->id,
        'name' => 'Project Owner Currency',
        'status' => 'in_progress',
        'pipeline_stage' => ProjectPipelineStage::New,
        'pricing_model' => ProjectPricingModel::Fixed,
        'currency' => null,
    ]);

    $serviceUsingOwnerCurrency = RecurringService::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => $customerWithoutCurrency->id,
        'project_id' => $projectWithoutCurrencyAndCustomerCurrency->id,
        'name' => 'Service Owner Currency',
        'service_type_id' => $serviceType->id,
        'billing_model' => RecurringServiceBillingModel::Fixed,
        'currency' => null,
        'fixed_amount' => 80,
        'cadence_unit' => RecurringServiceCadenceUnit::Month,
        'cadence_interval' => 1,
        'starts_on' => now()->toDateString(),
        'status' => RecurringServiceStatus::Active,
    ]);

    expect($serviceWithOwnCurrency->effectiveCurrency())->toBe('GBP')
        ->and($serviceUsingProjectCurrency->effectiveCurrency())->toBe('USD')
        ->and($serviceUsingCustomerCurrency->effectiveCurrency())->toBe('EUR')
        ->and($serviceUsingOwnerCurrency->effectiveCurrency())->toBe('CZK');
});
