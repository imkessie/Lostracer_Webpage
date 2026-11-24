<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $guarded = [];

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    /**
     * Cek apakah kategori ini adalah opsi "Others".
     */
    public function isOthers(): bool
    {
        return strtolower(trim($this->name)) === 'others';
    }

    /**
     * Mengurutkan kategori secara alfabetis dengan "Others" selalu di posisi paling akhir.
     */
    public static function orderedForDropdown(): Collection
    {
        return static::orderBy('name')
            ->get()
            ->sortBy(fn (self $category) => [$category->isOthers() ? 1 : 0, strtolower($category->name)])
            ->values();
    }
}
