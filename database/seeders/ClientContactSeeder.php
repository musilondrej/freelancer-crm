<?php

namespace Database\Seeders;

use App\Models\ClientContact;
use App\Models\Customer;
use Illuminate\Database\Seeder;

class ClientContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Customer::query()->exists()) {
            $this->call(CustomerSeeder::class);
        }

        Customer::query()->each(function (Customer $client): void {
            $contacts = ClientContact::factory()
                ->count(fake()->numberBetween(1, 3))
                ->for($client)
                ->state([
                    'owner_id' => $client->owner_id,
                ])
                ->create();

            $contacts->random()->update(['is_primary' => true]);
            $contacts->random()->update(['is_billing_contact' => true]);
        });
    }
}
