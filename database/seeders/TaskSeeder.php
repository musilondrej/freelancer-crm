<?php

namespace Database\Seeders;

use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Project::query()->exists()) {
            $this->call(ProjectSeeder::class);
        }

        Project::query()->each(function (Project $project): void {
            $entriesCount = fake()->numberBetween(6, 14);
            $statusCodes = $this->resolveStatusCodesForOwner();

            for ($index = 0; $index < $entriesCount; $index++) {
                $isHourly = fake()->boolean(60);

                $factory = Task::factory()
                    ->for($project)
                    ->state([
                        'owner_id' => $project->owner_id,
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
        return TaskStatus::values();
    }
}
