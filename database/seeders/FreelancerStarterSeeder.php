<?php

namespace Database\Seeders;

use App\Enums\CustomerStatus;
use App\Enums\LeadPipelineStage;
use App\Enums\LeadStatus;
use App\Enums\ProjectActivityType;
use App\Enums\ProjectPipelineStage;
use App\Enums\ProjectPricingModel;
use App\Enums\RecurringServiceBillingModel;
use App\Enums\RecurringServiceCadenceUnit;
use App\Enums\RecurringServiceStatus;
use App\Models\Activity;
use App\Models\ClientContact;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\Note;
use App\Models\Project;
use App\Models\ProjectActivityStatusOption;
use App\Models\ProjectStatusOption;
use App\Models\RecurringService;
use App\Models\RecurringServiceType;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserSetting;
use App\Models\Worklog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FreelancerStarterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $owner = User::query()->firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => 'password',
                'email_verified_at' => now(),
                'default_currency' => 'CZK',
                'default_hourly_rate' => 1450,
            ],
        );

        UserSetting::ensureForUser($owner->id);
        ProjectStatusOption::ensureDefaultsForOwner($owner->id);
        ProjectActivityStatusOption::ensureDefaultsForOwner($owner->id);

        $leadSources = $this->seedLeadSources($owner);
        $customers = $this->seedCustomers($owner);
        $contacts = $this->seedContacts($owner, $customers);
        $projects = $this->seedProjects($owner, $customers, $contacts);
        $activities = $this->seedActivities($owner, $projects);

        $this->seedProjectActivities($owner, $projects, $activities);
        $this->seedLeads($owner, $customers, $leadSources);
        $this->seedRecurringServices($owner, $customers, $projects);
        $this->seedTagsAndNotes($owner, $customers, $projects);
    }

    /**
     * @return array<string, LeadSource>
     */
    private function seedLeadSources(User $owner): array
    {
        $definitions = [
            ['name' => 'Referral', 'slug' => 'referral', 'sort_order' => 10],
            ['name' => 'LinkedIn', 'slug' => 'linkedin', 'sort_order' => 20],
            ['name' => 'Website', 'slug' => 'website', 'sort_order' => 30],
            ['name' => 'Existing Client', 'slug' => 'existing-client', 'sort_order' => 40],
        ];

        $sources = [];

        foreach ($definitions as $definition) {
            $sources[$definition['slug']] = LeadSource::query()->updateOrCreate(
                [
                    'owner_id' => $owner->id,
                    'slug' => $definition['slug'],
                ],
                [
                    'name' => $definition['name'],
                    'sort_order' => $definition['sort_order'],
                    'is_active' => true,
                ],
            );
        }

        return $sources;
    }

    /**
     * @return array<string, Customer>
     */
    private function seedCustomers(User $owner): array
    {
        $definitions = [
            'novak-eshop' => [
                'name' => 'Novak eShop s.r.o.',
                'legal_name' => 'Novak eShop s.r.o.',
                'registration_number' => '07651234',
                'vat_id' => 'CZ07651234',
                'email' => 'invoice@novakeshop.test',
                'phone' => '+420 777 100 200',
                'website' => 'https://novakeshop.example',
                'timezone' => 'Europe/Prague',
                'billing_currency' => 'CZK',
                'hourly_rate' => 1450,
                'status' => CustomerStatus::Active,
                'source' => 'referral',
                'internal_summary' => 'Long-term maintenance client.',
            ],
            'alpen-digital' => [
                'name' => 'Alpen Digital GmbH',
                'legal_name' => 'Alpen Digital GmbH',
                'registration_number' => 'HRB998877',
                'vat_id' => 'DE998877665',
                'email' => 'finance@alpen-digital.test',
                'phone' => '+49 151 1010 2020',
                'website' => 'https://alpen-digital.example',
                'timezone' => 'Europe/Berlin',
                'billing_currency' => 'EUR',
                'hourly_rate' => 95,
                'status' => CustomerStatus::Active,
                'source' => 'linkedin',
                'internal_summary' => 'Design and frontend delivery project.',
            ],
        ];

        $customers = [];

        foreach ($definitions as $key => $definition) {
            $customers[$key] = Customer::query()->updateOrCreate(
                [
                    'owner_id' => $owner->id,
                    'vat_id' => $definition['vat_id'],
                ],
                [
                    ...$definition,
                    'last_contacted_at' => now()->subDays(3),
                    'next_follow_up_at' => now()->addDays(7),
                ],
            );
        }

        return $customers;
    }

    /**
     * @param  array<string, Customer>  $customers
     * @return array<string, ClientContact>
     */
    private function seedContacts(User $owner, array $customers): array
    {
        $definitions = [
            'novak-eshop' => [
                'full_name' => 'Jana Novakova',
                'job_title' => 'Operations Manager',
                'email' => 'jana.novakova@novakeshop.test',
                'phone' => '+420 777 555 100',
            ],
            'alpen-digital' => [
                'full_name' => 'Lukas Meier',
                'job_title' => 'Product Manager',
                'email' => 'lukas.meier@alpen-digital.test',
                'phone' => '+49 171 555 4040',
            ],
        ];

        $contacts = [];

        foreach ($definitions as $customerKey => $definition) {
            $customer = $customers[$customerKey];

            $contacts[$customerKey] = ClientContact::query()->updateOrCreate(
                [
                    'owner_id' => $owner->id,
                    'client_id' => $customer->id,
                    'email' => $definition['email'],
                ],
                [
                    ...$definition,
                    'is_primary' => true,
                    'is_billing_contact' => true,
                    'last_contacted_at' => now()->subDays(2),
                ],
            );
        }

        return $contacts;
    }

    /**
     * @param  array<string, Customer>  $customers
     * @param  array<string, ClientContact>  $contacts
     * @return array<string, Project>
     */
    private function seedProjects(User $owner, array $customers, array $contacts): array
    {
        $projectStatusCodes = $this->resolveProjectStatusCodes($owner->id);

        $definitions = [
            'eshop-maintenance' => [
                'client_id' => $customers['novak-eshop']->id,
                'primary_contact_id' => $contacts['novak-eshop']->id,
                'name' => 'eShop Maintenance',
                'status' => $projectStatusCodes['in_progress'],
                'pipeline_stage' => ProjectPipelineStage::Won,
                'pricing_model' => ProjectPricingModel::Hourly,
                'priority' => 4,
                'start_date' => now()->subDays(45)->startOfDay(),
                'target_end_date' => null,
                'closed_date' => null,
                'currency' => 'CZK',
                'hourly_rate' => 1500,
                'fixed_price' => null,
                'estimated_hours' => 70,
                'estimated_value' => 105000,
                'actual_value' => null,
                'description' => 'Continuous maintenance and feature work.',
                'last_activity_at' => now()->subHours(6),
            ],
            'landing-redesign' => [
                'client_id' => $customers['alpen-digital']->id,
                'primary_contact_id' => $contacts['alpen-digital']->id,
                'name' => 'Landing Page Redesign',
                'status' => $projectStatusCodes['planned'],
                'pipeline_stage' => ProjectPipelineStage::Proposal,
                'pricing_model' => ProjectPricingModel::Fixed,
                'priority' => 3,
                'start_date' => now()->subDays(7)->startOfDay(),
                'target_end_date' => now()->addDays(40)->startOfDay(),
                'closed_date' => null,
                'currency' => 'EUR',
                'hourly_rate' => null,
                'fixed_price' => 3800,
                'estimated_hours' => 42,
                'estimated_value' => 3800,
                'actual_value' => null,
                'description' => 'Conversion-focused redesign and implementation.',
                'last_activity_at' => now()->subDay(),
            ],
        ];

        $projects = [];

        foreach ($definitions as $key => $definition) {
            $projects[$key] = Project::query()->updateOrCreate(
                [
                    'owner_id' => $owner->id,
                    'name' => $definition['name'],
                ],
                $definition,
            );
        }

        return $projects;
    }

    /**
     * @param  array<string, Project>  $projects
     * @return array<string, Activity>
     */
    private function seedActivities(User $owner, array $projects): array
    {
        $definitions = [
            'global-development' => [
                'project_id' => null,
                'name' => 'Development',
                'description' => 'Default development activity.',
                'default_hourly_rate' => $owner->default_hourly_rate,
                'is_billable' => true,
                'sort_order' => 10,
            ],
            'global-meeting' => [
                'project_id' => null,
                'name' => 'Meeting',
                'description' => 'Client meetings and calls.',
                'default_hourly_rate' => $owner->default_hourly_rate,
                'is_billable' => true,
                'sort_order' => 20,
            ],
            'global-admin' => [
                'project_id' => null,
                'name' => 'Internal Admin',
                'description' => 'Non-billable operations.',
                'default_hourly_rate' => null,
                'is_billable' => false,
                'sort_order' => 30,
            ],
            'eshop-bugfix' => [
                'project_id' => $projects['eshop-maintenance']->id,
                'name' => 'Bugfix & Support',
                'description' => 'Bugfixing and support tasks.',
                'default_hourly_rate' => 1500,
                'is_billable' => true,
                'sort_order' => 10,
            ],
            'landing-design' => [
                'project_id' => $projects['landing-redesign']->id,
                'name' => 'Design & Implementation',
                'description' => 'Design handoff and frontend implementation.',
                'default_hourly_rate' => 95,
                'is_billable' => true,
                'sort_order' => 10,
            ],
        ];

        $activities = [];

        foreach ($definitions as $key => $definition) {
            $activities[$key] = Activity::query()->updateOrCreate(
                [
                    'owner_id' => $owner->id,
                    'project_id' => $definition['project_id'],
                    'name' => $definition['name'],
                ],
                [
                    'description' => $definition['description'],
                    'default_hourly_rate' => $definition['default_hourly_rate'],
                    'is_billable' => $definition['is_billable'],
                    'is_active' => true,
                    'sort_order' => $definition['sort_order'],
                ],
            );
        }

        return $activities;
    }

    /**
     * @param  array<string, Project>  $projects
     * @param  array<string, Activity>  $activities
     */
    private function seedProjectActivities(User $owner, array $projects, array $activities): void
    {
        $projectActivityStatusCodes = $this->resolveProjectActivityStatusCodes($owner->id);

        $entries = [
            [
                'project' => $projects['eshop-maintenance'],
                'activity' => $activities['eshop-bugfix'],
                'title' => 'Weekly maintenance batch',
                'type' => ProjectActivityType::Hourly,
                'status' => $projectActivityStatusCodes['done'],
                'tracked_minutes' => 180,
                'unit_rate' => 1500,
                'flat_amount' => null,
                'is_billable' => true,
                'is_invoiced' => false,
                'started_at' => now()->subDays(2)->setTime(9, 0),
                'finished_at' => now()->subDays(2)->setTime(12, 0),
                'currency' => 'CZK',
            ],
            [
                'project' => $projects['eshop-maintenance'],
                'activity' => $activities['global-meeting'],
                'title' => 'Sprint planning call',
                'type' => ProjectActivityType::Hourly,
                'status' => $projectActivityStatusCodes['done'],
                'tracked_minutes' => 60,
                'unit_rate' => 1500,
                'flat_amount' => null,
                'is_billable' => true,
                'is_invoiced' => false,
                'started_at' => now()->subDay()->setTime(10, 0),
                'finished_at' => now()->subDay()->setTime(11, 0),
                'currency' => 'CZK',
            ],
            [
                'project' => $projects['eshop-maintenance'],
                'activity' => $activities['global-admin'],
                'title' => 'Internal reporting',
                'type' => ProjectActivityType::Hourly,
                'status' => $projectActivityStatusCodes['done'],
                'tracked_minutes' => 45,
                'unit_rate' => null,
                'flat_amount' => null,
                'is_billable' => false,
                'is_invoiced' => false,
                'started_at' => now()->subDay()->setTime(8, 0),
                'finished_at' => now()->subDay()->setTime(8, 45),
                'currency' => 'CZK',
            ],
            [
                'project' => $projects['eshop-maintenance'],
                'activity' => $activities['eshop-bugfix'],
                'title' => 'Production hotfix',
                'type' => ProjectActivityType::OneTime,
                'status' => $projectActivityStatusCodes['done'],
                'tracked_minutes' => null,
                'unit_rate' => null,
                'flat_amount' => 2400,
                'is_billable' => true,
                'is_invoiced' => false,
                'started_at' => now()->subDays(4)->setTime(14, 0),
                'finished_at' => now()->subDays(4)->setTime(15, 0),
                'currency' => 'CZK',
            ],
            [
                'project' => $projects['eshop-maintenance'],
                'activity' => $activities['eshop-bugfix'],
                'title' => 'API endpoint hardening',
                'type' => ProjectActivityType::Hourly,
                'status' => $projectActivityStatusCodes['done'],
                'tracked_minutes' => 150,
                'unit_rate' => 1500,
                'flat_amount' => null,
                'is_billable' => true,
                'is_invoiced' => true,
                'invoice_reference' => 'INV-2026-001',
                'invoiced_at' => now()->subDays(15)->setTime(9, 0),
                'started_at' => now()->subDays(16)->setTime(9, 30),
                'finished_at' => now()->subDays(16)->setTime(12, 0),
                'currency' => 'CZK',
            ],
            [
                'project' => $projects['landing-redesign'],
                'activity' => $activities['landing-design'],
                'title' => 'Wireframes and UX direction',
                'type' => ProjectActivityType::OneTime,
                'status' => $projectActivityStatusCodes['planned'],
                'tracked_minutes' => null,
                'unit_rate' => null,
                'flat_amount' => 800,
                'is_billable' => true,
                'is_invoiced' => false,
                'started_at' => null,
                'finished_at' => null,
                'currency' => 'EUR',
            ],
            [
                'project' => $projects['landing-redesign'],
                'activity' => $activities['landing-design'],
                'title' => 'UI implementation - section A',
                'type' => ProjectActivityType::Hourly,
                'status' => $projectActivityStatusCodes['in_progress'],
                'tracked_minutes' => null,
                'unit_rate' => 95,
                'flat_amount' => null,
                'is_billable' => true,
                'is_invoiced' => false,
                'started_at' => now()->subHours(3),
                'finished_at' => null,
                'currency' => 'EUR',
            ],
        ];

        foreach ($entries as $entry) {
            $project = $entry['project'];
            $activity = $entry['activity'];
            $startedAt = $entry['started_at'];
            $finishedAt = $entry['finished_at'];

            Worklog::query()->updateOrCreate(
                [
                    'owner_id' => $owner->id,
                    'project_id' => $project->id,
                    'title' => $entry['title'],
                ],
                [
                    'activity_id' => $activity?->id,
                    'description' => null,
                    'type' => $entry['type'],
                    'status' => $entry['status'],
                    'is_running' => false,
                    'is_billable' => $entry['is_billable'],
                    'is_invoiced' => $entry['is_invoiced'],
                    'invoice_reference' => $entry['invoice_reference'] ?? null,
                    'invoiced_at' => $entry['invoiced_at'] ?? null,
                    'currency' => $entry['currency'],
                    'quantity' => $entry['type'] === ProjectActivityType::OneTime ? 1 : null,
                    'unit_rate' => $entry['unit_rate'],
                    'flat_amount' => $entry['flat_amount'],
                    'tracked_minutes' => $entry['tracked_minutes'],
                    'due_date' => $startedAt?->copy()->startOfDay(),
                    'started_at' => $startedAt,
                    'finished_at' => $finishedAt,
                    'meta' => ['seed' => 'starter'],
                ],
            );
        }
    }

    /**
     * @param  array<string, Customer>  $customers
     * @param  array<string, LeadSource>  $leadSources
     */
    private function seedLeads(User $owner, array $customers, array $leadSources): void
    {
        $definitions = [
            [
                'email' => 'lead.automation@starter.test',
                'full_name' => 'Marek Dvorak',
                'company_name' => 'Automation Works',
                'status' => LeadStatus::New,
                'pipeline_stage' => LeadPipelineStage::Inbox,
                'lead_source_id' => $leadSources['website']->id,
                'currency' => 'CZK',
                'estimated_value' => 60000,
                'customer_id' => null,
            ],
            [
                'email' => 'lead.mobile@starter.test',
                'full_name' => 'Eva Kraus',
                'company_name' => 'Alpen Digital GmbH',
                'status' => LeadStatus::Proposal,
                'pipeline_stage' => LeadPipelineStage::Proposal,
                'lead_source_id' => $leadSources['linkedin']->id,
                'currency' => 'EUR',
                'estimated_value' => 5200,
                'customer_id' => null,
            ],
            [
                'email' => 'lead.retainer@starter.test',
                'full_name' => 'Jana Novakova',
                'company_name' => 'Novak eShop s.r.o.',
                'status' => LeadStatus::Won,
                'pipeline_stage' => LeadPipelineStage::Closed,
                'lead_source_id' => $leadSources['existing-client']->id,
                'currency' => 'CZK',
                'estimated_value' => 90000,
                'customer_id' => $customers['novak-eshop']->id,
            ],
        ];

        foreach ($definitions as $definition) {
            Lead::query()->updateOrCreate(
                [
                    'owner_id' => $owner->id,
                    'email' => $definition['email'],
                ],
                [
                    'lead_source_id' => $definition['lead_source_id'],
                    'customer_id' => $definition['customer_id'],
                    'full_name' => $definition['full_name'],
                    'company_name' => $definition['company_name'],
                    'phone' => null,
                    'website' => null,
                    'status' => $definition['status'],
                    'pipeline_stage' => $definition['pipeline_stage'],
                    'priority' => 3,
                    'currency' => $definition['currency'],
                    'estimated_value' => $definition['estimated_value'],
                    'expected_close_date' => now()->addDays(21),
                    'contacted_at' => now()->subDays(1),
                    'last_activity_at' => now()->subDays(1),
                    'summary' => 'Starter lead seed record.',
                ],
            );
        }
    }

    /**
     * @param  array<string, Customer>  $customers
     * @param  array<string, Project>  $projects
     */
    private function seedRecurringServices(User $owner, array $customers, array $projects): void
    {
        $typeDefinitions = [
            'hosting' => ['name' => 'Hosting', 'sort_order' => 10],
            'domain' => ['name' => 'Domain', 'sort_order' => 20],
            'maintenance' => ['name' => 'Maintenance', 'sort_order' => 30],
            'support' => ['name' => 'Support', 'sort_order' => 40],
        ];

        $types = [];

        foreach ($typeDefinitions as $slug => $definition) {
            $types[$slug] = RecurringServiceType::query()->updateOrCreate(
                [
                    'owner_id' => $owner->id,
                    'slug' => $slug,
                ],
                [
                    'name' => $definition['name'],
                    'is_active' => true,
                    'sort_order' => $definition['sort_order'],
                ],
            );
        }

        RecurringService::query()->updateOrCreate(
            [
                'owner_id' => $owner->id,
                'customer_id' => $customers['novak-eshop']->id,
                'name' => 'Managed Hosting',
            ],
            [
                'project_id' => $projects['eshop-maintenance']->id,
                'service_type_id' => $types['hosting']->id,
                'billing_model' => RecurringServiceBillingModel::Fixed,
                'currency' => 'CZK',
                'fixed_amount' => 1900,
                'hourly_rate' => null,
                'included_quantity' => null,
                'cadence_unit' => RecurringServiceCadenceUnit::Month,
                'cadence_interval' => 1,
                'starts_on' => now()->subMonths(2)->startOfDay(),
                'next_due_on' => now()->addDays(12)->startOfDay(),
                'last_invoiced_on' => now()->subDays(18)->startOfDay(),
                'auto_renew' => true,
                'status' => RecurringServiceStatus::Active,
                'remind_days_before' => [7, 1],
                'notes' => 'Monthly hosting fee.',
            ],
        );

        RecurringService::query()->updateOrCreate(
            [
                'owner_id' => $owner->id,
                'customer_id' => $customers['alpen-digital']->id,
                'name' => 'Domain Renewal',
            ],
            [
                'project_id' => $projects['landing-redesign']->id,
                'service_type_id' => $types['domain']->id,
                'billing_model' => RecurringServiceBillingModel::Fixed,
                'currency' => 'EUR',
                'fixed_amount' => 120,
                'hourly_rate' => null,
                'included_quantity' => null,
                'cadence_unit' => RecurringServiceCadenceUnit::Year,
                'cadence_interval' => 1,
                'starts_on' => now()->subMonths(9)->startOfDay(),
                'next_due_on' => now()->addMonths(3)->startOfDay(),
                'last_invoiced_on' => now()->subMonths(9)->startOfDay(),
                'auto_renew' => true,
                'status' => RecurringServiceStatus::Active,
                'remind_days_before' => [30, 7, 1],
                'notes' => 'Annual domain renewal.',
            ],
        );
    }

    /**
     * @param  array<string, Customer>  $customers
     * @param  array<string, Project>  $projects
     */
    private function seedTagsAndNotes(User $owner, array $customers, array $projects): void
    {
        $tags = collect([
            ['name' => 'Retainer', 'color' => '#2563EB', 'sort_order' => 10],
            ['name' => 'Urgent', 'color' => '#DC2626', 'sort_order' => 20],
            ['name' => 'Waiting Client', 'color' => '#D97706', 'sort_order' => 30],
        ])->mapWithKeys(function (array $definition) use ($owner): array {
            $slug = Str::slug($definition['name']);

            $tag = Tag::query()->updateOrCreate(
                [
                    'owner_id' => $owner->id,
                    'slug' => $slug,
                ],
                [
                    'name' => $definition['name'],
                    'color' => $definition['color'],
                    'sort_order' => $definition['sort_order'],
                ],
            );

            return [$slug => $tag];
        });

        $customers['novak-eshop']->tags()->syncWithoutDetaching([
            $tags['retainer']->id,
        ]);

        $projects['eshop-maintenance']->tags()->syncWithoutDetaching([
            $tags['retainer']->id,
            $tags['urgent']->id,
        ]);

        $projects['landing-redesign']->tags()->syncWithoutDetaching([
            $tags['waiting-client']->id,
        ]);

        Note::query()->updateOrCreate(
            [
                'owner_id' => $owner->id,
                'noteable_type' => Customer::class,
                'noteable_id' => $customers['novak-eshop']->id,
                'body' => 'Client prefers weekly summary on Fridays.',
            ],
            [
                'is_pinned' => true,
                'noted_at' => now()->subDays(2),
                'meta' => ['seed' => 'starter'],
            ],
        );

        Note::query()->updateOrCreate(
            [
                'owner_id' => $owner->id,
                'noteable_type' => Project::class,
                'noteable_id' => $projects['landing-redesign']->id,
                'body' => 'Waiting for final copy and imagery from client.',
            ],
            [
                'is_pinned' => false,
                'noted_at' => now()->subDay(),
                'meta' => ['seed' => 'starter'],
            ],
        );
    }

    /**
     * @return array{planned: string, in_progress: string}
     */
    private function resolveProjectStatusCodes(int $ownerId): array
    {
        $defaultCode = ProjectStatusOption::defaultCodeForOwner($ownerId);
        $openCodes = collect(ProjectStatusOption::definitionsForOwner($ownerId))
            ->filter(static fn (array $definition): bool => $definition['is_open'])
            ->pluck('code')
            ->values();

        $plannedCode = (string) ($openCodes->get(0) ?? $defaultCode);
        $inProgressCode = (string) ($openCodes->get(1) ?? $plannedCode);

        return [
            'planned' => $plannedCode,
            'in_progress' => $inProgressCode,
        ];
    }

    /**
     * @return array{planned: string, in_progress: string, done: string}
     */
    private function resolveProjectActivityStatusCodes(int $ownerId): array
    {
        $definitions = collect(ProjectActivityStatusOption::definitionsForOwner($ownerId));
        $defaultCode = ProjectActivityStatusOption::defaultCodeForOwner($ownerId);

        $openCodes = $definitions
            ->filter(static fn (array $definition): bool => $definition['is_open'])
            ->pluck('code')
            ->values();

        $runningDefinition = $definitions
            ->first(static fn (array $definition): bool => $definition['is_running']);

        $doneDefinition = $definitions
            ->first(static fn (array $definition): bool => $definition['is_done']);

        $plannedCode = (string) ($openCodes->get(0) ?? $defaultCode);
        $inProgressCode = is_array($runningDefinition)
            ? (string) $runningDefinition['code']
            : (string) ($openCodes->get(1) ?? $plannedCode);
        $doneCode = is_array($doneDefinition)
            ? (string) $doneDefinition['code']
            : $plannedCode;

        return [
            'planned' => $plannedCode,
            'in_progress' => $inProgressCode,
            'done' => $doneCode,
        ];
    }
}
