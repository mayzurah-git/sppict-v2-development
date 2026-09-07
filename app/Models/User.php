<?php

namespace App\Models;

// 1. Import Trait HasRoles dari Spatie & HasUuids / SoftDeletes
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;
    
    // 2. Masukkan HasRoles, HasUuids & SoftDeletes di sini
    use  HasUuids, SoftDeletes;

    /**
     * Tentukan kolum UUID untuk HasUuids
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uuid',
        'agency_id',
        'role_id',
        'name',
        'email',
        'position',
        'phone_number',
        'password',
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
        ];
    }

    // --- RELATIONSHIPS ---

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function applications()
    {
        return $this->hasMany(Application::class, 'applicant_id');
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

}
