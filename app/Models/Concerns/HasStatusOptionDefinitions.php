<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

trait HasStatusOptionDefinitions
{
    /**
     * @return list<array<string, mixed>>
     */
    abstract public static function defaultDefinitions(): array;

    /**
     * @return list<array<string, mixed>>
     */
    abstract public static function definitionsForOwner(?int $ownerId): array;

    /**
     * @return array<string, string>
     */
    public static function optionsForOwner(?int $ownerId): array
    {
        return collect(static::definitionsForOwner($ownerId))
            ->pluck('label', 'code')
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public static function colorsForOwner(?int $ownerId): array
    {
        return collect(static::definitionsForOwner($ownerId))
            ->pluck('color', 'code')
            ->all();
    }

    public static function defaultCodeForOwner(?int $ownerId): string
    {
        $definitions = static::definitionsForOwner($ownerId);
        $defaultDefinition = collect($definitions)->firstWhere('is_default', true);

        if (is_array($defaultDefinition)) {
            return (string) $defaultDefinition['code'];
        }

        $firstDefinition = $definitions[0] ?? null;

        if (is_array($firstDefinition)) {
            return (string) $firstDefinition['code'];
        }

        $fallbackDefault = collect(static::defaultDefinitions())->firstWhere('is_default', true);

        if (is_array($fallbackDefault)) {
            return (string) $fallbackDefault['code'];
        }

        $fallbackFirst = static::defaultDefinitions()[0] ?? null;

        return is_array($fallbackFirst) ? (string) $fallbackFirst['code'] : '';
    }

    /**
     * @return list<string>
     */
    public static function openCodesForOwner(?int $ownerId): array
    {
        return static::codesForBooleanFlag($ownerId, 'is_open');
    }

    public static function labelFor(?int $ownerId, ?string $code): string
    {
        if ($code === null || $code === '') {
            return '-';
        }

        $label = static::optionsForOwner($ownerId)[$code] ?? null;

        if ($label !== null) {
            return $label;
        }

        return Str::headline(str_replace('_', ' ', $code));
    }

    public static function colorFor(?int $ownerId, ?string $code): string
    {
        if ($code === null || $code === '') {
            return 'gray';
        }

        return static::colorsForOwner($ownerId)[$code] ?? 'gray';
    }

    /**
     * @return list<string>
     */
    protected static function codesForBooleanFlag(?int $ownerId, string $flag): array
    {
        $codes = collect(static::definitionsForOwner($ownerId))
            ->filter(static fn (array $definition): bool => (bool) ($definition[$flag] ?? false))
            ->pluck('code')
            ->values()
            ->all();

        if ($codes !== []) {
            return $codes;
        }

        return collect(static::defaultDefinitions())
            ->filter(static fn (array $definition): bool => (bool) ($definition[$flag] ?? false))
            ->pluck('code')
            ->values()
            ->all();
    }
}
