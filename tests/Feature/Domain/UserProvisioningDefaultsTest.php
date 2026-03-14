<?php

use App\Models\ProjectActivityStatusOption;
use App\Models\ProjectStatusOption;
use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates baseline settings and status options for a newly created user', function (): void {
    $user = User::factory()->create();

    $projectActivityStatusCodes = ProjectActivityStatusOption::query()
        ->where('owner_id', $user->id)
        ->orderBy('sort_order')
        ->pluck('code')
        ->all();

    expect(ProjectStatusOption::query()->where('owner_id', $user->id)->exists())->toBeTrue()
        ->and(ProjectActivityStatusOption::query()->where('owner_id', $user->id)->exists())->toBeTrue()
        ->and(UserSetting::query()->where('user_id', $user->id)->exists())->toBeTrue()
        ->and($projectActivityStatusCodes)->toEqual([
            'in_progress',
            'done',
            'cancelled',
        ]);
});
