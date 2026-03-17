<?php

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Leads\LeadResource;
use App\Filament\Resources\Projects\ProjectResource;
use App\Filament\Resources\RecurringServices\RecurringServiceResource;
use App\Filament\Resources\Worklogs\WorklogResource;
use App\Providers\Filament\AdminPanelProvider;
use Filament\Panel;

it('orders the admin navigation groups by workflow', function (): void {
    $panel = (new AdminPanelProvider(app()))->panel(Panel::make());

    $labels = array_map(
        static fn (string|object $group): string => is_string($group) ? $group : (string) $group->getLabel(),
        $panel->getNavigationGroups(),
    );

    expect($labels)->toBe([
        'Sales',
        'CRM',
        'Delivery',
        'Time & Money',
        'Setup',
    ]);
});

it('assigns primary resources to workflow-oriented navigation groups', function (string $resourceClass, string $group): void {
    $navigationGroup = (new ReflectionClass($resourceClass))->getDefaultProperties()['navigationGroup'] ?? null;

    expect($navigationGroup)->toBe($group);
})->with([
    LeadResource::class => [LeadResource::class, 'Sales'],
    CustomerResource::class => [CustomerResource::class, 'CRM'],
    ProjectResource::class => [ProjectResource::class, 'Delivery'],
    WorklogResource::class => [WorklogResource::class, 'Time & Money'],
    RecurringServiceResource::class => [RecurringServiceResource::class, 'Time & Money'],
]);
