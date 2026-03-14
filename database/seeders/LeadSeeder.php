<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use Illuminate\Database\Seeder;

class LeadSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! LeadSource::query()->exists()) {
            $this->call(LeadSourceSeeder::class);
        }

        if (! Customer::query()->exists()) {
            $this->call(CustomerSeeder::class);
        }

        User::query()->each(function (User $owner): void {
            $sources = LeadSource::query()
                ->where('owner_id', $owner->id)
                ->get();

            $customers = Customer::query()
                ->where('owner_id', $owner->id)
                ->get();

            $leadsCount = fake()->numberBetween(8, 16);

            for ($index = 0; $index < $leadsCount; $index++) {
                $convertedCustomerId = $customers->isNotEmpty() && fake()->boolean(25)
                    ? $customers->random()->id
                    : null;

                $factory = Lead::factory()
                    ->for($owner, 'owner')
                    ->state([
                        'lead_source_id' => $sources->isNotEmpty() ? $sources->random()->id : null,
                        'customer_id' => $convertedCustomerId,
                        'currency' => $owner->default_currency,
                    ]);

                if ($convertedCustomerId !== null) {
                    $factory = $factory->won();
                } elseif (fake()->boolean(15)) {
                    $factory = $factory->lost();
                }

                $factory->create();
            }
        });
    }
}
