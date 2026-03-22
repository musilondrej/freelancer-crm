<?php

namespace Database\Factories;

use App\Enums\Priority;
use App\Enums\TaskBillingModel;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $billingModel = fake()->randomElement(TaskBillingModel::cases());
        $isHourly = $billingModel === TaskBillingModel::Hourly;

        return [
            'project_id' => Project::factory(),
            'owner_id' => fn (array $attributes): ?int => Project::query()->find($attributes['project_id'])?->owner_id,
            'priority' => fake()->randomElement(Priority::cases()),
            'title' => ucfirst(fake()->words(4, true)),
            'description' => fake()->optional(0.7)->sentence(),
            'billing_model' => $billingModel,
            'status' => fake()->randomElement(TaskStatus::cases()),
            'is_billable' => fake()->boolean(90),
            'track_time' => $isHourly,
            'currency' => fake()->optional(0.8)->randomElement(['CZK', 'EUR', 'USD']),
            'quantity' => null,
            'hourly_rate_override' => function (array $attributes) use ($isHourly): ?float {
                if (! $isHourly) {
                    return null;
                }

                return fake()->randomFloat(2, 500, 3200);
            },
            'fixed_price' => $isHourly ? null : fake()->randomFloat(2, 400, 18000),
            'estimated_minutes' => fake()->optional(0.4)->numberBetween(30, 4800),
            'due_date' => fake()->optional(0.6)->dateTimeBetween('now', '+3 months'),
            'completed_at' => function (array $attributes): mixed {
                $status = $attributes['status'] ?? null;

                if (! is_string($status)) {
                    return null;
                }

                $resolvedStatus = $status instanceof TaskStatus
                    ? $status
                    : TaskStatus::tryFrom($status);

                if ($resolvedStatus === null || ! $resolvedStatus->isDone()) {
                    return null;
                }

                return fake()->optional(0.8)->dateTimeBetween('-1 month', 'now');
            },
            'meta' => [
                'category' => fake()->randomElement(['development', 'consulting', 'support', 'design']),
            ],
        ];
    }

    public function hourly(): static
    {
        return $this->state(fn (): array => [
            'billing_model' => TaskBillingModel::Hourly,
            'track_time' => true,
            'quantity' => null,
            'hourly_rate_override' => fake()->randomFloat(2, 500, 3200),
            'fixed_price' => null,
        ]);
    }

    public function planned(): static
    {
        return $this->state(fn (): array => [
            'billing_model' => TaskBillingModel::Hourly,
            'status' => TaskStatus::Planned,
            'priority' => fake()->randomElement(Priority::cases()),
            'estimated_minutes' => fake()->numberBetween(60, 4800),
            'completed_at' => null,
        ]);
    }

    public function oneTime(): static
    {
        return $this->state(fn (): array => [
            'billing_model' => TaskBillingModel::FixedPrice,
            'track_time' => false,
            'quantity' => null,
            'hourly_rate_override' => null,
            'fixed_price' => fake()->randomFloat(2, 400, 20000),
        ]);
    }
}
