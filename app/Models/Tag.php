<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $guarded = [];

    public function items()
    {
        return $this->belongsToMany(Item::class, 'item_tag');
    }
}
