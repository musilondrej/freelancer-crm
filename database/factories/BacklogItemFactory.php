<?php

namespace Database\Factories;

use App\Enums\BacklogItemPriority;
use App\Enums\BacklogItemStatus;
use App\Models\Activity;
use App\Models\BacklogItem;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BacklogItem>
 */
class BacklogItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'owner_id' => fn (array $attributes): ?int => Project::query()->find($attributes['project_id'])?->owner_id,
            'activity_id' => fn (array $attributes): ?int => $this->resolveActivityForProject($attributes['project_id'] ?? null)?->id,
            'title' => ucfirst(fake()->words(4, true)),
            'description' => fake()->optional(0.7)->sentence(),
            'status' => fake()->randomElement(BacklogItemStatus::openValues()),
            'priority' => fake()->randomElement(BacklogItemPriority::values()),
            'estimated_minutes' => fake()->optional(0.8)->numberBetween(30, 720),
            'due_date' => fake()->optional(0.6)->dateTimeBetween('now', '+2 months'),
            'sort_order' => fake()->numberBetween(0, 200),
            'converted_to_worklog_id' => null,
            'converted_at' => null,
            'meta' => [
                'source' => 'factory',
            ],
        ];
    }

    private function resolveActivityForProject(mixed $projectId): ?Activity
    {
        if (! is_numeric($projectId)) {
            return null;
        }

        $project = Project::query()->find((int) $projectId);

        if (! $project instanceof Project) {
            return null;
        }

        return Activity::query()
            ->where('owner_id', $project->owner_id)
            ->where('is_active', true)
            ->where(function (Builder $query) use ($project): void {
                $query->whereNull('project_id')
                    ->orWhere('project_id', $project->id);
            })
            ->inRandomOrder()
            ->first();
    }
}
