<?php

namespace App\Models;

use App\Enums\BacklogItemPriority;
use App\Enums\BacklogItemStatus;
use App\Enums\ProjectActivityType;
use App\Models\Concerns\EnforcesOwner;
use Database\Factories\BacklogItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Validation\ValidationException;

class BacklogItem extends Model
{
    use EnforcesOwner;

    /** @use HasFactory<BacklogItemFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'project_id',
        'activity_id',
        'title',
        'description',
        'status',
        'priority',
        'estimated_minutes',
        'due_date',
        'sort_order',
        'converted_to_worklog_id',
        'converted_at',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BacklogItemStatus::class,
            'priority' => BacklogItemPriority::class,
            'estimated_minutes' => 'integer',
            'due_date' => 'date',
            'sort_order' => 'integer',
            'converted_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Activity, $this>
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * @return BelongsTo<ProjectActivity, $this>
     */
    public function convertedWorklog(): BelongsTo
    {
        return $this->belongsTo(ProjectActivity::class, 'converted_to_worklog_id');
    }

    /**
     * @return HasMany<ProjectActivity, $this>
     */
    public function worklogs(): HasMany
    {
        return $this->hasMany(ProjectActivity::class, 'backlog_item_id');
    }

    /**
     * @return MorphMany<Note, $this>
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'noteable');
    }

    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function resolvedStatusCode(): string
    {
        $rawStatus = $this->getRawOriginal('status');

        return (string) $rawStatus;
    }

    public function resolvedStatusLabel(): string
    {
        return BacklogItemStatus::tryFrom($this->resolvedStatusCode())?->getLabel() ?? 'Unknown';
    }

    public function resolvedStatusColor(): string
    {
        return BacklogItemStatus::tryFrom($this->resolvedStatusCode())?->getColor() ?? 'gray';
    }

    public function resolvedPriorityValue(): int
    {
        return (int) ($this->getRawOriginal('priority') ?? BacklogItemPriority::Medium->value);
    }

    public function resolvedPriorityLabel(): string
    {
        return BacklogItemPriority::tryFrom($this->resolvedPriorityValue())?->getLabel() ?? 'Medium';
    }

    public function resolvedPriorityColor(): string
    {
        return BacklogItemPriority::tryFrom($this->resolvedPriorityValue())?->getColor() ?? 'info';
    }

    public function convertToWorklog(): ProjectActivity
    {
        $project = $this->project;

        if (! $project instanceof Project) {
            throw ValidationException::withMessages([
                'project_id' => 'Cannot convert backlog item without a project.',
            ]);
        }

        $existingWorklog = $this->convertedWorklog ?? $this->worklogs()->oldest('id')->first();

        if ($existingWorklog instanceof ProjectActivity) {
            if ($existingWorklog->backlog_item_id !== $this->getKey()) {
                $existingWorklog->update([
                    'backlog_item_id' => $this->getKey(),
                ]);
            }

            $this->update([
                'status' => BacklogItemStatus::Done,
                'converted_to_worklog_id' => $existingWorklog->getKey(),
                'converted_at' => $this->converted_at ?? now(),
            ]);

            return $existingWorklog;
        }

        $activity = $this->activity;
        $isBillable = $activity instanceof Activity ? (bool) $activity->is_billable : true;
        $unitRate = $activity instanceof Activity ? $activity->default_hourly_rate : null;

        $worklog = ProjectActivity::query()->create([
            'owner_id' => $this->owner_id,
            'project_id' => $this->project_id,
            'activity_id' => $this->activity_id,
            'backlog_item_id' => $this->getKey(),
            'title' => $this->title,
            'description' => $this->description,
            'type' => ProjectActivityType::Hourly,
            'status' => ProjectActivityStatusOption::defaultCodeForOwner($this->owner_id),
            'is_running' => false,
            'is_billable' => $isBillable,
            'currency' => $project->effectiveCurrency(),
            'unit_rate' => $unitRate,
            'due_date' => $this->due_date,
            'meta' => array_filter([
                'source' => 'backlog_conversion',
                'backlog_item_id' => $this->getKey(),
            ]),
        ]);

        $this->update([
            'status' => BacklogItemStatus::Done,
            'converted_to_worklog_id' => $worklog->getKey(),
            'converted_at' => now(),
        ]);

        return $worklog;
    }
}
