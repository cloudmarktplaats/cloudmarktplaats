<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes;

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

    protected static function booted(): void
    {
        static::creating(function (self $u): void {
            $u->ulid = $u->ulid ?? (string) Str::ulid();
        });
    }
}
