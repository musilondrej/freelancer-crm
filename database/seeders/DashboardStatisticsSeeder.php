<?php

namespace Database\Seeders;

use App\Enums\LeadStatus;
use App\Enums\Priority;
use App\Enums\ProjectPipelineStage;
use App\Enums\ProjectPricingModel;
use App\Enums\ProjectStatus;
use App\Enums\TaskBillingModel;
use App\Enums\TaskStatus;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DashboardStatisticsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'email_verified_at' => now(),
                'default_currency' => 'CZK',
                'default_hourly_rate' => 1500,
            ],
        );

        User::query()->each(function (User $owner): void {
            $leadSources = $this->seedLeadSources($owner);
            $customers = $this->seedCustomers($owner);
            $projects = $this->seedProjects($owner, $customers);

            $this->seedProjectActivities($owner, $projects);
            $this->seedLeads($owner, $leadSources, $customers);
        });
    }

    /**
     * @return Collection<int, LeadSource>
     */
    private function seedLeadSources(User $owner): Collection
    {
        $sourceNames = [
            'Referral',
            'LinkedIn',
            'Website',
            'Community',
            'Cold Outreach',
        ];

        foreach ($sourceNames as $index => $name) {
            LeadSource::query()->updateOrCreate(
                [
                    'owner_id' => $owner->id,
                    'slug' => Str::slug($name),
                ],
                [
                    'name' => $name,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'meta' => ['seeded' => 'dashboard_statistics'],
                ],
            );
        }

        return LeadSource::query()
            ->where('owner_id', $owner->id)
            ->whereIn('slug', collect($sourceNames)->map(fn (string $name): string => Str::slug($name)))
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * @return array<string, Customer>
     */
    private function seedCustomers(User $owner): array
    {
        return [
            'cz' => $this->upsertCustomer($owner, [
                'name' => 'Acme Digital s.r.o.',
                'legal_name' => 'Acme Digital s.r.o.',
                'registration_number' => '28495011',
                'vat_id' => 'CZ28495011',
                'email' => 'billing+acme@crmseed.test',
                'phone' => '+420 777 100 200',
                'website' => 'https://acme-digital.example',
                'timezone' => 'Europe/Prague',
                'billing_currency' => 'CZK',
                'hourly_rate' => 1650,
                'is_active' => true,
                'source' => 'repeat-client',
                'last_contacted_at' => now()->subDays(5),
                'next_follow_up_at' => now()->addDays(14),
                'internal_summary' => 'Primary local customer with monthly support.',
                'meta' => [
                    'seeded' => 'dashboard_statistics',
                    'industry' => 'SaaS',
                ],
            ]),
            'eur' => $this->upsertCustomer($owner, [
                'name' => 'Northwind GmbH',
                'legal_name' => 'Northwind GmbH',
                'registration_number' => 'DE90238477',
                'vat_id' => 'DE90238477',
                'email' => 'finance+northwind@crmseed.test',
                'phone' => '+49 151 0000 9999',
                'website' => 'https://northwind.example',
                'timezone' => 'Europe/Berlin',
                'billing_currency' => 'EUR',
                'hourly_rate' => 95,
                'is_active' => true,
                'source' => 'linkedin',
                'last_contacted_at' => now()->subDays(2),
                'next_follow_up_at' => now()->addDays(10),
                'internal_summary' => 'Product team outsourcing selected engineering work.',
                'meta' => [
                    'seeded' => 'dashboard_statistics',
                    'industry' => 'Fintech',
                ],
            ]),
            'usd' => $this->upsertCustomer($owner, [
                'name' => 'Pacific Apps LLC',
                'legal_name' => 'Pacific Apps LLC',
                'registration_number' => 'US-TX-294772',
                'vat_id' => 'US29477211',
                'email' => 'accounts+pacific@crmseed.test',
                'phone' => '+1 512 900 1200',
                'website' => 'https://pacificapps.example',
                'timezone' => 'America/Chicago',
                'billing_currency' => 'USD',
                'hourly_rate' => 120,
                'is_active' => true,
                'source' => 'referral',
                'last_contacted_at' => now()->subDays(1),
                'next_follow_up_at' => now()->addDays(8),
                'internal_summary' => 'US customer with API integrations and maintenance.',
                'meta' => [
                    'seeded' => 'dashboard_statistics',
                    'industry' => 'E-commerce',
                ],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertCustomer(User $owner, array $attributes): Customer
    {
        $customer = Customer::query()
            ->withTrashed()
            ->firstOrNew([
                'owner_id' => $owner->id,
                'vat_id' => $attributes['vat_id'],
            ]);

        $customer->fill($attributes);
        $customer->owner()->associate($owner);
        $customer->save();

        if ($customer->trashed()) {
            $customer->restore();
        }

        return $customer->refresh();
    }

    /**
     * @param  array<string, Customer>  $customers
     * @return array<string, Project>
     */
    private function seedProjects(User $owner, array $customers): array
    {
        $projectStatusCodes = $this->resolveProjectStatusCodes();

        return [
            'retainer_cz' => $this->upsertProject($owner, $customers['cz'], [
                'name' => 'Acme Monthly Delivery',
                'status' => $projectStatusCodes['in_progress'],
                'pipeline_stage' => ProjectPipelineStage::Won,
                'pricing_model' => ProjectPricingModel::Hourly,
                'priority' => Priority::High,
                'start_date' => CarbonImmutable::now()->subMonths(6)->startOfMonth(),
                'target_end_date' => null,
                'closed_date' => null,
                'currency' => 'CZK',
                'hourly_rate' => 1700,
                'fixed_price' => null,
                'estimated_hours' => 240,
                'estimated_value' => 408000,
                'actual_value' => null,
                'description' => 'Continuous product development and support.',
                'last_activity_at' => now()->subHours(5),
                'meta' => [
                    'seeded' => 'dashboard_statistics',
                ],
            ]),
            'fixed_eur' => $this->upsertProject($owner, $customers['eur'], [
                'name' => 'Northwind Checkout Revamp',
                'status' => $projectStatusCodes['planned'],
                'pipeline_stage' => ProjectPipelineStage::Proposal,
                'pricing_model' => ProjectPricingModel::Fixed,
                'priority' => Priority::Normal,
                'start_date' => CarbonImmutable::now()->subMonths(1)->startOfMonth(),
                'target_end_date' => CarbonImmutable::now()->addMonths(2)->endOfMonth(),
                'closed_date' => null,
                'currency' => 'EUR',
                'hourly_rate' => null,
                'fixed_price' => 18500,
                'estimated_hours' => 110,
                'estimated_value' => 18500,
                'actual_value' => null,
                'description' => 'Checkout redesign for conversion uplift.',
                'last_activity_at' => now()->subDays(1),
                'meta' => [
                    'seeded' => 'dashboard_statistics',
                ],
            ]),
            'api_usd' => $this->upsertProject($owner, $customers['usd'], [
                'name' => 'Pacific API Integration',
                'status' => $projectStatusCodes['blocked_or_open'],
                'pipeline_stage' => ProjectPipelineStage::Negotiation,
                'pricing_model' => ProjectPricingModel::Hourly,
                'priority' => Priority::Critical,
                'start_date' => CarbonImmutable::now()->subMonths(2)->startOfMonth(),
                'target_end_date' => CarbonImmutable::now()->addMonth()->endOfMonth(),
                'closed_date' => null,
                'currency' => 'USD',
                'hourly_rate' => 125,
                'fixed_price' => null,
                'estimated_hours' => 160,
                'estimated_value' => 20000,
                'actual_value' => null,
                'description' => 'Integration with logistics and invoicing APIs.',
                'last_activity_at' => now()->subDays(3),
                'meta' => [
                    'seeded' => 'dashboard_statistics',
                ],
            ]),
            'legacy_done' => $this->upsertProject($owner, $customers['cz'], [
                'name' => 'Legacy Platform Stabilization',
                'status' => $projectStatusCodes['completed_or_done'],
                'pipeline_stage' => ProjectPipelineStage::Won,
                'pricing_model' => ProjectPricingModel::Retainer,
                'priority' => Priority::Low,
                'start_date' => CarbonImmutable::now()->subMonths(10)->startOfMonth(),
                'target_end_date' => CarbonImmutable::now()->subMonths(1)->endOfMonth(),
                'closed_date' => CarbonImmutable::now()->subMonths(1)->endOfMonth(),
                'currency' => 'CZK',
                'hourly_rate' => 1550,
                'fixed_price' => null,
                'estimated_hours' => 90,
                'estimated_value' => 139500,
                'actual_value' => 148250,
                'description' => 'Completed legacy stabilization package.',
                'last_activity_at' => now()->subMonths(1),
                'meta' => [
                    'seeded' => 'dashboard_statistics',
                ],
            ]),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function upsertProject(User $owner, Customer $customer, array $attributes): Project
    {
        $project = Project::query()
            ->withTrashed()
            ->firstOrNew([
                'owner_id' => $owner->id,
                'name' => $attributes['name'],
            ]);

        $project->fill($attributes);
        $project->owner()->associate($owner);
        $project->customer()->associate($customer);
        $project->save();

        if ($project->trashed()) {
            $project->restore();
        }

        return $project->refresh();
    }

    /**
     * @param  array<string, Project>  $projects
     */
    private function seedProjectActivities(User $owner, array $projects): void
    {
        $taskStatusCodes = $this->resolveTaskStatusCodes();

        Task::query()
            ->withTrashed()
            ->where('owner_id', $owner->id)
            ->where('title', 'like', '[Seed Stats]%')
            ->forceDelete();

        $start = CarbonImmutable::today()->subDays(120);
        $end = CarbonImmutable::today();

        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            $dailyMinutes = $this->workedMinutesForDate($day);
            $invoicedAt = $day->lessThanOrEqualTo(CarbonImmutable::today()->subDays(21))
                ? $day->addDays(7)->setTime(9, 0)
                : null;

            if ($dailyMinutes > 0) {
                Task::factory()
                    ->for($projects['retainer_cz'])
                    ->state([
                        'owner_id' => $owner->id,
                        'title' => '[Seed Stats] Development '.$day->format('Y-m-d'),
                        'billing_model' => TaskBillingModel::Hourly,
                        'status' => $taskStatusCodes['done'],
                        'is_billable' => true,
                        'track_time' => true,
                        'currency' => 'CZK',
                        'quantity' => null,
                        'hourly_rate_override' => 1700,
                        'fixed_price' => null,
                        'completed_at' => $day->setTime(17, 30),
                        'due_date' => $day,
                        'meta' => array_filter([
                            'seeded' => 'dashboard_statistics',
                            'channel' => 'delivery',
                        ]),
                    ])
                    ->create()
                    ->timeEntries()
                    ->create([
                        'owner_id' => $owner->id,
                        'started_at' => $day->setTime(9, 0),
                        'ended_at' => $day->setTime(17, 30),
                        'minutes' => $dailyMinutes,
                        'meta' => ['seeded' => 'dashboard_statistics'],
                    ]);
            }

            if ($day->isMonday() || $day->isThursday()) {
                Task::factory()
                    ->for($projects['api_usd'])
                    ->state([
                        'owner_id' => $owner->id,
                        'title' => '[Seed Stats] API sync '.$day->format('Y-m-d'),
                        'billing_model' => TaskBillingModel::Hourly,
                        'status' => $taskStatusCodes['done'],
                        'is_billable' => true,
                        'track_time' => true,
                        'currency' => 'USD',
                        'quantity' => null,
                        'hourly_rate_override' => 125,
                        'fixed_price' => null,
                        'completed_at' => $day->setTime(19, 0),
                        'due_date' => $day,
                        'meta' => array_filter([
                            'seeded' => 'dashboard_statistics',
                            'channel' => 'integration',
                        ]),
                    ])
                    ->create()
                    ->timeEntries()
                    ->create([
                        'owner_id' => $owner->id,
                        'started_at' => $day->setTime(18, 0),
                        'ended_at' => $day->setTime(19, 0),
                        'minutes' => 60,
                        'meta' => ['seeded' => 'dashboard_statistics'],
                    ]);
            }

            if (in_array($day->day, [5, 20], true)) {
                Task::factory()
                    ->for($projects['fixed_eur'])
                    ->state([
                        'owner_id' => $owner->id,
                        'title' => '[Seed Stats] Milestone '.$day->format('Y-m-d'),
                        'billing_model' => TaskBillingModel::FixedPrice,
                        'status' => $taskStatusCodes['done'],
                        'is_billable' => true,
                        'track_time' => false,
                        'currency' => 'EUR',
                        'quantity' => 1,
                        'hourly_rate_override' => null,
                        'fixed_price' => 850,
                        'completed_at' => $day->setTime(15, 0),
                        'due_date' => $day,
                        'meta' => array_filter([
                            'seeded' => 'dashboard_statistics',
                            'channel' => 'milestone',
                        ]),
                    ])
                    ->create();
            }

            if ($day->isTuesday()) {
                Task::factory()
                    ->for($projects['retainer_cz'])
                    ->state([
                        'owner_id' => $owner->id,
                        'title' => '[Seed Stats] Internal sync '.$day->format('Y-m-d'),
                        'billing_model' => TaskBillingModel::Hourly,
                        'status' => $taskStatusCodes['done'],
                        'is_billable' => false,
                        'track_time' => true,
                        'currency' => 'CZK',
                        'quantity' => null,
                        'hourly_rate_override' => 1700,
                        'fixed_price' => null,
                        'completed_at' => $day->setTime(8, 45),
                        'due_date' => $day,
                        'meta' => [
                            'seeded' => 'dashboard_statistics',
                            'channel' => 'internal',
                        ],
                    ])
                    ->create()
                    ->timeEntries()
                    ->create([
                        'owner_id' => $owner->id,
                        'started_at' => $day->setTime(8, 0),
                        'ended_at' => $day->setTime(8, 45),
                        'minutes' => 45,
                        'meta' => ['seeded' => 'dashboard_statistics'],
                    ]);
            }
        }

        collect([
            ['title' => '[Seed Stats] Waiting for customer assets', 'dueDaysAgo' => 3, 'status' => $taskStatusCodes['planned'], 'project' => $projects['fixed_eur']],
            ['title' => '[Seed Stats] Deploy API gateway', 'dueDaysAgo' => 6, 'status' => $taskStatusCodes['in_progress'], 'project' => $projects['api_usd']],
            ['title' => '[Seed Stats] DNS migration', 'dueDaysAgo' => 12, 'status' => $taskStatusCodes['planned'], 'project' => $projects['retainer_cz']],
        ])->each(function (array $item) use ($owner): void {
            Task::factory()
                ->for($item['project'])
                ->state([
                    'owner_id' => $owner->id,
                    'title' => $item['title'],
                    'billing_model' => TaskBillingModel::FixedPrice,
                    'status' => $item['status'],
                    'is_billable' => true,
                    'track_time' => false,
                    'currency' => $item['project']->currency,
                    'quantity' => 1,
                    'hourly_rate_override' => null,
                    'fixed_price' => 1200,
                    'due_date' => CarbonImmutable::today()->subDays($item['dueDaysAgo']),
                    'completed_at' => null,
                    'meta' => [
                        'seeded' => 'dashboard_statistics',
                    ],
                ])
                ->create();
        });
    }

    /**
     * @param  Collection<int, LeadSource>  $leadSources
     * @param  array<string, Customer>  $customers
     */
    private function seedLeads(User $owner, Collection $leadSources, array $customers): void
    {
        Lead::query()
            ->withTrashed()
            ->where('owner_id', $owner->id)
            ->where('email', 'like', 'seed.lead.%@crmseed.test')
            ->forceDelete();

        $definitions = [
            [LeadStatus::New, null, 'CZK'],
            [LeadStatus::Discovery, null, 'CZK'],
            [LeadStatus::Qualified, null, 'EUR'],
            [LeadStatus::Proposal, null, 'USD'],
            [LeadStatus::Won, $customers['cz']->id, 'CZK'],
            [LeadStatus::Won, $customers['eur']->id, 'EUR'],
            [LeadStatus::Lost, null, 'USD'],
            [LeadStatus::Archived, null, 'CZK'],
        ];

        foreach ($definitions as $index => [$status, $customerId, $currency]) {
            Lead::query()->create([
                'owner_id' => $owner->id,
                'lead_source_id' => $leadSources->isNotEmpty() ? $leadSources->random()->id : null,
                'customer_id' => $customerId,
                'full_name' => 'Seed Lead '.($index + 1),
                'company_name' => 'Seeded Prospect '.($index + 1),
                'email' => sprintf('seed.lead.%02d@crmseed.test', $index + 1),
                'phone' => '+420 777 '.str_pad((string) (200 + $index), 3, '0', STR_PAD_LEFT),
                'website' => 'https://lead-'.($index + 1).'.example',
                'status' => $status,
                'priority' => $index < 4 ? 4 : 2,
                'currency' => $currency,
                'estimated_value' => 4000 + ($index * 2750),
                'expected_close_date' => CarbonImmutable::today()->addDays(14 + ($index * 5)),
                'contacted_at' => CarbonImmutable::today()->subDays(20 - min($index, 18)),
                'last_activity_at' => CarbonImmutable::today()->subDays($index),
                'summary' => 'Seeded lead for dashboard statistics testing.',
                'meta' => [
                    'seeded' => 'dashboard_statistics',
                ],
            ]);
        }
    }

    /**
     * @return array{planned: string, in_progress: string, blocked_or_open: string, completed_or_done: string}
     */
    private function resolveProjectStatusCodes(): array
    {
        return [
            'planned' => ProjectStatus::Planned->value,
            'in_progress' => ProjectStatus::InProgress->value,
            'blocked_or_open' => ProjectStatus::Blocked->value,
            'completed_or_done' => ProjectStatus::Completed->value,
        ];
    }

    /**
     * @return array{planned: string, in_progress: string, done: string}
     */
    private function resolveTaskStatusCodes(): array
    {
        return [
            'planned' => TaskStatus::Planned->value,
            'in_progress' => TaskStatus::InProgress->value,
            'done' => TaskStatus::Done->value,
        ];
    }

    private function workedMinutesForDate(CarbonImmutable $date): int
    {
        if ($date->isWeekend()) {
            return $date->isSaturday() ? 60 : 0;
        }

        return match ($date->dayOfWeekIso) {
            1 => 420,
            2 => 360,
            3 => 390,
            4 => 450,
            5 => 300,
            default => 0,
        };
    }
}
