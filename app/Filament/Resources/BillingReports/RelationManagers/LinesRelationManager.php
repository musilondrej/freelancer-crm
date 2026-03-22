<?php

namespace App\Filament\Resources\BillingReports\RelationManagers;

use App\Enums\TaskBillingModel;
use App\Models\BillingReport;
use App\Models\BillingReportLine;
use App\Models\Task;
use App\Support\CurrencyConverter;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LinesRelationManager extends RelationManager
{
    protected static string $relationship = 'lines';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columns(12)->schema([
                    Textarea::make('description')
                        ->label(__('Description'))
                        ->required()
                        ->rows(2)
                        ->columnSpan(12),

                    TextInput::make('quantity')
                        ->label(__('Quantity'))
                        ->numeric()
                        ->minValue(0.01)
                        ->step(0.01)
                        ->required()
                        ->columnSpan(4),

                    TextInput::make('unit_price')
                        ->label(__('Unit price'))
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->required()
                        ->columnSpan(4),

                    TextInput::make('sort_order')
                        ->label(__('Order'))
                        ->numeric()
                        ->default(0)
                        ->columnSpan(4),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        /** @var BillingReport $report */
        $report = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('description')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('description')
                    ->label(__('Description'))
                    ->limit(60)
                    ->tooltip(fn (BillingReportLine $record): ?string => strlen($record->description) > 60
                        ? $record->description
                        : null),

                TextColumn::make('task.billing_model')
                    ->label(__('Type'))
                    ->badge()
                    ->placeholder(__('Custom')),

                TextColumn::make('quantity')
                    ->label(__('Qty / Hours'))
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd(),

                TextColumn::make('unit_price')
                    ->label(__('Unit price'))
                    ->state(fn (BillingReportLine $line): string => CurrencyConverter::format(
                        (float) $line->unit_price,
                        $report->currency,
                        2,
                    ))
                    ->alignEnd(),

                TextColumn::make('total_amount')
                    ->label(__('Total'))
                    ->state(fn (BillingReportLine $line): string => CurrencyConverter::format(
                        (float) $line->total_amount,
                        $report->currency,
                        2,
                    ))
                    ->weight('semibold')
                    ->alignEnd()
                    ->summarize(
                        Sum::make()
                            ->formatStateUsing(fn (mixed $state): string => CurrencyConverter::format(
                                (float) $state,
                                $report->currency,
                                2,
                            ))
                    ),
            ])
            ->headerActions([
                Action::make('add_tasks')
                    ->label(__('Add tasks'))
                    ->icon(Heroicon::OutlinedPlusCircle)
                    ->color('primary')
                    ->visible(fn (): bool => $report->isDraft())
                    ->schema([
                        CheckboxList::make('task_ids')
                            ->label(__('Select tasks to include'))
                            ->options(fn (): array => Task::query()
                                ->whereHas('project', fn (Builder $q): Builder => $q->where(
                                    'customer_id',
                                    $report->customer_id
                                ))
                                ->where('is_billable', true)
                                ->whereDoesntHave('billingReportLine')
                                ->with('project')
                                ->orderBy('title')
                                ->get()
                                ->mapWithKeys(fn (Task $task): array => [
                                    $task->id => sprintf(
                                        '%s (%s — %s)',
                                        $task->title,
                                        $task->project->name ?? '?',
                                        $task->billing_model->getLabel(),
                                    ),
                                ])
                                ->all())
                            ->columns(1)
                            ->required(),
                    ])
                    ->action(function (array $data) use ($report): void {
                        $added = 0;

                        foreach ($data['task_ids'] as $taskId) {
                            $task = Task::with('project')->find($taskId);

                            if (! $task instanceof Task) {
                                continue;
                            }

                            match ($task->billing_model) {
                                TaskBillingModel::Hourly => $report->addHourlyTask($task),
                                TaskBillingModel::FixedPrice => $report->addFixedPriceTask($task),
                            };

                            $added++;
                        }

                        Notification::make()
                            ->success()
                            ->title(__(':count task(s) added to report', ['count' => $added]))
                            ->send();
                    }),

                Action::make('add_custom_line')
                    ->label(__('Add custom line'))
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('gray')
                    ->visible(fn (): bool => $report->isDraft())
                    ->schema([
                        Textarea::make('description')
                            ->label(__('Description'))
                            ->required()
                            ->rows(2),

                        TextInput::make('quantity')
                            ->label(__('Quantity'))
                            ->numeric()
                            ->default(1)
                            ->minValue(0.01)
                            ->step(0.01)
                            ->required(),

                        TextInput::make('unit_price')
                            ->label(__('Unit price'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->step(0.01)
                            ->required(),
                    ])
                    ->action(function (array $data) use ($report): void {
                        $report->addCustomLine(
                            $data['description'],
                            (float) $data['quantity'],
                            (float) $data['unit_price'],
                        );

                        Notification::make()
                            ->success()
                            ->title(__('Custom line added'))
                            ->send();
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => $report->isDraft()),
                DeleteAction::make()
                    ->visible(fn (): bool => $report->isDraft()),
            ])
            ->paginated(false);
    }
}
