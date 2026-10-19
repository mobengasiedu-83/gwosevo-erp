<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'active_organization_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token', 'mfa_secret', 'mfa_recovery_codes', 'mfa_last_used',
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
            'mfa_secret' => 'encrypted', 'mfa_recovery_codes' => 'encrypted:array',
            'mfa_confirmed_at' => 'datetime', 'require_mfa' => 'boolean', 'auth_version' => 'integer',
        ];
    }

    public function organizations()
    {
        return $this->belongsToMany(Organization::class, 'user_organization_memberships')
            ->withPivot(['department_id', 'team_id', 'scope', 'branch_id', 'position_id', 'manager_id', 'approval_manager_id'])
            ->withTimestamps();
    }

    public function activeOrganization()
    {
        return $this->belongsTo(Organization::class, 'active_organization_id');
    }

    public function pbxExtensions()
    {
        return $this->hasMany(PbxExtension::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function requiresMfa(): bool
    {
        if (! config('identity.mfa_required', true)) {
            return false;
        }

        return $this->require_mfa || $this->hasRole('Administrator') || $this->can('leads.approve') || $this->roles()->where('requires_mfa', true)->exists();
    }

    public function canAccessOrganization(int $organizationId): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        return $this->hasRole('Administrator')
            || $this->organizations()->whereKey($organizationId)->exists();
    }
}
