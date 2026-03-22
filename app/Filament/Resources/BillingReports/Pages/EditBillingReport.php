<?php

namespace App\Filament\Resources\BillingReports\Pages;

use App\Filament\Resources\BillingReports\BillingReportResource;
use App\Models\BillingReport;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditBillingReport extends EditRecord
{
    protected static string $resource = BillingReportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('finalize')
                ->label(__('Finalize report'))
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('Finalize billing report'))
                ->modalDescription(__('This locks all included time entries and finalizes the report. This action cannot be undone.'))
                ->visible(fn (): bool => $this->getRecord() instanceof BillingReport
                    && $this->getRecord()->isDraft())
                ->schema(fn (): array => [
                    TextInput::make('reference')
                        ->label(__('Invoice reference'))
                        ->placeholder(__('FAK-2026-001'))
                        ->default(function (): ?string {
                            /** @var BillingReport $record */
                            $record = $this->getRecord();

                            return $record->reference;
                        })
                        ->maxLength(255)
                        ->helperText(__('Optional: set or confirm the external invoice number before finalizing')),
                ])
                ->action(function (array $data): void {
                    /** @var BillingReport $report */
                    $report = $this->getRecord();

                    if ($report->lines()->doesntExist()) {
                        Notification::make()
                            ->warning()
                            ->title(__('Cannot finalize an empty report'))
                            ->send();

                        return;
                    }

                    $report->finalize($data['reference'] ?: null);

                    Notification::make()
                        ->success()
                        ->title(__('Report finalized'))
                        ->body(__('Report finalized and all entries locked.'))
                        ->send();

                    $this->refreshFormData(['status', 'finalized_at', 'reference']);
                }),

            DeleteAction::make()
                ->visible(fn (): bool => $this->getRecord() instanceof BillingReport
                    && $this->getRecord()->isDraft()),
        ];
    }
}
