<?php

use App\Enums\RecurringServiceStatus;
use App\Filament\Resources\RecurringServices\RecurringServiceResource;
use App\Models\RecurringService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

it('shows a danger navigation badge only for recurring services nearing expiration', function (): void {
    $owner = User::factory()->create();
    Auth::login($owner);

    RecurringService::factory()->create([
        'owner_id' => $owner->id,
        'status' => RecurringServiceStatus::Active,
        'ends_on' => today()->addDays(7),
    ]);

    RecurringService::factory()->create([
        'owner_id' => $owner->id,
        'status' => RecurringServiceStatus::Active,
        'ends_on' => today()->addDays(14),
    ]);

    RecurringService::factory()->create([
        'owner_id' => $owner->id,
        'status' => RecurringServiceStatus::Active,
        'ends_on' => today()->addDays(15),
    ]);

    RecurringService::factory()->create([
        'owner_id' => $owner->id,
        'status' => RecurringServiceStatus::Paused,
        'ends_on' => today()->addDays(3),
    ]);

    RecurringService::factory()->create([
        'owner_id' => $owner->id,
        'status' => RecurringServiceStatus::Active,
        'ends_on' => null,
    ]);

    expect(RecurringServiceResource::getNavigationBadge())->toBe('2')
        ->and(RecurringServiceResource::getNavigationBadgeColor())->toBe('danger')
        ->and(RecurringServiceResource::getNavigationBadgeTooltip())->toContain('14');
});

it('does not show a recurring service navigation badge when nothing is nearing expiration', function (): void {
    $owner = User::factory()->create();
    Auth::login($owner);

    RecurringService::factory()->create([
        'owner_id' => $owner->id,
        'status' => RecurringServiceStatus::Active,
        'ends_on' => today()->addDays(30),
    ]);

    expect(RecurringServiceResource::getNavigationBadge())->toBeNull()
        ->and(RecurringServiceResource::getNavigationBadgeColor())->toBeNull()
        ->and(RecurringServiceResource::getNavigationBadgeTooltip())->toBeNull();
});
