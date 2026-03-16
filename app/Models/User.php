<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'default_currency',
        'default_hourly_rate',
        'dashboard_widgets',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'default_hourly_rate' => 'decimal:2',
            'dashboard_widgets' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $user): void {
            UserSetting::ensureForUser($user->id);
        });
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'owner_id');
    }

    public function clientContacts(): HasMany
    {
        return $this->hasMany(ClientContact::class, 'owner_id');
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'owner_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class, 'owner_id');
    }

    public function projectActivities(): HasMany
    {
        return $this->hasMany(Worklog::class, 'owner_id');
    }

    public function backlogItems(): HasMany
    {
        return $this->hasMany(BacklogItem::class, 'owner_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'owner_id');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(Tag::class, 'owner_id');
    }

    public function leadSources(): HasMany
    {
        return $this->hasMany(LeadSource::class, 'owner_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'owner_id');
    }

    public function recurringServices(): HasMany
    {
        return $this->hasMany(RecurringService::class, 'owner_id');
    }

    public function recurringServiceTypes(): HasMany
    {
        return $this->hasMany(RecurringServiceType::class, 'owner_id');
    }

    public function userSetting(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }
}
