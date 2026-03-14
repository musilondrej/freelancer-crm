<?php

namespace Database\Seeders;

use App\Models\ProjectActivityStatusOption;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectActivityStatusOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()
            ->pluck('id')
            ->each(function (int $ownerId): void {
                ProjectActivityStatusOption::ensureDefaultsForOwner($ownerId);
            });
    }
}
