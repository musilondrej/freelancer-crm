<?php

namespace App\Models;

use App\Models\Concerns\EnforcesOwner;
use App\Models\Concerns\HasStatusOptionDefinitions;
use Database\Factories\ProjectStatusOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProjectStatusOption extends Model
{
    use EnforcesOwner;

    /** @use HasFactory<ProjectStatusOptionFactory> */
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
        'is_trackable',
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
            'is_trackable' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $statusOption): void {
            $statusOption->code = Str::slug($statusOption->code, '_');

            if ($statusOption->exists && $statusOption->isDirty('code')) {
                $statusOption->previousCodeBeforeSave = (string) $statusOption->getOriginal('code');
            }

            if (! $statusOption->is_default) {
                return;
            }

            $ownerId = (int) $statusOption->owner_id;

            static::query()
                ->where('owner_id', $ownerId)
                ->when(
                    $statusOption->exists,
                    fn ($query) => $query->whereKeyNot($statusOption->getKey()),
                )
                ->update(['is_default' => false]);
        });

        static::saved(function (self $statusOption): void {
            $previousCode = $statusOption->previousCodeBeforeSave;

            if ($previousCode !== null && $previousCode !== $statusOption->code) {
                Project::query()
                    ->where('owner_id', (int) $statusOption->owner_id)
                    ->where('status', $previousCode)
                    ->update(['status' => (string) $statusOption->code]);
            }

            $statusOption->previousCodeBeforeSave = null;
            self::flushDefinitionsCache((int) $statusOption->owner_id);
        });

        static::deleting(function (self $statusOption): void {
            if ($statusOption->isUsedByProjects()) {
                throw ValidationException::withMessages([
                    'code' => 'This status cannot be deleted because it is assigned to one or more projects.',
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

    public function projectsUsingStatusCount(): int
    {
        return Project::query()
            ->where('owner_id', (int) $this->owner_id)
            ->where('status', (string) $this->code)
            ->count();
    }

    public function isUsedByProjects(): bool
    {
        return $this->projectsUsingStatusCount() > 0;
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
     *     is_trackable: bool
     * }>
     */
    public static function defaultDefinitions(): array
    {
        return [
            [
                'code' => 'planned',
                'label' => 'Planned',
                'color' => 'gray',
                'icon' => null,
                'sort_order' => 10,
                'is_default' => true,
                'is_open' => true,
                'is_trackable' => true,
            ],
            [
                'code' => 'in_progress',
                'label' => 'In Progress',
                'color' => 'warning',
                'icon' => null,
                'sort_order' => 20,
                'is_default' => false,
                'is_open' => true,
                'is_trackable' => true,
            ],
            [
                'code' => 'blocked',
                'label' => 'Blocked',
                'color' => 'danger',
                'icon' => null,
                'sort_order' => 30,
                'is_default' => false,
                'is_open' => true,
                'is_trackable' => true,
            ],
            [
                'code' => 'completed',
                'label' => 'Completed',
                'color' => 'success',
                'icon' => null,
                'sort_order' => 40,
                'is_default' => false,
                'is_open' => false,
                'is_trackable' => true,
            ],
            [
                'code' => 'cancelled',
                'label' => 'Cancelled',
                'color' => 'danger',
                'icon' => null,
                'sort_order' => 50,
                'is_default' => false,
                'is_open' => false,
                'is_trackable' => false,
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
                    'is_trackable' => $definition['is_trackable'],
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
     *     is_trackable: bool
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
                        'is_trackable' => (bool) $record->is_trackable,
                    ])
                    ->values()
                    ->all();
            },
        );
    }

    /**
     * @return list<string>
     */
    public static function trackableCodesForOwner(?int $ownerId): array
    {
        return self::codesForBooleanFlag($ownerId, 'is_trackable');
    }

    private static function flushDefinitionsCache(int $ownerId): void
    {
        Cache::forget(self::definitionsCacheKey($ownerId));
    }

    private static function definitionsCacheKey(int $ownerId): string
    {
        return 'project_status_options.'.$ownerId;
    }
}
