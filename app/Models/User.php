<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
   use HasFactory, Notifiable;
   protected $guarded = [];
   protected $hidden = ['password', 'remember_token'];

   public function items()
   {
    return $this->hasMany(Item::class);
   }

   public function claims()
   {
    return $this->hasMany(Claim::class);
   }
}
