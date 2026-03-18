<?php

namespace Database\Factories;

use App\Models\ClientContact;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClientContact>
 */
class ClientContactFactory extends Factory
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
            'customer_id' => Customer::factory(),
            'owner_id' => fn (array $attributes): ?int => Customer::query()->find($attributes['customer_id'])?->owner_id,
            'full_name' => fake()->name(),
            'job_title' => fake()->optional(0.8)->jobTitle(),
            'email' => $email,
            'phone' => fake()->optional(0.85)->phoneNumber(),
            'is_primary' => false,
            'is_billing_contact' => false,
            'last_contacted_at' => fake()->optional(0.7)->dateTimeBetween('-6 months', 'now'),
            'meta' => [
                'preferred_channel' => fake()->randomElement(['email', 'phone', 'slack']),
            ],
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_primary' => true,
        ]);
    }

    public function billing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_billing_contact' => true,
        ]);
    }
}
