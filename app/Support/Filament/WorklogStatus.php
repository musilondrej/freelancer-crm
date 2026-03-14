<?php

namespace App\Support\Filament;

use App\Models\ProjectActivityStatusOption;

class WorklogStatus
{
    /**
     * @var list<string>
     */
    private const ALLOWED_CODES = [
        'in_progress',
        'done',
        'cancelled',
    ];

    /**
     * @return array<string, string>
     */
    public static function options(?int $ownerId): array
    {
        return collect(ProjectActivityStatusOption::optionsForOwner($ownerId))
            ->only(self::ALLOWED_CODES)
            ->all();
    }

    public static function defaultCode(?int $ownerId): string
    {
        $options = self::options($ownerId);
        $defaultCode = ProjectActivityStatusOption::defaultCodeForOwner($ownerId);

        if (array_key_exists($defaultCode, $options)) {
            return $defaultCode;
        }

        return self::ALLOWED_CODES[0];
    }
}
