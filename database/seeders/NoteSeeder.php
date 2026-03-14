<?php

namespace Database\Seeders;

use App\Models\ClientContact;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Note;
use App\Models\Project;
use App\Models\RecurringService;
use App\Models\Worklog;
use Illuminate\Database\Seeder;

class NoteSeeder extends Seeder
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

        if (! Project::query()->exists()) {
            $this->call(ProjectSeeder::class);
        }

        if (! Worklog::query()->exists()) {
            $this->call(ProjectActivitySeeder::class);
        }

        if (! RecurringService::query()->exists()) {
            $this->call(RecurringServiceSeeder::class);
        }

        if (! Lead::query()->exists()) {
            $this->call(LeadSeeder::class);
        }

        Customer::query()
            ->with(['contacts', 'projects'])
            ->each(function (Customer $client): void {
                Note::factory()
                    ->count(fake()->numberBetween(0, 2))
                    ->for($client, 'noteable')
                    ->state([
                        'owner_id' => $client->owner_id,
                    ])
                    ->create();

                $client->contacts->each(function (ClientContact $contact) use ($client): void {
                    Note::factory()
                        ->count(fake()->numberBetween(0, 1))
                        ->for($contact, 'noteable')
                        ->state([
                            'owner_id' => $client->owner_id,
                        ])
                        ->create();
                });

                $client->projects->each(function (Project $project) use ($client): void {
                    $notes = Note::factory()
                        ->count(fake()->numberBetween(0, 3))
                        ->for($project, 'noteable')
                        ->state([
                            'owner_id' => $client->owner_id,
                        ])
                        ->create();

                    if ($notes->isNotEmpty() && fake()->boolean(20)) {
                        $notes->first()->update(['is_pinned' => true]);
                    }
                });
            });

        Lead::query()->each(function (Lead $lead): void {
            $notes = Note::factory()
                ->count(fake()->numberBetween(0, 2))
                ->for($lead, 'noteable')
                ->state([
                    'owner_id' => $lead->owner_id,
                ])
                ->create();

            if ($notes->isNotEmpty() && fake()->boolean(15)) {
                $notes->first()->update(['is_pinned' => true]);
            }
        });

        Worklog::query()->each(function (Worklog $activity): void {
            $notes = Note::factory()
                ->count(fake()->numberBetween(0, 1))
                ->for($activity, 'noteable')
                ->state([
                    'owner_id' => $activity->owner_id,
                ])
                ->create();

            if ($notes->isNotEmpty() && fake()->boolean(10)) {
                $notes->first()->update(['is_pinned' => true]);
            }
        });

        RecurringService::query()->each(function (RecurringService $service): void {
            $notes = Note::factory()
                ->count(fake()->numberBetween(0, 2))
                ->for($service, 'noteable')
                ->state([
                    'owner_id' => $service->owner_id,
                ])
                ->create();

            if ($notes->isNotEmpty() && fake()->boolean(20)) {
                $notes->first()->update(['is_pinned' => true]);
            }
        });
    }
}
