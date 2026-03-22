<?php

namespace App\Filament\Resources\BillingReports\Pages;

use App\Enums\TaskBillingModel;
use App\Filament\Resources\BillingReports\BillingReportResource;
use App\Filament\Resources\BillingReports\Schemas\CreateBillingReportForm;
use App\Models\BillingReport;
use App\Models\Task;
use App\Models\TimeEntry;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class CreateBillingReport extends CreateRecord
{
    protected static string $resource = BillingReportResource::class;

    /** @var list<int> */
    protected array $pendingTaskIds = [];

    /** @var list<int> */
    protected array $pendingTimeEntryIds = [];

    public function form(Schema $schema): Schema
    {
        return CreateBillingReportForm::configure($schema);
    }

    /**
     * Strip virtual selection fields before persisting the model.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingTaskIds = array_map(intval(...), $data['selected_task_ids'] ?? []);
        $this->pendingTimeEntryIds = array_map(intval(...), $data['selected_time_entry_ids'] ?? []);

        unset($data['selected_task_ids'], $data['selected_time_entry_ids']);

        return $data;
    }

    /**
     * After the BillingReport record is saved, attach the selected items as lines.
     */
    protected function afterCreate(): void
    {
        /** @var BillingReport $report */
        $report = $this->record;

        foreach ($this->pendingTaskIds as $taskId) {
            $task = Task::query()->find($taskId);

            if (! $task instanceof Task) {
                continue;
            }

            if ($task->billing_model === TaskBillingModel::FixedPrice) {
                $report->addFixedPriceTask($task);
            } else {
                $report->addHourlyTask($task);
            }
        }

        if ($this->pendingTimeEntryIds !== []) {
            /** @var EloquentCollection<int, TimeEntry> $entries */
            $entries = TimeEntry::query()->whereIn('id', $this->pendingTimeEntryIds)->get();
            $report->addSpecificEntries($entries);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
