<?php

namespace Database\Factories;

use App\Models\BillingReport;
use App\Models\BillingReportLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillingReportLine>
 */
class BillingReportLineFactory extends Factory
{
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 0.5, 40);
        $unitPrice = fake()->randomFloat(2, 50, 200);

        return [
            'billing_report_id' => BillingReport::factory(),
            'task_id' => null,
            'description' => fake()->sentence(4),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => round($quantity * $unitPrice, 2),
            'sort_order' => 0,
        ];
    }
}
