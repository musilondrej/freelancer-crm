<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Lead;
use App\Models\Note;
use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\RecurringService;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const COLOR_PALETTE = [
        '#0F172A',
        '#1D4ED8',
        '#059669',
        '#D97706',
        '#DC2626',
        '#7C3AED',
        '#0EA5E9',
        '#4B5563',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! User::query()->exists()) {
            $this->call(CustomerSeeder::class);
        }

        if (! Customer::query()->exists()) {
            $this->call(CustomerSeeder::class);
        }

        if (! Project::query()->exists()) {
            $this->call(ProjectSeeder::class);
        }

        if (! Note::query()->exists()) {
            $this->call(NoteSeeder::class);
        }

        if (! Lead::query()->exists()) {
            $this->call(LeadSeeder::class);
        }

        if (! ProjectActivity::query()->exists()) {
            $this->call(ProjectActivitySeeder::class);
        }

        if (! RecurringService::query()->exists()) {
            $this->call(RecurringServiceSeeder::class);
        }

        User::query()->each(function (User $owner): void {
            $tagNames = collect([
                'VIP',
                'Urgent',
                'Long-term',
                'Maintenance',
                'Potential Upsell',
                'Paused',
                'International',
                'Enterprise',
                'SMB',
                'Invoice Delayed',
            ])->shuffle()->take(fake()->numberBetween(5, 8))->values();

            $tags = $tagNames->values()->map(fn (string $name, int $index): Tag => Tag::query()->updateOrCreate(
                [
                    'owner_id' => $owner->id,
                    'slug' => Str::slug($name),
                ],
                [
                    'name' => $name,
                    'color' => fake()->randomElement(self::COLOR_PALETTE),
                    'sort_order' => ($index + 1) * 10,
                    'meta' => ['is_system' => false],
                ],
            ));

            $tagIds = $tags->pluck('id');

            Customer::query()
                ->where('owner_id', $owner->id)
                ->each(function (Customer $customer) use ($tagIds): void {
                    $customer->tags()->syncWithoutDetaching($tagIds->random(fake()->numberBetween(1, min(3, $tagIds->count())))->all());
                });

            Project::query()
                ->where('owner_id', $owner->id)
                ->each(function (Project $project) use ($tagIds): void {
                    $project->tags()->syncWithoutDetaching($tagIds->random(fake()->numberBetween(1, min(3, $tagIds->count())))->all());
                });

            Note::query()
                ->where('owner_id', $owner->id)
                ->each(function (Note $note) use ($tagIds): void {
                    if (fake()->boolean(60)) {
                        $note->tags()->syncWithoutDetaching($tagIds->random(fake()->numberBetween(1, min(2, $tagIds->count())))->all());
                    }
                });

            Lead::query()
                ->where('owner_id', $owner->id)
                ->each(function (Lead $lead) use ($tagIds): void {
                    if (fake()->boolean(65)) {
                        $lead->tags()->syncWithoutDetaching($tagIds->random(fake()->numberBetween(1, min(3, $tagIds->count())))->all());
                    }
                });

            ProjectActivity::query()
                ->where('owner_id', $owner->id)
                ->each(function (ProjectActivity $activity) use ($tagIds): void {
                    if (fake()->boolean(55)) {
                        $activity->tags()->syncWithoutDetaching($tagIds->random(fake()->numberBetween(1, min(2, $tagIds->count())))->all());
                    }
                });

            RecurringService::query()
                ->where('owner_id', $owner->id)
                ->each(function (RecurringService $service) use ($tagIds): void {
                    if (fake()->boolean(65)) {
                        $service->tags()->syncWithoutDetaching($tagIds->random(fake()->numberBetween(1, min(3, $tagIds->count())))->all());
                    }
                });
        });
    }
}
