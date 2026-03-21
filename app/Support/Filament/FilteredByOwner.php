<?php

namespace App\Support\Filament;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Builder;

final class FilteredByOwner
{
    public static function ownerId(): ?int
    {
        return Filament::auth()->id();
    }

    /**
     * Returns a closure suitable for `modifyQueryUsing:` on relationship selects.
     */
    public static function closure(): Closure
    {
        $ownerId = self::ownerId();

        return fn (Builder $query): Builder => $ownerId !== null
            ? $query->where('owner_id', $ownerId)
            : $query;
    }

    /**
     * Applies owner_id scoping directly to an existing query builder chain.
     */
    public static function applyTo(Builder $query): Builder
    {
        $ownerId = self::ownerId();

        return $ownerId !== null
            ? $query->where('owner_id', $ownerId)
            : $query;
    }
}
