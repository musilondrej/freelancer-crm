<?php

namespace App\Models;

use App\Models\Concerns\EnforcesOwner;
use App\Models\Concerns\HasStatusOptionDefinitions;
use Database\Factories\ProjectActivityStatusOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectActivityStatusOption extends Model
{
    use EnforcesOwner;

    /** @use HasFactory<ProjectActivityStatusOptionFactory> */
    use HasFactory;

    use HasStatusOptionDefinitions;

    protected ?string $previousCodeBeforeSave = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'code',
        'label',
        'color',
        'icon',
        'sort_order',
        'is_default',
        'is_open',
        'is_done',
        'is_cancelled',
        'is_running',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_id' => 'integer',
            'sort_order' => 'integer',
            'is_default' => 'boolean',
            'is_open' => 'boolean',
            'is_done' => 'boolean',
            'is_cancelled' => 'boolean',
            'is_running' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $statusOption): void {
            $statusOption->code = Str::slug($statusOption->code, '_');

            $allowedStatusCodes = [
                'in_progress',
                'done',
                'cancelled',
            ];

            if (! in_array($statusOption->code, $allowedStatusCodes, true)) {
                throw ValidationException::withMessages([
                    'code' => 'Only in_progress, done, and cancelled status codes are supported for worklogs.',
                ]);
            }

            if ($statusOption->code === 'in_progress') {
                $statusOption->is_default = true;
                $statusOption->is_open = true;
                $statusOption->is_done = false;
                $statusOption->is_cancelled = false;
                $statusOption->is_running = true;
            }

            if ($statusOption->code === 'done') {
                $statusOption->is_default = false;
                $statusOption->is_open = false;
                $statusOption->is_done = true;
                $statusOption->is_cancelled = false;
                $statusOption->is_running = false;
            }

            if ($statusOption->code === 'cancelled') {
                $statusOption->is_default = false;
                $statusOption->is_open = false;
                $statusOption->is_done = false;
                $statusOption->is_cancelled = true;
                $statusOption->is_running = false;
            }

            if ($statusOption->exists && $statusOption->isDirty('code')) {
                $statusOption->previousCodeBeforeSave = (string) $statusOption->getOriginal('code');
            }

            $ownerId = (int) $statusOption->owner_id;

            if ($statusOption->is_default) {
                static::query()
                    ->where('owner_id', $ownerId)
                    ->when(
                        $statusOption->exists,
                        fn ($query) => $query->whereKeyNot($statusOption->getKey()),
                    )
                    ->update(['is_default' => false]);
            }

            if ($statusOption->is_running) {
                static::query()
                    ->where('owner_id', $ownerId)
                    ->when(
                        $statusOption->exists,
                        fn ($query) => $query->whereKeyNot($statusOption->getKey()),
                    )
                    ->update(['is_running' => false]);
            }
        });

        static::saved(function (self $statusOption): void {
            $previousCode = $statusOption->previousCodeBeforeSave;

            if ($previousCode !== null && $previousCode !== $statusOption->code) {
                Worklog::query()
                    ->where('owner_id', (int) $statusOption->owner_id)
                    ->where('status', $previousCode)
                    ->update(['status' => (string) $statusOption->code]);
            }

            $statusOption->previousCodeBeforeSave = null;
            self::flushDefinitionsCache((int) $statusOption->owner_id);
        });

        static::deleting(function (self $statusOption): void {
            if ($statusOption->isUsedByActivities()) {
                throw ValidationException::withMessages([
                    'code' => 'This status cannot be deleted because it is assigned to one or more worklogs.',
                ]);
            }
        });

        static::deleted(function (self $statusOption): void {
            self::flushDefinitionsCache((int) $statusOption->owner_id);
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function activitiesUsingStatusCount(): int
    {
        return Worklog::query()
            ->where('owner_id', (int) $this->owner_id)
            ->where('status', (string) $this->code)
            ->count();
    }

    public function isUsedByActivities(): bool
    {
        return $this->activitiesUsingStatusCount() > 0;
    }

    /**
     * @return list<array{
     *     code: string,
     *     label: string,
     *     color: string,
     *     icon: string|null,
     *     sort_order: int,
     *     is_default: bool,
     *     is_open: bool,
     *     is_done: bool,
     *     is_cancelled: bool,
     *     is_running: bool
     * }>
     */
    public static function defaultDefinitions(): array
    {
        return [
            [
                'code' => 'in_progress',
                'label' => 'In Progress',
                'color' => 'warning',
                'icon' => null,
                'sort_order' => 10,
                'is_default' => true,
                'is_open' => true,
                'is_done' => false,
                'is_cancelled' => false,
                'is_running' => true,
            ],
            [
                'code' => 'done',
                'label' => 'Done',
                'color' => 'success',
                'icon' => null,
                'sort_order' => 20,
                'is_default' => false,
                'is_open' => false,
                'is_done' => true,
                'is_cancelled' => false,
                'is_running' => false,
            ],
            [
                'code' => 'cancelled',
                'label' => 'Cancelled',
                'color' => 'danger',
                'icon' => null,
                'sort_order' => 30,
                'is_default' => false,
                'is_open' => false,
                'is_done' => false,
                'is_cancelled' => true,
                'is_running' => false,
            ],
        ];
    }

    public static function ensureDefaultsForOwner(int $ownerId): void
    {
        foreach (self::defaultDefinitions() as $definition) {
            static::query()->updateOrCreate(
                [
                    'owner_id' => $ownerId,
                    'code' => $definition['code'],
                ],
                [
                    'label' => $definition['label'],
                    'color' => $definition['color'],
                    'icon' => $definition['icon'],
                    'sort_order' => $definition['sort_order'],
                    'is_default' => $definition['is_default'],
                    'is_open' => $definition['is_open'],
                    'is_done' => $definition['is_done'],
                    'is_cancelled' => $definition['is_cancelled'],
                    'is_running' => $definition['is_running'],
                ],
            );
        }
    }

    /**
     * @return list<array{
     *     code: string,
     *     label: string,
     *     color: string,
     *     icon: string|null,
     *     sort_order: int,
     *     is_default: bool,
     *     is_open: bool,
     *     is_done: bool,
     *     is_cancelled: bool,
     *     is_running: bool
     * }>
     */
    public static function definitionsForOwner(?int $ownerId): array
    {
        if ($ownerId === null) {
            return self::defaultDefinitions();
        }

        return Cache::rememberForever(
            self::definitionsCacheKey($ownerId),
            static function () use ($ownerId): array {
                $records = static::query()
                    ->where('owner_id', $ownerId)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();

                if ($records->isEmpty()) {
                    return self::defaultDefinitions();
                }

                return $records
                    ->map(static fn (self $record): array => [
                        'code' => (string) $record->code,
                        'label' => (string) $record->label,
                        'color' => (string) $record->color,
                        'icon' => $record->icon !== null ? (string) $record->icon : null,
                        'sort_order' => (int) $record->sort_order,
                        'is_default' => (bool) $record->is_default,
                        'is_open' => (bool) $record->is_open,
                        'is_done' => (bool) $record->is_done,
                        'is_cancelled' => (bool) $record->is_cancelled,
                        'is_running' => (bool) $record->is_running,
                    ])
                    ->values()
                    ->all();
            },
        );
    }

    /**
     * @return list<string>
     */
    public static function doneCodesForOwner(?int $ownerId): array
    {
        return self::codesForBooleanFlag($ownerId, 'is_done');
    }

    /**
     * @return list<string>
     */
    public static function cancelledCodesForOwner(?int $ownerId): array
    {
        return self::codesForBooleanFlag($ownerId, 'is_cancelled');
    }

    public static function runningCodeForOwner(?int $ownerId): string
    {
        $runningDefinition = collect(self::definitionsForOwner($ownerId))
            ->firstWhere('is_running', true);

        if (is_array($runningDefinition)) {
            return (string) $runningDefinition['code'];
        }

        $openDefinition = collect(self::definitionsForOwner($ownerId))
            ->firstWhere('is_open', true);

        if (is_array($openDefinition)) {
            return (string) $openDefinition['code'];
        }

        return self::defaultCodeForOwner($ownerId);
    }

    private static function flushDefinitionsCache(int $ownerId): void
    {
        Cache::forget(self::definitionsCacheKey($ownerId));
    }

    private static function definitionsCacheKey(int $ownerId): string
    {
        return 'project_activity_status_options.'.$ownerId;
    }
}
