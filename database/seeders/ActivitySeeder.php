<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Project::query()->exists()) {
            $this->call(ProjectSeeder::class);
        }

        User::query()->each(function (User $owner): void {
            $this->seedGlobalActivities($owner);
        });

        Project::query()->each(function (Project $project): void {
            $this->seedProjectActivities($project);
        });
    }

    private function seedGlobalActivities(User $owner): void
    {
        $globalActivityDefinitions = [
            ['name' => 'General Development', 'is_billable' => true],
            ['name' => 'Meetings', 'is_billable' => true],
            ['name' => 'Internal Admin', 'is_billable' => false],
        ];

        foreach ($globalActivityDefinitions as $index => $definition) {
            Activity::query()->updateOrCreate(
                [
                    'owner_id' => $owner->id,
                    'project_id' => null,
                    'name' => $definition['name'],
                ],
                [
                    'description' => 'Global activity available for any project.',
                    'default_hourly_rate' => $definition['is_billable']
                        ? $owner->default_hourly_rate
                        : null,
                    'is_billable' => $definition['is_billable'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'meta' => [
                        'seeded' => 'activity_seeder',
                        'scope' => 'global',
                    ],
                ],
            );
        }
    }

    private function seedProjectActivities(Project $project): void
    {
        $projectActivityDefinitions = [
            ['name' => 'Development', 'is_billable' => true],
            ['name' => 'QA / Testing', 'is_billable' => true],
            ['name' => 'Project Management', 'is_billable' => true],
        ];

        foreach ($projectActivityDefinitions as $index => $definition) {
            Activity::query()->updateOrCreate(
                [
                    'owner_id' => $project->owner_id,
                    'project_id' => $project->id,
                    'name' => $definition['name'],
                ],
                [
                    'description' => sprintf('Default activity for project %s.', $project->name),
                    'default_hourly_rate' => $project->hourly_rate,
                    'is_billable' => $definition['is_billable'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'meta' => [
                        'seeded' => 'activity_seeder',
                        'scope' => 'project',
                    ],
                ],
            );
        }
    }
}
