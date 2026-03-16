<?php

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates baseline settings for a newly created user', function (): void {
    $user = User::factory()->create();

    expect(UserSetting::query()->where('user_id', $user->id)->exists())->toBeTrue();
});
