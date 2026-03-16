<?php

namespace App\Models;

use App\Models\HR\HrRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'force_password_change',
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
            'force_password_change' => 'boolean',
        ];
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    public function getSettingsAttribute(): UserSetting
    {
        return $this->settings()->firstOrCreate(['user_id' => $this->id]);
    }

    public function hrRoles(): BelongsToMany
    {
        return $this->belongsToMany(
            HrRole::class,
            'model_has_roles',
            'model_id',
            'role_id'
        )
            ->wherePivot('model_type', self::class)
            ->withPivot('model_type', 'assigned_by', 'assigned_at', 'created_at', 'updated_at');
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isHrAdmin()) {
            return true;
        }

        $permission = mb_strtolower(trim($permission));

        return $this->hrRoles()
            ->with('permissions')
            ->get()
            ->flatMap(fn ($role) => $role->permissions)
            ->pluck('name')
            ->map(fn ($name) => mb_strtolower(trim((string) $name)))
            ->contains($permission);
    }

    public function isHrAdmin(): bool
    {
        return $this->hasRole('HR Admin');
    }
}
