<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements CanResetPassword, FilamentUser, HasName, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use CanResetPasswordTrait, HasFactory, Notifiable, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'email',
        'password_hash',
        'username',
        'display_name',
        'role',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password_hash',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'is_banned' => 'boolean',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash ?? '';
    }

    /**
     * @return HasMany<UserIdentity, $this>
     */
    public function identities(): HasMany
    {
        return $this->hasMany(UserIdentity::class);
    }

    /**
     * @return HasMany<LegalAcceptance, $this>
     */
    public function legalAcceptances(): HasMany
    {
        return $this->hasMany(LegalAcceptance::class);
    }

    public function hasIdentity(string $provider): bool
    {
        return $this->identities()->where('provider', $provider)->exists();
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    /**
     * Gate Filament panel access to staff roles.
     *
     * The Filament admin panel is reserved for moderators and admins; regular
     * users hit a 403 via the `role` middleware on the panel route, and this
     * method ensures the same outcome if any internal Filament check is
     * invoked outside that middleware (e.g. component-level guards).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('admin', 'moderator');
    }

    /**
     * Display name surfaced by Filament (avatar, header, audit log).
     *
     * Falls back to the username when `display_name` is empty so the panel
     * never renders an empty avatar tooltip — Filament treats a `null` name
     * as a runtime error.
     */
    public function getFilamentName(): string
    {
        return $this->display_name ?: $this->username ?? $this->email;
    }

    protected static function booted(): void
    {
        static::creating(function (self $u): void {
            $u->ulid = $u->ulid ?? (string) Str::ulid();
        });
    }
}
