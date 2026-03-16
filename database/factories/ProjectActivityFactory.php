<?php

namespace Database\Factories;

use App\Enums\ProjectActivityStatus;
use App\Enums\ProjectActivityType;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Worklog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Worklog>
 */
class ProjectActivityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(ProjectActivityType::cases());
        $isHourly = $type === ProjectActivityType::Hourly;

        return [
            'project_id' => Project::factory(),
            'owner_id' => fn (array $attributes): ?int => Project::query()->find($attributes['project_id'])?->owner_id,
            'activity_id' => fn (array $attributes): ?int => $this->resolveActivityForOwner($attributes['owner_id'] ?? null)?->id,
            'backlog_item_id' => null,
            'title' => function (array $attributes): string {
                $activityId = $attributes['activity_id'] ?? null;
                $activity = Activity::query()->find($activityId);

                if ($activity instanceof Activity) {
                    return $activity->name;
                }

                return ucfirst(fake()->words(4, true));
            },
            'description' => fake()->optional(0.7)->sentence(),
            'type' => $type,
            'status' => fake()->randomElement(ProjectActivityStatus::cases()),
            'is_running' => false,
            'is_billable' => function (array $attributes): bool {
                $activityId = $attributes['activity_id'] ?? null;
                $activity = Activity::query()->find($activityId);

                if ($activity instanceof Activity) {
                    return $activity->is_billable;
                }

                return fake()->boolean(90);
            },
            'is_invoiced' => false,
            'invoice_reference' => null,
            'invoiced_at' => null,
            'currency' => fake()->optional(0.8)->randomElement(['CZK', 'EUR', 'USD']),
            'quantity' => $isHourly ? fake()->randomFloat(2, 0.5, 80) : 1,
            'unit_rate' => function (array $attributes) use ($isHourly): ?float {
                if (! $isHourly) {
                    return null;
                }

                $activityId = $attributes['activity_id'] ?? null;
                $activityRate = Activity::query()->find($activityId)?->default_hourly_rate;

                if ($activityRate !== null) {
                    return (float) $activityRate;
                }

                return fake()->randomFloat(2, 500, 3200);
            },
            'flat_amount' => $isHourly ? null : fake()->randomFloat(2, 400, 18000),
            'tracked_minutes' => $isHourly ? fake()->numberBetween(30, 5200) : null,
            'due_date' => fake()->optional(0.6)->dateTimeBetween('now', '+3 months'),
            'started_at' => fake()->optional(0.6)->dateTimeBetween('-2 months', 'now'),
            'finished_at' => function (array $attributes): mixed {
                $status = $attributes['status'] ?? null;
                $ownerId = $attributes['owner_id'] ?? null;

                if (! is_string($status)) {
                    return null;
                }

                $resolvedStatus = $status instanceof ProjectActivityStatus
                    ? $status
                    : ProjectActivityStatus::tryFrom($status);

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

    private function resolveActivityForOwner(mixed $ownerId): ?Activity
    {
        if (! is_numeric($ownerId)) {
            return null;
        }

        return Activity::query()
            ->where('owner_id', (int) $ownerId)
            ->where('is_active', true)
            ->inRandomOrder()
            ->first();
    }

    public function hourly(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ProjectActivityType::Hourly,
            'is_running' => false,
            'quantity' => fake()->randomFloat(2, 1, 60),
            'unit_rate' => fake()->randomFloat(2, 500, 3200),
            'flat_amount' => null,
            'tracked_minutes' => fake()->numberBetween(60, 4800),
        ]);
    }

    public function oneTime(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => ProjectActivityType::OneTime,
            'is_running' => false,
            'quantity' => 1,
            'unit_rate' => null,
            'flat_amount' => fake()->randomFloat(2, 400, 20000),
            'tracked_minutes' => null,
        ]);
    }
}
