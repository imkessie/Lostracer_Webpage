<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'is_blocked' => 'boolean',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    public function claims()
    {
        return $this->hasMany(Claim::class);
    }

    public function verifiedClaims()
    {
        return $this->hasMany(Claim::class, 'verified_by');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function receivedMessages()
    {
        return $this->hasMany(Message::class, 'receiver_id');
    }

    /**
     * Cek apakah pengguna memiliki salah satu peran yang diizinkan (ROL-01).
     */
    public function hasRole(string|array $roles): bool
    {
        $allowedRoles = is_array($roles) ? $roles : [$roles];

        return in_array($this->role, $allowedRoles, true);
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isSecurity(): bool
    {
        return $this->role === 'security';
    }

    public function isItAdmin(): bool
    {
        return $this->role === 'it_admin';
    }
}
