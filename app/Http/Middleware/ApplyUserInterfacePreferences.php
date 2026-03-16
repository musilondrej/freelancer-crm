<?php

namespace App\Http\Middleware;

use App\Models\UserSetting;
use Closure;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

class ApplyUserInterfacePreferences
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->user()?->getAuthIdentifier();

        $ui = UserSetting::uiForUser(
            is_numeric($userId) ? (int) $userId : null,
        );

        $locale = $ui['locale'];

        App::setLocale($locale);
        Date::setLocale($locale);
        date_default_timezone_set($ui['timezone']);
        FilamentTimezone::set($ui['timezone']);

        return $next($request);
    }
}
