<?php

namespace App\Filament\Resources\TimeEntries\Pages;

use App\Filament\Resources\TimeEntries\TimeEntryResource;
use App\Models\TimeEntry;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTimeEntry extends EditRecord
{
    protected static string $resource = TimeEntryResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->record instanceof TimeEntry && $this->record->isLocked()) {
            Notification::make()
                ->warning()
                ->title(__('Locked'))
                ->body(__('This time entry is locked and cannot be edited.'))
                ->send();

            $this->redirect(TimeEntryResource::getUrl('index'));
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
