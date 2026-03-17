<?php

use App\Enums\UserSettingLocale;
use App\Filament\Pages\Auth\Schemas\EditProfileForm;

test('normalizes enum state values for snapshot fields', function (): void {
    $form = new class extends EditProfileForm
    {
        public static function exposeStringState(mixed $value, string $fallback): string
        {
            return self::stringState($value, $fallback);
        }
    };

    expect($form::exposeStringState(UserSettingLocale::Czech, 'fallback'))->toBe('cs')
        ->and($form::exposeStringState('en', 'fallback'))->toBe('en')
        ->and($form::exposeStringState(null, 'fallback'))->toBe('fallback');
});
