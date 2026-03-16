<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Seeder;

class ActivitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->each(function (User $owner): void {
            $this->seedActivities($owner);
        });
    }

    private function seedActivities(User $owner): void
    {
        $definitions = [
            ['name' => 'General Development', 'is_billable' => true],
            ['name' => 'Meetings', 'is_billable' => true],
            ['name' => 'Internal Admin', 'is_billable' => false],
            ['name' => 'Development', 'is_billable' => true],
            ['name' => 'QA / Testing', 'is_billable' => true],
            ['name' => 'Project Management', 'is_billable' => true],
        ];

        foreach ($definitions as $index => $definition) {
            Activity::query()->updateOrCreate(
                [
                    'owner_id' => $owner->id,
                    'name' => $definition['name'],
                ],
                [
                    'description' => 'Seeded activity.',
                    'default_hourly_rate' => $definition['is_billable']
                        ? $owner->default_hourly_rate
                        : null,
                    'is_billable' => $definition['is_billable'],
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'meta' => [
                        'seeded' => 'activity_seeder',
                    ],
                ],
            );
        }
    }
}
