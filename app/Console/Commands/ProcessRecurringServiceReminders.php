<?php

namespace App\Console\Commands;

use App\Enums\RecurringServiceStatus;
use App\Models\RecurringService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

class ProcessRecurringServiceReminders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recurring-services:process-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create due recurring service reminder notes based on service cadence settings.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $today = today();

        $processed = 0;
        $skipped = 0;

        RecurringService::query()
            ->where('status', RecurringServiceStatus::Active->value)
            ->whereNotNull('next_due_on')
            ->whereNotNull('remind_days_before')
            ->where(function ($query) use ($today): void {
                $query->whereNull('ends_on')
                    ->orWhereDate('ends_on', '>=', $today);
            })
            ->orderBy('id')
            ->chunkById(200, function ($services) use ($today, &$processed, &$skipped): void {
                foreach ($services as $service) {
                    $nextDueOn = $this->toCarbonDate($service->next_due_on);

                    if (! $nextDueOn instanceof Carbon) {
                        $skipped++;

                        continue;
                    }

                    $daysBefore = $this->normalizeReminderDays($service->remind_days_before);

                    if ($daysBefore === []) {
                        $skipped++;

                        continue;
                    }

                    $daysUntilDue = $today->diffInDays($nextDueOn, false);

                    if (! in_array($daysUntilDue, $daysBefore, true)) {
                        $skipped++;

                        continue;
                    }

                    DB::transaction(function () use ($service, &$processed, &$skipped): void {
                        /** @var RecurringService|null $lockedService */
                        $lockedService = RecurringService::query()
                            ->whereKey($service->id)
                            ->lockForUpdate()
                            ->first();

                        if (! $lockedService instanceof RecurringService) {
                            $skipped++;

                            return;
                        }

                        $lockedNextDueOn = $this->toCarbonDate($lockedService->next_due_on);
                        $lastRemindedForDueOn = $this->toCarbonDate($lockedService->last_reminded_for_due_on);

                        if (! $lockedNextDueOn instanceof Carbon) {
                            $skipped++;

                            return;
                        }

                        $alreadyReminded = $lastRemindedForDueOn instanceof Carbon
                            && $lastRemindedForDueOn->isSameDay($lockedNextDueOn);

                        if ($alreadyReminded) {
                            $skipped++;

                            return;
                        }

                        $lockedService->notes()->create([
                            'owner_id' => $lockedService->owner_id,
                            'body' => sprintf(
                                'Reminder: recurring service "%s" is due on %s.',
                                $lockedService->name,
                                $lockedNextDueOn->format('d.m.Y'),
                            ),
                            'noted_at' => now(),
                            'meta' => [
                                'source' => 'recurring_service_scheduler',
                                'next_due_on' => $lockedNextDueOn->toDateString(),
                            ],
                        ]);

                        $lockedService->forceFill([
                            'last_reminded_for_due_on' => $lockedNextDueOn->toDateString(),
                            'last_reminded_at' => now(),
                        ])->save();

                        $processed++;
                    });
                }
            });

        $this->info(sprintf('Recurring service reminders processed. Created: %d, skipped: %d', $processed, $skipped));

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function normalizeReminderDays(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->map(fn (mixed $day): int => (int) $day)
            ->filter(fn (int $day): bool => $day >= 0)
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function toCarbonDate(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return Date::parse($value)->startOfDay();
    }
}
