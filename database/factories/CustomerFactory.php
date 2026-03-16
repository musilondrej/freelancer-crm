<?php

namespace Database\Factories;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $registrationNumber = fake()->optional(0.6)->randomElement([
            fake()->numerify('########'),
            fake()->bothify('HRB######'),
            fake()->bothify('US-??-######'),
            fake()->bothify('ABN###########'),
        ]);
        $vatId = fake()->boolean(70)
            ? fake()->unique()->randomElement([
                fake()->bothify('CZ########'),
                fake()->bothify('DE#########'),
                fake()->bothify('GB#########'),
                fake()->bothify('AU###########'),
                fake()->bothify('US#########'),
            ])
            : null;
        $email = fake()->boolean(80)
            ? fake()->unique()->companyEmail()
            : null;

        return [
            'owner_id' => User::factory(),
            'name' => fake()->company(),
            'legal_name' => fake()->optional(0.7)->company(),
            'registration_number' => $registrationNumber,
            'vat_id' => $vatId,
            'email' => $email,
            'phone' => fake()->optional(0.8)->phoneNumber(),
            'website' => fake()->optional(0.7)->url(),
            'timezone' => fake()->randomElement(['Europe/Prague', 'Europe/Berlin', 'America/New_York']),
            'billing_currency' => fake()->randomElement(['CZK', 'EUR', 'USD']),
            'hourly_rate' => fake()->optional(0.7)->randomFloat(2, 600, 2500),
            'status' => fake()->randomElement(CustomerStatus::cases()),
            'source' => fake()->optional(0.7)->randomElement(['referral', 'linkedin', 'web', 'repeat-client']),
            'last_contacted_at' => fake()->optional(0.7)->dateTimeBetween('-6 months', 'now'),
            'next_follow_up_at' => fake()->optional(0.6)->dateTimeBetween('now', '+2 months'),
            'internal_summary' => fake()->optional(0.6)->sentence(),
            'meta' => [
                'industry' => fake()->randomElement(['SaaS', 'E-commerce', 'Healthcare', 'Fintech']),
                'country' => fake()->countryCode(),
            ],
        ];
    }

    public function lead(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CustomerStatus::Lead,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CustomerStatus::Active,
        ]);
    }
}
