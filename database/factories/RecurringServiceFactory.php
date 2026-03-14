<?php

namespace Database\Factories;

use App\Enums\RecurringServiceBillingModel;
use App\Enums\RecurringServiceCadenceUnit;
use App\Enums\RecurringServiceStatus;
use App\Models\Customer;
use App\Models\RecurringService;
use App\Models\RecurringServiceType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringService>
 */
class RecurringServiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /** @var array{slug: string, label: string, service_name: string} $template */
        $template = fake()->randomElement([
            ['slug' => 'hosting', 'label' => 'Hosting', 'service_name' => 'Web Hosting'],
            ['slug' => 'domain', 'label' => 'Domain', 'service_name' => 'Domain Renewal'],
            ['slug' => 'maintenance', 'label' => 'Maintenance', 'service_name' => 'Monthly Website Maintenance'],
            ['slug' => 'support', 'label' => 'Support', 'service_name' => 'Support Retainer'],
            ['slug' => 'license', 'label' => 'License', 'service_name' => 'Software License'],
            ['slug' => 'retainer', 'label' => 'Retainer', 'service_name' => 'Development Retainer'],
            ['slug' => 'other', 'label' => 'Other', 'service_name' => ucfirst(fake()->words(2, true))],
        ]);

        $billingModel = in_array($template['slug'], ['maintenance', 'support', 'retainer'], true)
            && fake()->boolean(45)
            ? RecurringServiceBillingModel::Hourly
            : RecurringServiceBillingModel::Fixed;

        $cadenceUnit = $template['slug'] === 'domain'
            ? fake()->randomElement([RecurringServiceCadenceUnit::Year, RecurringServiceCadenceUnit::Quarter])
            : fake()->randomElement([RecurringServiceCadenceUnit::Month, RecurringServiceCadenceUnit::Quarter]);

        $startsOn = fake()->dateTimeBetween('-1 year', '-1 week');
        $nextDueOn = fake()->dateTimeBetween('now', '+3 months');

        return [
            'customer_id' => Customer::factory(),
            'owner_id' => fn (array $attributes): ?int => Customer::query()->find($attributes['customer_id'])?->owner_id,
            'project_id' => null,
            'name' => $template['service_name'],
            'service_type_id' => fn (array $attributes): int => $this->resolveServiceTypeId(
                $attributes,
                slug: $template['slug'],
                name: $template['label'],
            ),
            'billing_model' => $billingModel,
            'currency' => fake()->optional(0.9)->randomElement(['CZK', 'EUR', 'USD']),
            'fixed_amount' => $billingModel === RecurringServiceBillingModel::Fixed ? fake()->randomFloat(2, 200, 12000) : null,
            'hourly_rate' => $billingModel === RecurringServiceBillingModel::Hourly ? fake()->randomFloat(2, 500, 3200) : null,
            'included_quantity' => $billingModel === RecurringServiceBillingModel::Hourly ? fake()->randomFloat(2, 1, 25) : null,
            'cadence_unit' => $cadenceUnit,
            'cadence_interval' => 1,
            'starts_on' => $startsOn,
            'next_due_on' => $nextDueOn,
            'last_invoiced_on' => fake()->optional(0.7)->dateTimeBetween('-2 months', 'now'),
            'ends_on' => null,
            'auto_renew' => true,
            'status' => fake()->randomElement([
                RecurringServiceStatus::Active,
                RecurringServiceStatus::Active,
                RecurringServiceStatus::Active,
                RecurringServiceStatus::Paused,
            ]),
            'remind_days_before' => fake()->randomElement([[14, 7, 1], [7, 1], [3, 1], [1]]),
            'notes' => fake()->optional(0.6)->sentence(),
            'meta' => [
                'provider' => fake()->optional(0.6)->company(),
            ],
        ];
    }

    public function hosting(): static
    {
        return $this->state(fn (array $attributes): array => [
            'service_type_id' => $this->resolveServiceTypeId($attributes, slug: 'hosting', name: 'Hosting'),
            'billing_model' => RecurringServiceBillingModel::Fixed,
            'name' => 'Web Hosting',
            'cadence_unit' => RecurringServiceCadenceUnit::Month,
            'fixed_amount' => fake()->randomFloat(2, 250, 2500),
            'hourly_rate' => null,
            'included_quantity' => null,
            'remind_days_before' => [7, 1],
        ]);
    }

    public function domain(): static
    {
        return $this->state(fn (array $attributes): array => [
            'service_type_id' => $this->resolveServiceTypeId($attributes, slug: 'domain', name: 'Domain'),
            'billing_model' => RecurringServiceBillingModel::Fixed,
            'name' => 'Domain Renewal',
            'cadence_unit' => RecurringServiceCadenceUnit::Year,
            'fixed_amount' => fake()->randomFloat(2, 250, 1200),
            'hourly_rate' => null,
            'included_quantity' => null,
            'remind_days_before' => [30, 14, 7, 1],
        ]);
    }

    public function monthlyMaintenance(): static
    {
        return $this->state(fn (array $attributes): array => [
            'service_type_id' => $this->resolveServiceTypeId($attributes, slug: 'maintenance', name: 'Maintenance'),
            'billing_model' => RecurringServiceBillingModel::Fixed,
            'name' => 'Monthly Website Maintenance',
            'cadence_unit' => RecurringServiceCadenceUnit::Month,
            'fixed_amount' => fake()->randomFloat(2, 800, 8000),
            'hourly_rate' => null,
            'included_quantity' => null,
            'remind_days_before' => [7, 1],
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function resolveServiceTypeId(array $attributes, ?string $slug = null, ?string $name = null): int
    {
        $ownerId = $attributes['owner_id'] ?? null;
        $customerId = $attributes['customer_id'] ?? null;

        if (! is_int($ownerId)) {
            if ($customerId instanceof Customer) {
                $ownerId = $customerId->owner_id;
            } elseif (is_int($customerId) || (is_string($customerId) && ctype_digit($customerId))) {
                $ownerId = Customer::query()->find((int) $customerId)?->owner_id;
            }
        }

        if (! is_int($ownerId)) {
            $ownerId = User::factory()->create()->id;
        }

        if ($slug !== null && $name !== null) {
            return RecurringServiceType::query()->firstOrCreate(
                [
                    'owner_id' => $ownerId,
                    'slug' => $slug,
                ],
                [
                    'name' => $name,
                    'is_active' => true,
                ],
            )->id;
        }

        $existingTypeId = RecurringServiceType::query()
            ->where('owner_id', $ownerId)
            ->inRandomOrder()
            ->value('id');

        if (is_int($existingTypeId)) {
            return $existingTypeId;
        }

        return RecurringServiceType::query()->create([
            'owner_id' => $ownerId,
            'name' => 'Other',
            'slug' => 'other',
            'is_active' => true,
        ])->id;
    }
}
