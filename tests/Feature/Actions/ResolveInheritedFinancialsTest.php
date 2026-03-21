<?php

use App\Actions\ResolveInheritedFinancials;
use App\Enums\ProjectPipelineStage;
use App\Enums\ProjectPricingModel;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Support\FinancialDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── fromCustomer ───────────────────────────────────────────────────────────

it('resolves currency and rate from a customer with own overrides', function (): void {
    $owner = User::factory()->create([
        'default_currency' => 'CZK',
        'default_hourly_rate' => 1000,
    ]);

    $customer = Customer::factory()->for($owner, 'owner')->create([
        'billing_currency' => 'EUR',
        'hourly_rate' => 1500,
    ]);

    $defaults = (new ResolveInheritedFinancials($owner->id))->fromCustomer($customer->id);

    expect($defaults)->toBeInstanceOf(FinancialDefaults::class)
        ->and($defaults->currency)->toBe('EUR')
        ->and($defaults->hourlyRate)->toBe(1500.0);
});

it('falls back to owner defaults when customer has no overrides', function (): void {
    $owner = User::factory()->create([
        'default_currency' => 'USD',
        'default_hourly_rate' => 1200,
    ]);

    $customer = Customer::factory()->for($owner, 'owner')->create([
        'billing_currency' => null,
        'hourly_rate' => null,
    ]);

    $defaults = (new ResolveInheritedFinancials($owner->id))->fromCustomer($customer->id);

    expect($defaults->currency)->toBe('USD')
        ->and($defaults->hourlyRate)->toBe(1200.0);
});

it('falls back to user defaults when customer id is null', function (): void {
    $owner = User::factory()->create([
        'default_currency' => 'CZK',
        'default_hourly_rate' => 800,
    ]);

    $defaults = (new ResolveInheritedFinancials($owner->id))->fromCustomer(null);

    expect($defaults->currency)->toBe('CZK')
        ->and($defaults->hourlyRate)->toBe(800.0);
});

it('ignores customers owned by other users', function (): void {
    $owner = User::factory()->create(['default_currency' => 'CZK', 'default_hourly_rate' => 900]);
    $other = User::factory()->create();

    $foreignCustomer = Customer::factory()->for($other, 'owner')->create([
        'billing_currency' => 'EUR',
        'hourly_rate' => 9999,
    ]);

    $defaults = (new ResolveInheritedFinancials($owner->id))->fromCustomer($foreignCustomer->id);

    expect($defaults->currency)->toBe('CZK')
        ->and($defaults->hourlyRate)->toBe(900.0);
});

// ─── fromProject ────────────────────────────────────────────────────────────

it('resolves currency and rate from a project with own overrides', function (): void {
    $owner = User::factory()->create(['default_currency' => 'CZK', 'default_hourly_rate' => 1000]);
    $customer = Customer::factory()->for($owner, 'owner')->create(['billing_currency' => 'EUR', 'hourly_rate' => 1100]);

    $project = Project::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => $customer->id,
        'name' => 'Test Project',
        'status' => 'in_progress',
        'pipeline_stage' => ProjectPipelineStage::Won,
        'pricing_model' => ProjectPricingModel::Hourly,
        'currency' => 'USD',
        'hourly_rate' => 1800,
    ]);

    $defaults = (new ResolveInheritedFinancials($owner->id))->fromProject($project->id);

    expect($defaults->currency)->toBe('USD')
        ->and($defaults->hourlyRate)->toBe(1800.0);
});

it('inherits currency and rate from customer when project has no overrides', function (): void {
    $owner = User::factory()->create(['default_currency' => 'CZK', 'default_hourly_rate' => 1000]);
    $customer = Customer::factory()->for($owner, 'owner')->create(['billing_currency' => 'EUR', 'hourly_rate' => 1100]);

    $project = Project::query()->create([
        'owner_id' => $owner->id,
        'customer_id' => $customer->id,
        'name' => 'Bare Project',
        'status' => 'in_progress',
        'pipeline_stage' => ProjectPipelineStage::Won,
        'pricing_model' => ProjectPricingModel::Hourly,
        'currency' => null,
        'hourly_rate' => null,
    ]);

    $defaults = (new ResolveInheritedFinancials($owner->id))->fromProject($project->id);

    expect($defaults->currency)->toBe('EUR')
        ->and($defaults->hourlyRate)->toBe(1100.0);
});

it('falls back to user defaults when project id is null', function (): void {
    $owner = User::factory()->create(['default_currency' => 'GBP', 'default_hourly_rate' => 2000]);

    $defaults = (new ResolveInheritedFinancials($owner->id))->fromProject(null);

    expect($defaults->currency)->toBe('GBP')
        ->and($defaults->hourlyRate)->toBe(2000.0);
});

it('ignores projects owned by other users', function (): void {
    $owner = User::factory()->create(['default_currency' => 'CZK', 'default_hourly_rate' => 700]);
    $other = User::factory()->create();
    $otherCustomer = Customer::factory()->for($other, 'owner')->create();

    $foreignProject = Project::query()->create([
        'owner_id' => $other->id,
        'customer_id' => $otherCustomer->id,
        'name' => 'Foreign',
        'status' => 'in_progress',
        'pipeline_stage' => ProjectPipelineStage::Won,
        'pricing_model' => ProjectPricingModel::Fixed,
        'currency' => 'JPY',
        'hourly_rate' => 9999,
    ]);

    $defaults = (new ResolveInheritedFinancials($owner->id))->fromProject($foreignProject->id);

    expect($defaults->currency)->toBe('CZK')
        ->and($defaults->hourlyRate)->toBe(700.0);
});
