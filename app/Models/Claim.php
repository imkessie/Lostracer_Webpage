<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Claim extends Model
{
    protected $guarded = [];

    protected $casts = [
        'decided_at' => 'datetime',
        'handover_at' => 'datetime',
        'attempt_no' => 'integer',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }
}
