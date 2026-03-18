<?php

namespace App\Models;

use App\Models\Concerns\EnforcesOwner;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Tag extends Model
{
    use EnforcesOwner;

    /** @use HasFactory<TagFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_id',
        'name',
        'slug',
        'color',
        'sort_order',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
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
     * @return MorphToMany<Customer, $this>
     */
    public function customers(): MorphToMany
    {
        return $this->morphedByMany(Customer::class, 'taggable');
    }

    /**
     * @return MorphToMany<Project, $this>
     */
    public function projects(): MorphToMany
    {
        return $this->morphedByMany(Project::class, 'taggable');
    }

    /**
     * @return MorphToMany<Note, $this>
     */
    public function notes(): MorphToMany
    {
        return $this->morphedByMany(Note::class, 'taggable');
    }

    /**
     * @return MorphToMany<Lead, $this>
     */
    public function leads(): MorphToMany
    {
        return $this->morphedByMany(Lead::class, 'taggable');
    }

    /**
     * @return MorphToMany<Task, $this>
     */
    public function tasks(): MorphToMany
    {
        return $this->morphedByMany(Task::class, 'taggable');
    }

    /**
     * @return MorphToMany<RecurringService, $this>
     */
    public function recurringServices(): MorphToMany
    {
        return $this->morphedByMany(RecurringService::class, 'taggable');
    }
}
