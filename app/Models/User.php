<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Auth;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasUuids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'phone',
        'password',
        'must_change_password',
        'role_id',
        'institution_id',
        'department_id',
        'is_active',
        'avatar',
        'signature',
        'location',
        'bio',
        'timezone',
        'language',
        'last_login_at',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** Filter by active users */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Filter by specific role */
    public function scopeRole($query, $role)
    {
        return $query->where('role_id', $role);
    }

     /** Filter users belonging to same institution as the logged-in user */
    public function scopeSameInstitution($query, $institutionId = null)
    {
        // If institutionId is provided, use it
        if ($institutionId !== null) {
            return $query->where('institution_id', $institutionId);
        }

        // Otherwise, use the logged-in user's institution_id
        $authInstitutionId = Auth::check() ? Auth::user()->institution_id : null;

        if (!$authInstitutionId) {
            return $query;
        }

        return $query->where('institution_id', $authInstitutionId);
    }

    /** Exclude the current user */
    public function scopeExcludeCurrentUser($query)
    {
        return $query->where('id', '!=', Auth::id());
    }

    /** Relationship to Institution */
    public function institution()
    {
        return $this->belongsTo(Institution::class);
    }

    public function myInstitutionID()
    {
        return Auth::user()->institution_id;
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function isDepartmentHead()
    {
        if (!$this->role_id) return false;

        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        return $this->role && $this->role->name === 'Department Head';
    }

    public function isAdmin()
    {
        if (!$this->role_id) return false;

        if (!$this->relationLoaded('role')) {
            $this->load('role');
        }

        return $this->role && $this->role->name === 'Admin';
    }

    public function getAvatarUrlAttribute()
    {
        if (!$this->avatar) {
            return null;
        }

        // If it's already a full URL, return as-is
        if (filter_var($this->avatar, FILTER_VALIDATE_URL)) {
            return $this->avatar;
        }

        // Check if file exists in storage/app/public/
        // if (!Storage::disk('public')->exists($this->avatar)) {
        //     return null;
        // }

        return asset('storage/' . $this->avatar);
    }
}
