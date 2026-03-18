<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $invoice = Invoice::factory();

        return [
            'invoice_id' => $invoice,
            'owner_id' => fn (array $attributes): ?int => Invoice::query()->find($attributes['invoice_id'])?->owner_id,
            'invoiceable_type' => Task::class,
            'invoiceable_id' => Task::factory(),
            'description' => fake()->sentence(),
            'quantity' => fake()->randomFloat(2, 1, 10),
            'unit_rate' => fake()->randomFloat(2, 500, 3500),
            'amount' => fake()->randomFloat(2, 500, 20000),
            'currency' => fake()->randomElement(['CZK', 'EUR', 'USD']),
            'line_order' => 1,
            'meta' => [
                'source' => 'factory',
            ],
        ];
    }
}
