<?php

namespace Database\Seeders;

use App\Models\LeadSource;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LeadSourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! User::query()->exists()) {
            User::factory()->count(2)->create();
        }

        User::query()->each(function (User $owner): void {
            collect([
                'Referral',
                'LinkedIn',
                'Website',
                'Cold Outreach',
                'Returning Client',
                'Partner',
                'Community',
            ])->values()->each(function (string $name, int $index) use ($owner): void {
                LeadSource::query()->firstOrCreate(
                    [
                        'owner_id' => $owner->id,
                        'slug' => Str::slug($name),
                    ],
                    [
                        'name' => $name,
                        'is_active' => true,
                        'sort_order' => $index,
                        'meta' => ['is_system' => true],
                    ],
                );
            });
        });
    }
}
