<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\ProjectPipelineStage;
use App\Enums\ProjectPricingModel;
use App\Enums\ProjectStatus;
use App\Models\Customer;
use App\Models\Project;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->optional(0.8)->dateTimeBetween('-3 months', '+1 month');
        $targetEndDate = $startDate instanceof DateTimeInterface
            ? fake()->optional(0.7)->dateTimeBetween($startDate, '+6 months')
            : fake()->optional(0.7)->dateTimeBetween('now', '+6 months');

        return [
            'customer_id' => Customer::factory(),
            'owner_id' => fn (array $attributes): ?int => Customer::query()->find($attributes['customer_id'])?->owner_id,
            'primary_contact_id' => null,
            'name' => ucfirst(fake()->words(3, true)),
            'status' => fake()->randomElement(ProjectStatus::cases()),
            'pipeline_stage' => fake()->randomElement(ProjectPipelineStage::cases()),
            'pricing_model' => ProjectPricingModel::Fixed,
            'priority' => fake()->randomElement(Priority::cases()),
            'start_date' => $startDate,
            'target_end_date' => $targetEndDate,
            'closed_date' => null,
            'currency' => fake()->optional(0.8)->randomElement(['CZK', 'EUR', 'USD']),
            'hourly_rate' => null,
            'fixed_price' => fake()->randomFloat(2, 8000, 250000),
            'estimated_hours' => fake()->optional(0.6)->randomFloat(2, 8, 240),
            'estimated_value' => fake()->optional(0.7)->randomFloat(2, 8000, 250000),
            'actual_value' => null,
            'description' => fake()->optional(0.8)->paragraph(),
            'last_activity_at' => fake()->optional(0.7)->dateTimeBetween('-2 months', 'now'),
            'meta' => [
                'delivery_mode' => fake()->randomElement(['remote', 'hybrid', 'on-site']),
            ],
        ];
    }

    public function hourly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'pricing_model' => ProjectPricingModel::Hourly,
            'hourly_rate' => fake()->randomFloat(2, 600, 3500),
            'fixed_price' => null,
        ]);
    }

    public function retainer(): static
    {
        return $this->state(fn (array $attributes): array => [
            'pricing_model' => ProjectPricingModel::Retainer,
            'hourly_rate' => fake()->randomFloat(2, 600, 3000),
        ]);
    }
}
