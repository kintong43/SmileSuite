<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use App\Tenancy\TenantContext;
use Illuminate\Auth\MustVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail as MustVerifyEmailContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements MustVerifyEmailContract
{
    use BelongsToTenant, HasFactory, MustVerifyEmail, Notifiable, TwoFactorAuthenticatable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected function casts(): array
    {
        return ['password' => 'hashed', 'email_verified_at' => 'immutable_datetime', 'two_factor_confirmed_at' => 'immutable_datetime'];
    }

    public function setEmailAttribute(string $email): void
    {
        $this->attributes['email'] = strtolower(trim($email));
    }

    public function roles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function roleNames(): array
    {
        if ((int) $this->tenant_id !== TenantContext::current()->id()) {
            throw new \LogicException('Cross-tenant role access denied.');
        }

        return $this->roles()->pluck('role')->all();
    }

    public function hasRole(string ...$roles): bool
    {
        return count(array_intersect($roles, $this->roleNames())) > 0;
    }

    public function patientProfile(): HasOne
    {
        return $this->hasOne(PatientProfile::class);
    }

    public function practitioner(): HasOne
    {
        return $this->hasOne(Practitioner::class);
    }
}
