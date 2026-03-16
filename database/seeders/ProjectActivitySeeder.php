<?php

namespace Database\Seeders;

use App\Enums\ProjectActivityStatus;
use App\Models\Activity;
use App\Models\Project;
use App\Models\Worklog;
use Illuminate\Database\Seeder;

class ProjectActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Project::query()->exists()) {
            $this->call(ProjectSeeder::class);
        }

        if (! Activity::query()->exists()) {
            $this->call(ActivitySeeder::class);
        }

        Project::query()->each(function (Project $project): void {
            $entriesCount = fake()->numberBetween(6, 14);
            $statusCodes = $this->resolveStatusCodesForOwner();
            $activityPool = Activity::query()
                ->where('owner_id', $project->owner_id)
                ->where(function ($query) use ($project): void {
                    $query->whereNull('project_id')
                        ->orWhere('project_id', $project->id);
                })
                ->where('is_active', true)
                ->get();

            for ($index = 0; $index < $entriesCount; $index++) {
                $selectedActivity = $activityPool->isNotEmpty()
                    ? $activityPool->random()
                    : null;
                $isHourly = fake()->boolean(60);

                $factory = Worklog::factory()
                    ->for($project)
                    ->state([
                        'owner_id' => $project->owner_id,
                        'activity_id' => $selectedActivity?->id,
                        'title' => $selectedActivity !== null ? $selectedActivity->name : ucfirst(fake()->words(4, true)),
                        'is_billable' => $selectedActivity !== null ? $selectedActivity->is_billable : fake()->boolean(90),
                        'unit_rate' => $selectedActivity?->default_hourly_rate,
                        'currency' => fake()->boolean(85) ? $project->effectiveCurrency() : null,
                    ]);

                if ($isHourly) {
                    $factory = $factory
                        ->hourly()
                        ->state([
                            'status' => fake()->randomElement($statusCodes),
                        ]);
                } else {
                    $factory = $factory->oneTime();
                }

                $factory->create();
            }
        });
    }

    /**
     * @return list<string>
     */
    private function resolveStatusCodesForOwner(): array
    {
        return ProjectActivityStatus::values();
    }
}
