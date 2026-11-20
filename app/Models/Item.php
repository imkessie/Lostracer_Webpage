<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $guarded = [];

    protected $casts = [
        'event_date' => 'date',
        'is_sensitive' => 'boolean',
        'is_hidden' => 'boolean',
        'move_due_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'item_tag');
    }

    public function claims()
    {
        return $this->hasMany(Claim::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Cek apakah barang sudah memiliki klaim berstatus approved (menunggu serah-terima).
     */
    public function hasApprovedClaim(): bool
    {
        return $this->claims()->where('status', 'approved')->exists();
    }

    /**
     * Cek apakah sudah ada klaim yang masuk (REP-04: kunci edit pelapor biasa).
     */
    public function hasAnyClaims(): bool
    {
        return $this->claims()->exists();
    }

    /**
     * Cek apakah laporan temuan masih boleh menerima pengajuan klaim baru (STA-02).
     */
    public function isClaimable(): bool
    {
        if ($this->type !== 'found' || $this->is_hidden) {
            return false;
        }

        if (! in_array($this->status, ['open', 'claimed'], true)) {
            return false;
        }

        return ! $this->hasApprovedClaim();
    }

    /**
     * Status terminal (returned atau expired) mengunci edit, klaim, dan chat (STA-03, HND-04).
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, ['returned', 'expired'], true);
    }

    /**
     * Cek apakah barang temuan perlu dipindahkan ke Extended Shelf (bulan ke-3, CUS-08).
     */
    public function isDueForExtendedShelf(): bool
    {
        if ($this->type !== 'found' || $this->isTerminal() || $this->move_due_at === null) {
            return false;
        }

        if ($this->move_due_at->isFuture()) {
            return false;
        }

        return $this->custody_location !== config('lostracer.extended_shelf_location')
            && ! str_contains((string) $this->custody_location, 'Lemari Terkunci');
    }
}
