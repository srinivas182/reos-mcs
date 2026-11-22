<?php

namespace App\Models;

use App\Enums\Appearance;
use App\Enums\Locale;
use App\Enums\TextSize;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string|null $name
 * @property string|null $email
 * @property Carbon|null $email_verified_at
 * @property string|null $phone
 * @property Carbon|null $phone_verified_at
 * @property Carbon|null $deletion_requested_at
 * @property string|null $onboarding_path
 * @property string|null $target_state
 * @property string|null $current_state The state they work in now (the state switcher, A5.1)
 * @property Carbon|null $exam_month
 * @property int|null $minutes_per_day
 * @property string|null $reminder_time
 * @property Carbon|null $onboarded_at
 * @property string|null $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Appearance $appearance
 * @property TextSize $text_size
 * @property Locale $locale
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, SocialAccount> $socialAccounts
 * @property-read Collection<int, Licence> $licences
 */
#[Fillable(['name', 'email', 'password', 'appearance', 'text_size', 'locale'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** Roles are held per organisation; see App\Services\Authorization. */
    protected string $guard_name = 'web';

    /**
     * Defaults that match the database, so new models have them before saving.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'appearance' => 'system',
        'text_size' => 'standard',
        'locale' => 'en',
    ];

    /** @return HasMany<SocialAccount, $this> */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /** @return HasMany<Licence, $this> */
    public function licences(): HasMany
    {
        return $this->hasMany(Licence::class);
    }

    /**
     * People who signed up with a phone number alone have no email to verify;
     * their verified phone stands in for it.
     */
    public function hasVerifiedEmail(): bool
    {
        if ($this->email === null) {
            return $this->phone_verified_at !== null;
        }

        return $this->email_verified_at !== null;
    }

    /** Emails and notifications go out in the person's chosen language. */
    public function preferredLocale(): string
    {
        return $this->locale->value;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'exam_month' => 'date',
            'minutes_per_day' => 'integer',
            'onboarded_at' => 'datetime',
            'password' => 'hashed',
            'appearance' => Appearance::class,
            'text_size' => TextSize::class,
            'locale' => Locale::class,
            /* @chisel-2fa */
            'two_factor_confirmed_at' => 'datetime',
            /* @end-chisel-2fa */
        ];
    }
}
