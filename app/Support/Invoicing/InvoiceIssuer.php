<?php

namespace App\Support\Invoicing;

use App\Enums\TaskBillingModel;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InvoiceIssuer
{
    /**
     * @param  iterable<Model>  $invoiceables
     * @return EloquentCollection<int, Invoice>
     */
    public function issue(iterable $invoiceables, ?string $reference = null, CarbonInterface|string|null $issuedAt = null): EloquentCollection
    {
        /** @var Collection<int, Task|TimeEntry> $records */
        $records = collect($invoiceables)
            ->filter(fn (mixed $record): bool => $record instanceof Task || $record instanceof TimeEntry)
            ->values();

        throw_if($records->isEmpty(), InvalidArgumentException::class, 'At least one invoiceable record is required.');

        $normalizedReference = is_string($reference) && trim($reference) !== ''
            ? trim($reference)
            : null;

        throw_if($records->contains(fn (Task|TimeEntry $record): bool => $record instanceof TimeEntry) && $normalizedReference === null, InvalidArgumentException::class, 'Invoice reference is required for time entry invoicing.');

        $normalizedIssuedAt = $this->normalizeIssuedAt($issuedAt);

        /** @var EloquentCollection<int, Invoice> $invoices */
        $invoices = DB::transaction(function () use ($records, $normalizedReference, $normalizedIssuedAt): EloquentCollection {
            $createdInvoices = new EloquentCollection;

            $records
                ->groupBy(fn (Task|TimeEntry $record): string => $this->invoiceGroupKey($record))
                ->each(function (Collection $group) use ($createdInvoices, $normalizedReference, $normalizedIssuedAt): void {
                    /** @var Collection<int, Task|TimeEntry> $invoiceables */
                    $invoiceables = $group->values();

                    $createdInvoices->push($this->createInvoiceForGroup(
                        $invoiceables,
                        $normalizedReference,
                        $normalizedIssuedAt,
                    ));
                });

            return $createdInvoices;
        });

        return $invoices;
    }

    /**
     * @param  Collection<int, Task|TimeEntry>  $invoiceables
     */
    private function createInvoiceForGroup(Collection $invoiceables, ?string $reference, CarbonInterface $issuedAt): Invoice
    {
        $first = $invoiceables->first();

        throw_if(! $first instanceof Task && ! $first instanceof TimeEntry, InvalidArgumentException::class, 'Invoice group must contain invoiceable records.');

        $ownerId = $this->ownerId($first);
        $customer = $this->customer($first);
        $project = $this->sharedProject($invoiceables);

        $invoice = Invoice::query()->create([
            'owner_id' => $ownerId,
            'customer_id' => $customer?->id,
            'project_id' => $project?->id,
            'reference' => $reference,
            'issued_at' => $issuedAt,
            'currency' => $this->resolvedCurrency($invoiceables, $customer, $project),
        ]);

        $invoiceables->values()->each(function (Task|TimeEntry $invoiceable, int $index) use ($invoice): void {
            $this->createInvoiceItem($invoice, $invoiceable, $index + 1);
        });

        return $invoice->load('items');
    }

    private function createInvoiceItem(Invoice $invoice, Task|TimeEntry $invoiceable, int $lineOrder): InvoiceItem
    {
        $invoiceItem = new InvoiceItem([
            'owner_id' => $invoice->owner_id,
            'description' => $this->description($invoiceable),
            'quantity' => $this->quantity($invoiceable),
            'unit_rate' => $this->unitRate($invoiceable),
            'amount' => $this->amount($invoiceable),
            'currency' => $this->currency($invoiceable),
            'line_order' => $lineOrder,
        ]);

        $invoiceItem->invoice()->associate($invoice);

        $invoiceable->invoiceItems()->save($invoiceItem);

        return $invoiceItem;
    }

    private function normalizeIssuedAt(CarbonInterface|string|null $issuedAt): CarbonInterface
    {
        return match (true) {
            $issuedAt instanceof CarbonInterface => $issuedAt,
            is_string($issuedAt) && trim($issuedAt) !== '' => CarbonImmutable::parse($issuedAt),
            default => now(),
        };
    }

    private function invoiceGroupKey(Task|TimeEntry $record): string
    {
        $ownerId = $this->ownerId($record);
        $customerId = $this->customer($record)?->id;

        return sprintf('%d:%s', $ownerId, $customerId ?? 'none');
    }

    private function ownerId(Task|TimeEntry $record): int
    {
        return (int) $record->owner_id;
    }

    private function customer(Task|TimeEntry $record): ?Customer
    {
        if ($record instanceof TimeEntry) {
            return $record->project->customer ?? $record->task?->project?->customer;
        }

        return $record->project?->customer;
    }

    /**
     * @param  Collection<int, Task|TimeEntry>  $invoiceables
     */
    private function sharedProject(Collection $invoiceables): ?Project
    {
        $projects = $invoiceables
            ->map(fn (Task|TimeEntry $record): ?Project => $record instanceof TimeEntry
                ? ($record->project ?? $record->task?->project)
                : $record->project)
            ->filter(fn (?Project $project): bool => $project instanceof Project)
            ->unique(fn (Project $project): int => $project->id)
            ->values();

        return $projects->count() === 1 ? $projects->first() : null;
    }

    /**
     * @param  Collection<int, Task|TimeEntry>  $invoiceables
     */
    private function resolvedCurrency(Collection $invoiceables, ?Customer $customer, ?Project $project): ?string
    {
        $currencies = $invoiceables
            ->map(fn (Task|TimeEntry $record): ?string => $this->currency($record))
            ->filter(fn (?string $currency): bool => $currency !== null)
            ->unique()
            ->values();

        if ($currencies->count() === 1) {
            return $currencies->first();
        }

        return $project?->effectiveCurrency() ?? $customer?->effectiveCurrency();
    }

    private function description(Task|TimeEntry $invoiceable): string
    {
        if ($invoiceable instanceof TimeEntry) {
            $description = trim((string) ($invoiceable->description ?? ''));

            if ($description !== '') {
                return $description;
            }

            return $invoiceable->task instanceof Task
                ? $invoiceable->task->title
                : ($invoiceable->project->name ?? __('Time entry'));
        }

        return $invoiceable->title;
    }

    private function quantity(Task|TimeEntry $invoiceable): float
    {
        if ($invoiceable instanceof TimeEntry) {
            return round($invoiceable->resolvedMinutes() / 60, 2);
        }

        if ($invoiceable->billing_model === TaskBillingModel::Hourly) {
            $trackedHours = round($invoiceable->billableTrackedMinutes() / 60, 2);

            if ($trackedHours > 0) {
                return $trackedHours;
            }

            return 0.0;
        }

        return 1.0;
    }

    private function unitRate(Task|TimeEntry $invoiceable): ?float
    {
        if ($invoiceable instanceof TimeEntry) {
            return $invoiceable->effectiveHourlyRate();
        }

        if ($invoiceable->billing_model === TaskBillingModel::Hourly) {
            return $invoiceable->effectiveHourlyRate();
        }

        if ($invoiceable->fixed_price !== null) {
            return (float) $invoiceable->fixed_price;
        }

        return $invoiceable->calculatedAmount();
    }

    private function amount(Task|TimeEntry $invoiceable): ?float
    {
        return $invoiceable->calculatedAmount();
    }

    private function currency(Task|TimeEntry $invoiceable): ?string
    {
        if ($invoiceable instanceof TimeEntry) {
            return $invoiceable->task?->effectiveCurrency() ?? $invoiceable->project?->effectiveCurrency();
        }

        return $invoiceable->effectiveCurrency();
    }
}
