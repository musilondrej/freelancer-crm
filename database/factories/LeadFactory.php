<?php

namespace Database\Factories;

use App\Enums\LeadPipelineStage;
use App\Enums\LeadStatus;
use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $email = fake()->boolean(85)
            ? fake()->unique()->safeEmail()
            : null;

        return [
            'owner_id' => User::factory(),
            'lead_source_id' => LeadSource::factory()->state(fn (array $attributes): array => [
                'owner_id' => $attributes['owner_id'],
            ]),
            'customer_id' => null,
            'full_name' => fake()->name(),
            'company_name' => fake()->optional(0.7)->company(),
            'email' => $email,
            'phone' => fake()->optional(0.8)->phoneNumber(),
            'website' => fake()->optional(0.6)->url(),
            'status' => fake()->randomElement([
                LeadStatus::New,
                LeadStatus::Contacted,
                LeadStatus::Qualified,
                LeadStatus::Proposal,
                LeadStatus::Won,
                LeadStatus::Lost,
            ]),
            'pipeline_stage' => fake()->randomElement(LeadPipelineStage::cases()),
            'priority' => fake()->numberBetween(1, 5),
            'currency' => fake()->optional(0.8)->randomElement(['CZK', 'EUR', 'USD']),
            'estimated_value' => fake()->optional(0.7)->randomFloat(2, 5000, 250000),
            'expected_close_date' => fake()->optional(0.6)->dateTimeBetween('now', '+6 months'),
            'contacted_at' => fake()->optional(0.6)->dateTimeBetween('-2 months', 'now'),
            'last_activity_at' => fake()->optional(0.7)->dateTimeBetween('-1 month', 'now'),
            'summary' => fake()->optional(0.7)->sentence(),
            'meta' => [
                'channel' => fake()->randomElement(['email', 'phone', 'linkedin', 'referral']),
            ],
        ];
    }

    public function won(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LeadStatus::Won,
            'pipeline_stage' => LeadPipelineStage::Closed,
        ]);
    }

    public function lost(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LeadStatus::Lost,
            'pipeline_stage' => LeadPipelineStage::Closed,
        ]);
    }
}
