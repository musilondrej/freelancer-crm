<?php

namespace App\Actions;

use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Support\FinancialDefaults;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resolves inherited financial defaults (currency + hourly rate) up the ownership chain.
 *
 * Inheritance chain: User defaults → Customer → Project → Task
 *
 * Each resolution method performs a single DB query and returns a typed FinancialDefaults
 * value object, falling back to the authenticated user's defaults when no parent is found.
 *
 * Usage in Filament form schemas:
 *   $financials = new ResolveInheritedFinancials($ownerId);
 *   $defaults = $financials->fromCustomer($get('customer_id'));
 *   $set('currency', $defaults->currency);
 *   $set('hourly_rate', $defaults->hourlyRate);
 */
final readonly class ResolveInheritedFinancials
{
    public function __construct(private ?int $ownerId) {}

    /**
     * Resolve from a Customer's effective currency and rate.
     * Falls back to the authenticated user's defaults when the customer is not found.
     */
    public function fromCustomer(mixed $customerId): FinancialDefaults
    {
        if (is_numeric($customerId)) {
            $customer = Customer::query()
                ->when($this->ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $this->ownerId))
                ->with('owner')
                ->find((int) $customerId);

            if ($customer instanceof Customer) {
                $currency = $customer->effectiveCurrency();

                return new FinancialDefaults(
                    currency: $currency,
                    hourlyRate: $customer->effectiveHourlyRate($currency),
                );
            }
        }

        return $this->fromAuthUser();
    }

    /**
     * Resolve from a Project's effective currency and rate (which itself may inherit from its Customer).
     * Falls back to the authenticated user's defaults when the project is not found.
     */
    public function fromProject(mixed $projectId): FinancialDefaults
    {
        if (is_numeric($projectId)) {
            $project = Project::query()
                ->when($this->ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $this->ownerId))
                ->with(['customer.owner'])
                ->find((int) $projectId);

            if ($project instanceof Project) {
                $currency = $project->effectiveCurrency();

                return new FinancialDefaults(
                    currency: $currency,
                    hourlyRate: $project->effectiveHourlyRate($currency),
                );
            }
        }

        return $this->fromAuthUser();
    }

    private function fromAuthUser(): FinancialDefaults
    {
        $user = $this->ownerId !== null ? User::query()->find($this->ownerId) : null;
        $currency = $user?->default_currency;

        return new FinancialDefaults(
            currency: $currency,
            hourlyRate: $user?->defaultHourlyRateForCurrency($currency),
        );
    }
}
