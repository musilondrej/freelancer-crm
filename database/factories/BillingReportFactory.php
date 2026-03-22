<?php

namespace Database\Factories;

use App\Enums\BillingReportStatus;
use App\Enums\Currency;
use App\Models\BillingReport;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingReport>
 */
class BillingReportFactory extends Factory
{
    public function definition(): array
    {
        $owner = User::factory();
        $year = fake()->year();

        return [
            'owner_id' => $owner,
            'customer_id' => Customer::factory(),
            'title' => fake()->company().' – '.fake()->monthName().' '.$year,
            'reference' => null,
            'currency' => fake()->randomElement([Currency::EUR, Currency::CZK, Currency::USD])->value,
            'status' => BillingReportStatus::Draft,
            'notes' => fake()->optional(0.3)->sentence(),
            'finalized_at' => null,
        ];
    }

    public function finalized(): static
    {
        return $this->state(fn (): array => [
            'status' => BillingReportStatus::Finalized,
            'reference' => 'FAK-'.fake()->year().'-'.fake()->numberBetween(1, 999),
            'finalized_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => BillingReportStatus::Draft,
            'finalized_at' => null,
        ]);
    }
}
