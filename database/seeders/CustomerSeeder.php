<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owners = User::query()->get();

        if ($owners->isEmpty()) {
            $owners = User::factory()->count(2)->create();
        }

        $owners->each(function (User $owner): void {
            Customer::factory()
                ->count(fake()->numberBetween(4, 8))
                ->for($owner, 'owner')
                ->create();
        });
    }
}
