<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $project = Project::factory();

        return [
            'project_id' => $project,
            'owner_id' => fn (array $attributes): ?int => Project::query()->find($attributes['project_id'])?->owner_id,
            'customer_id' => fn (array $attributes): ?int => Project::query()->find($attributes['project_id'])?->customer_id,
            'reference' => fake()->optional(0.8)->bothify('INV-####-###'),
            'issued_at' => fake()->dateTimeBetween('-3 months', 'now'),
            'currency' => fake()->randomElement(['CZK', 'EUR', 'USD']),
            'notes' => fake()->optional(0.3)->sentence(),
            'meta' => [
                'source' => 'factory',
            ],
        ];
    }

    public function withoutProject(): static
    {
        return $this->state(function (): array {
            $customer = Customer::factory();

            return [
                'project_id' => null,
                'customer_id' => $customer,
                'owner_id' => fn (array $attributes): ?int => Customer::query()->find($attributes['customer_id'])?->owner_id,
            ];
        });
    }
}
