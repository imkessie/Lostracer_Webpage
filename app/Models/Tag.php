<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    protected $guarded = [];

    public function items()
    {
        return $this->belongsTo(Item::class, 'item_tag');
    }
}
