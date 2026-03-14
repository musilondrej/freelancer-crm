<?php

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('returns configured defaults when user id is null', function (): void {
    config()->set('crm.time_tracking.rounding.enabled', true);
    config()->set('crm.time_tracking.rounding.mode', 'nearest');
    config()->set('crm.time_tracking.rounding.interval_minutes', 10);
    config()->set('crm.time_tracking.rounding.minimum_minutes', 2);

    $rounding = UserSetting::roundingForUser(null);

    expect($rounding)->toMatchArray([
        'enabled' => true,
        'mode' => 'nearest',
        'interval_minutes' => 10,
        'minimum_minutes' => 2,
    ]);
});

it('keeps only one user settings record when ensuring defaults repeatedly', function (): void {
    $user = User::factory()->create();
    $initialCount = UserSetting::query()->where('user_id', $user->id)->count();

    $settings = UserSetting::ensureForUser($user->id);
    $afterEnsureCount = UserSetting::query()->where('user_id', $user->id)->count();

    expect($settings->user_id)->toBe($user->id)
        ->and($settings->preferences)->toBeArray()
        ->and($initialCount)->toBe(1)
        ->and($afterEnsureCount)->toBe(1);
});

it('merges custom rounding preferences with defaults', function (): void {
    config()->set('crm.time_tracking.rounding.enabled', true);
    config()->set('crm.time_tracking.rounding.mode', 'ceil');
    config()->set('crm.time_tracking.rounding.interval_minutes', 15);
    config()->set('crm.time_tracking.rounding.minimum_minutes', 1);

    $user = User::factory()->create();

    UserSetting::query()
        ->where('user_id', $user->id)
        ->update([
            'preferences' => [
                'time_tracking' => [
                    'rounding' => [
                        'enabled' => false,
                        'interval_minutes' => 30,
                    ],
                ],
            ],
        ]);

    $rounding = UserSetting::roundingForUser($user->id);

    expect($rounding)->toMatchArray([
        'enabled' => false,
        'mode' => 'ceil',
        'interval_minutes' => 30,
        'minimum_minutes' => 1,
    ]);
});

it('returns configured ui defaults when user id is null', function (): void {
    config()->set('app.locale', 'cs');
    config()->set('app.timezone', 'Europe/Prague');

    $ui = UserSetting::uiForUser(null);

    expect($ui)->toMatchArray([
        'locale' => 'cs',
        'timezone' => 'Europe/Prague',
        'week_starts_on' => 'monday',
        'date_format' => 'd.m.Y',
        'time_format' => 'H:i',
    ]);
});

it('merges ui preferences and falls back for invalid values', function (): void {
    config()->set('app.locale', 'en');
    config()->set('app.timezone', 'UTC');

    $user = User::factory()->create();

    UserSetting::query()
        ->where('user_id', $user->id)
        ->update([
            'preferences' => [
                'ui' => [
                    'locale' => 'cs',
                    'timezone' => 'Europe/Prague',
                    'week_starts_on' => 'sunday',
                    'date_format' => 'Y-m-d',
                    'time_format' => 'h:i A',
                ],
            ],
        ]);

    expect(UserSetting::uiForUser($user->id))->toMatchArray([
        'locale' => 'cs',
        'timezone' => 'Europe/Prague',
        'week_starts_on' => 'sunday',
        'date_format' => 'Y-m-d',
        'time_format' => 'h:i A',
    ]);

    UserSetting::query()
        ->where('user_id', $user->id)
        ->update([
            'preferences' => [
                'ui' => [
                    'locale' => 'de',
                    'timezone' => 'Invalid/Timezone',
                    'week_starts_on' => 'friday',
                    'date_format' => 'j.n.Y',
                    'time_format' => 'g:i a',
                ],
            ],
        ]);

    expect(UserSetting::uiForUser($user->id))->toMatchArray([
        'locale' => 'en',
        'timezone' => 'UTC',
        'week_starts_on' => 'monday',
        'date_format' => 'd.m.Y',
        'time_format' => 'H:i',
    ]);
});
