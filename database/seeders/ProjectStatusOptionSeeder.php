<?php

namespace Database\Seeders;

use App\Models\ProjectStatusOption;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProjectStatusOptionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()
            ->pluck('id')
            ->each(function (int $ownerId): void {
                ProjectStatusOption::ensureDefaultsForOwner($ownerId);
            });
    }
}
