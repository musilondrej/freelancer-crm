<?php

namespace Database\Seeders;

use App\Models\ClientContact;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Customer::query()->exists()) {
            $this->call(CustomerSeeder::class);
        }

        if (! ClientContact::query()->exists()) {
            $this->call(ClientContactSeeder::class);
        }

        Customer::query()
            ->with('contacts')
            ->each(function (Customer $client): void {
                $primaryContactId = $client->contacts->isNotEmpty()
                    ? $client->contacts->random()->id
                    : null;

                $projectsCount = fake()->numberBetween(1, 4);

                for ($index = 0; $index < $projectsCount; $index++) {
                    $projectFactory = Project::factory()
                        ->for($client)
                        ->state([
                            'owner_id' => $client->owner_id,
                            'primary_contact_id' => $primaryContactId,
                            'currency' => fake()->boolean(80) ? $client->billing_currency : null,
                        ]);

                    if (fake()->boolean(45)) {
                        $projectFactory = $projectFactory->hourly();
                    } elseif (fake()->boolean(20)) {
                        $projectFactory = $projectFactory->retainer();
                    }

                    $projectFactory->create();
                }
            });
    }
}
