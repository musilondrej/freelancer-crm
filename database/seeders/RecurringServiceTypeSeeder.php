<?php

namespace Database\Seeders;

use App\Models\RecurringServiceType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RecurringServiceTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! User::query()->exists()) {
            User::factory()->count(2)->create();
        }

        $defaults = [
            'Hosting',
            'Domain',
            'Maintenance',
            'Support',
            'License',
            'Retainer',
            'Other',
        ];

        User::query()->each(function (User $owner) use ($defaults): void {
            collect($defaults)->values()->each(function (string $name, int $index) use ($owner): void {
                RecurringServiceType::query()->updateOrCreate(
                    [
                        'owner_id' => $owner->id,
                        'slug' => Str::slug($name),
                    ],
                    [
                        'name' => $name,
                        'is_active' => true,
                        'sort_order' => ($index + 1) * 10,
                        'meta' => ['is_system' => true],
                    ],
                );
            });
        });
    }
}
