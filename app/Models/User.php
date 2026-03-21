<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
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

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
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

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'owner_id');
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

    public function defaultHourlyRateForCurrency(?string $currency = null): ?float
    {
        $normalizedCurrency = is_string($currency) && trim($currency) !== ''
            ? strtoupper(trim($currency))
            : null;

        if ($normalizedCurrency !== null) {
            $hourlyRateByCurrency = $this->hourlyRateByCurrency($normalizedCurrency);

            if ($hourlyRateByCurrency !== null) {
                return $hourlyRateByCurrency;
            }
        }

        return $this->default_hourly_rate !== null
            ? (float) $this->default_hourly_rate
            : null;
    }

    private function hourlyRateByCurrency(string $currency): ?float
    {
        $preferences = $this->resolvedPreferences();
        $hourlyRates = data_get($preferences, 'billing.hourly_rates');

        if (! is_array($hourlyRates)) {
            return null;
        }

        foreach (array_reverse($hourlyRates) as $hourlyRateItem) {
            if (! is_array($hourlyRateItem)) {
                continue;
            }

            $itemCurrency = strtoupper(trim((string) ($hourlyRateItem['currency'] ?? '')));

            if ($itemCurrency !== $currency) {
                continue;
            }

            $itemHourlyRate = $hourlyRateItem['hourly_rate'] ?? null;

            if (! is_numeric($itemHourlyRate)) {
                continue;
            }

            return (float) $itemHourlyRate;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvedPreferences(): array
    {
        $settings = $this->relationLoaded('userSetting')
            ? $this->getRelation('userSetting')
            : $this->userSetting()->first();

        if (! $settings instanceof UserSetting) {
            return UserSetting::defaultPreferences();
        }

        $preferences = $settings->getAttribute('preferences');

        if (is_string($preferences)) {
            $decodedPreferences = json_decode($preferences, true);
            $preferences = is_array($decodedPreferences) ? $decodedPreferences : null;
        }

        if (! is_array($preferences)) {
            return UserSetting::defaultPreferences();
        }

        return array_replace_recursive(
            UserSetting::defaultPreferences(),
            $preferences,
        );
    }
}
