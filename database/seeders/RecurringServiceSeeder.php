<?php

namespace Database\Seeders;

use App\Enums\RecurringServiceBillingModel;
use App\Enums\RecurringServiceCadenceUnit;
use App\Models\Customer;
use App\Models\Project;
use App\Models\RecurringService;
use App\Models\RecurringServiceType;
use App\Models\User;
use Illuminate\Database\Seeder;

class RecurringServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! Customer::query()->exists()) {
            $this->call(CustomerSeeder::class);
        }

        if (! Project::query()->exists()) {
            $this->call(ProjectSeeder::class);
        }

        $this->call(RecurringServiceTypeSeeder::class);

        User::query()->each(function (User $owner): void {
            $customers = Customer::query()
                ->where('owner_id', $owner->id)
                ->with('projects')
                ->get();

            $serviceTypes = RecurringServiceType::query()
                ->where('owner_id', $owner->id)
                ->get()
                ->keyBy('slug');

            $customers->each(function (Customer $customer) use ($owner, $serviceTypes): void {
                $project = $customer->projects->isNotEmpty()
                    ? $customer->projects->random()
                    : null;

                RecurringService::factory()
                    ->hosting()
                    ->state([
                        'owner_id' => $owner->id,
                        'customer_id' => $customer->id,
                        'project_id' => $project?->id,
                        'service_type_id' => $serviceTypes->get('hosting')?->id
                            ?? $serviceTypes->get('other')?->id
                            ?? $serviceTypes->first()?->id,
                        'currency' => $customer->effectiveCurrency() ?? $owner->default_currency,
                    ])
                    ->create();

                RecurringService::factory()
                    ->domain()
                    ->state([
                        'owner_id' => $owner->id,
                        'customer_id' => $customer->id,
                        'project_id' => $project?->id,
                        'service_type_id' => $serviceTypes->get('domain')?->id
                            ?? $serviceTypes->get('other')?->id
                            ?? $serviceTypes->first()?->id,
                        'currency' => $customer->effectiveCurrency() ?? $owner->default_currency,
                    ])
                    ->create();

                RecurringService::factory()
                    ->monthlyMaintenance()
                    ->state([
                        'owner_id' => $owner->id,
                        'customer_id' => $customer->id,
                        'project_id' => $project?->id,
                        'service_type_id' => $serviceTypes->get('maintenance')?->id
                            ?? $serviceTypes->get('other')?->id
                            ?? $serviceTypes->first()?->id,
                        'currency' => $customer->effectiveCurrency() ?? $owner->default_currency,
                    ])
                    ->create();

                if (fake()->boolean(35)) {
                    RecurringService::factory()
                        ->state([
                            'owner_id' => $owner->id,
                            'customer_id' => $customer->id,
                            'project_id' => $project?->id,
                            'currency' => $customer->effectiveCurrency() ?? $owner->default_currency,
                            'service_type_id' => $serviceTypes->get('support')?->id
                                ?? $serviceTypes->get('other')?->id
                                ?? $serviceTypes->first()?->id,
                            'billing_model' => RecurringServiceBillingModel::Hourly,
                            'name' => 'Support Retainer',
                            'cadence_unit' => RecurringServiceCadenceUnit::Month,
                            'hourly_rate' => fake()->randomFloat(2, 700, 3000),
                            'included_quantity' => fake()->randomFloat(2, 2, 18),
                            'fixed_amount' => null,
                        ])
                        ->create();
                }
            });
        });
    }
}
