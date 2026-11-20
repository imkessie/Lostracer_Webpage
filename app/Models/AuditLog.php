<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLog extends Model
{
    protected $guarded = [];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function claim()
    {
        return $this->belongsTo(Claim::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper terpusat untuk mencatat log audit append-only (ROL-02, HND-06).
     */
    public static function record(
        string $action,
        ?int $itemId = null,
        ?int $claimId = null,
        ?string $notes = null,
        ?string $photoPath = null,
        ?int $userId = null
    ): self {
        return self::create([
            'item_id' => $itemId,
            'claim_id' => $claimId,
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'photo_path' => $photoPath,
            'notes' => $notes,
        ]);
    }
}
