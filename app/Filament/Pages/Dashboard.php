<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardMetricsBoard;
use App\Filament\Widgets\OverdueActivitiesTable;
use App\Filament\Widgets\RevenueTrendChart;
use App\Filament\Widgets\UnbilledDoneWorkTable;
use App\Filament\Widgets\UpcomingRevenueTable;
use App\Filament\Widgets\WorkHoursTimelineChart;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $title = 'Dashboard';

    protected ?Alignment $headerActionsAlignment = Alignment::End;

    /**
     * @var list<string>
     */
    public array $activeMetricKeys = [];

    public function mount(): void
    {
        $this->activeMetricKeys = $this->resolvedDashboardWidgets();
    }

    public function getColumns(): int|array
    {
        return [
            'md' => 2,
            'xl' => 2,
        ];
    }

    public function getWidgets(): array
    {
        return [
            DashboardMetricsBoard::make([
                'metricKeys' => $this->activeMetricKeys !== []
                    ? $this->activeMetricKeys
                    : $this->resolvedDashboardWidgets(),
            ]),
            RevenueTrendChart::class,
            WorkHoursTimelineChart::class,
            UnbilledDoneWorkTable::class,
            UpcomingRevenueTable::class,
            OverdueActivitiesTable::class,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->schema([
                        DatePicker::make('startDate')
                            ->label('Start date')
                            ->default(now()->startOfMonth())
                            ->maxDate(fn (Get $get): mixed => $get('endDate') ?: now())
                            ->native(false),
                        DatePicker::make('endDate')
                            ->label('End date')
                            ->default(now())
                            ->minDate(fn (Get $get): mixed => $get('startDate') ?: now()->startOfMonth())
                            ->maxDate(now())
                            ->native(false),
                        Select::make('currency')
                            ->label('Display currency')
                            ->options([
                                'CZK' => 'CZK (Kč)',
                                'EUR' => 'EUR (€)',
                                'USD' => 'USD ($)',
                            ])
                            ->default(fn (): string => strtoupper((string) (Filament::auth()->user()->default_currency ?? 'CZK'))),
                    ])
                    ->columns([
                        'md' => 3,
                        'xl' => 3,
                    ])
                    ->columnSpanFull(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('customizeDashboard')
                ->label('Customize')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->slideOver()
                ->stickyModalHeader()
                ->modalWidth('4xl')
                ->fillForm(fn (): array => [
                    'dashboard_widgets' => $this->resolvedDashboardWidgets(),
                ])
                ->schema([
                    CheckboxList::make('dashboard_widgets')
                        ->label('Visible metrics')
                        ->options(DashboardMetricsBoard::metricOptions())
                        ->columns(2)
                        ->bulkToggleable()
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var User|null $user */
                    $user = Filament::auth()->user();

                    if ($user === null) {
                        return;
                    }

                    $user->forceFill([
                        'dashboard_widgets' => array_values($data['dashboard_widgets'] ?? DashboardMetricsBoard::defaultMetricKeys()),
                    ])->save();

                    $this->activeMetricKeys = array_values($data['dashboard_widgets'] ?? DashboardMetricsBoard::defaultMetricKeys());
                    $this->redirect(self::getUrl(), navigate: false);
                })
                ->successNotificationTitle('Dashboard updated'),
        ];
    }

    /**
     * @return list<string>
     */
    private function resolvedDashboardWidgets(): array
    {
        /** @var User|null $user */
        $user = Filament::auth()->user();
        $dashboardWidgets = $user?->getAttribute('dashboard_widgets');

        if (! is_array($dashboardWidgets) || $dashboardWidgets === []) {
            return DashboardMetricsBoard::defaultMetricKeys();
        }

        $resolvedWidgetKeys = array_values(array_filter(
            $dashboardWidgets,
            fn (mixed $metricKey): bool => is_string($metricKey)
                && array_key_exists($metricKey, DashboardMetricsBoard::metricOptions()),
        ));

        if ($resolvedWidgetKeys === []) {
            return DashboardMetricsBoard::defaultMetricKeys();
        }

        return $resolvedWidgetKeys;
    }
}
