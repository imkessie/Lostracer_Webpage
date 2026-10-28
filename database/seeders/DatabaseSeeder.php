<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run():void
    {
        User::create([
            'name' => 'Petugas Keamanan UPH',
            'email' => 'security@uph.edu',
            'password' => Hash::make('password'),
            'role' => 'security',
        ]);

        User::create([
            'name' => 'Admin IT Kampus',
            'email' => 'admin@uph.edu',
            'password' => Hash::make('password'),
            'role' => 'it_admin',
        ]);

        User::create([
            'name' => 'Student',
            'email' => 'kessie@student.uph.edu',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);

        $kategori = [
            'Elektronik & Gadget',
            'Kartu Identitas (KTM/KTP/SIM)',
            'Perlengkapan Pribadi',
            'Pakaian',
            'Buku & Alat Tulis',
        ];

        foreach ($kategori as $kat){
            Category::create(['name' => $kat]);
        }

        $tags = ['tumbler', 'ktm', 'laptop', 'charger', 'jaket', 'dompet', 'kunci', 'earphone'];

        foreach ($tags as $t){
            Tag::create(['name' => $t]);
        }
    }
}