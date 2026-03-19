<?php

use App\Filament\Resources\RecurringServiceTypes\RecurringServiceTypeResource;
use App\Models\RecurringServiceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;

uses(RefreshDatabase::class);

it('scopes recurring service type resource query to the authenticated owner', function (): void {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();

    $ownerType = RecurringServiceType::factory()->create([
        'owner_id' => $owner->id,
        'slug' => 'my-custom-service',
    ]);

    RecurringServiceType::factory()->create([
        'owner_id' => $otherOwner->id,
        'slug' => 'my-custom-service',
    ]);

    Auth::login($owner);

    $visibleTypeIds = RecurringServiceTypeResource::getEloquentQuery()
        ->pluck('id')
        ->all();

    expect($visibleTypeIds)->toBe([$ownerType->id]);
});

it('allows the same recurring service type slug for different owners', function (): void {
    $firstOwner = User::factory()->create();
    $secondOwner = User::factory()->create();

    RecurringServiceType::factory()->create([
        'owner_id' => $firstOwner->id,
        'name' => 'Maintenance Plan',
        'slug' => 'maintenance-plan',
    ]);

    RecurringServiceType::factory()->create([
        'owner_id' => $secondOwner->id,
        'name' => 'Maintenance Plan',
        'slug' => 'maintenance-plan',
    ]);

    expect(
        RecurringServiceType::query()
            ->where('slug', 'maintenance-plan')
            ->count()
    )->toBe(2);
});
