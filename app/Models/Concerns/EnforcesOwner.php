<?php

namespace App\Models\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait EnforcesOwner
{
    public static function bootEnforcesOwner(): void
    {
        static::addGlobalScope('owner', function (Builder $query): void {
            $ownerId = Auth::id();

            if ($ownerId === null) {
                return;
            }

            $query->where($query->qualifyColumn('owner_id'), $ownerId);
        });

        static::creating(function (Model $model): void {
            $ownerId = Auth::id();

            if ($ownerId === null) {
                return;
            }

            $model->setAttribute('owner_id', $ownerId);
        });

        static::updating(function (Model $model): void {
            $ownerId = Auth::id();

            if ($ownerId === null) {
                return;
            }

            throw_if((int) $model->getOriginal('owner_id') !== $ownerId, AuthorizationException::class, 'You are not allowed to modify records owned by a different user.');

            $model->setAttribute('owner_id', $ownerId);
        });
    }
}
