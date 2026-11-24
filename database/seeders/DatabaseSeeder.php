<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Item;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Official Development Accounts (Blueprint Section 11)
        $security = User::create([
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

        $kessie = User::create([
            'name' => 'Kessie',
            'email' => 'kessie@student.uph.edu',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);

        // Additional peer student account for claim and 1-on-1 chat demonstrations
        $alvaro = User::create([
            'name' => 'Alvaro Rahardjo',
            'email' => 'alvaro@student.uph.edu',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);

        // 2. Master Categories
        $categories = [
            'Electronics & Gadgets',
            'Identity Cards (KTM/KTP/SIM)',
            'Personal Accessories',
            'Clothing & Apparel',
            'Books & Stationery',
            'Others',
        ];

        $categoryMap = [];
        foreach ($categories as $categoryName) {
            $category = Category::firstOrCreate(['name' => $categoryName]);
            $categoryMap[$categoryName] = $category->id;
        }

        // 3. Initial Hashtags
        $tagNames = ['tumbler', 'ktm', 'laptop', 'charger', 'jacket', 'wallet', 'keys', 'earphones'];
        $tagMap = [];
        foreach ($tagNames as $tagName) {
            $tag = Tag::firstOrCreate(['name' => $tagName]);
            $tagMap[$tagName] = $tag->id;
        }

        // 4. Sample Posts for Local Demonstration
        $foundTumbler = Item::create([
            'user_id' => $alvaro->id,
            'category_id' => $categoryMap['Personal Accessories'],
            'type' => 'found',
            'status' => 'open',
            'title' => 'Matte Black Corkcicle Tumbler 475ml',
            'description' => 'Found left on a study table near the window and handed over to the Building B security desk.',
            'building' => 'Building B',
            'floor_detail' => 'Floor 3, Room B304',
            'event_date' => now()->subDays(2)->toDateString(),
            'photo_path' => null,
            'is_sensitive' => false,
            'custody_location' => 'Pos Keamanan Gedung B',
            'deposit_note' => 'Deposited with Officer Budi (Afternoon Shift)',
            'hidden_detail' => 'Small HIMSI sticker on the bottom of the bottle.',
            'move_due_at' => now()->addMonths(3),
            'expires_at' => now()->addMonths(6),
        ]);
        $foundTumbler->tags()->sync([$tagMap['tumbler']]);

        $foundCharger = Item::create([
            'user_id' => $security->id,
            'category_id' => $categoryMap['Electronics & Gadgets'],
            'type' => 'found',
            'status' => 'open',
            'title' => 'USB-C Laptop Charger 65W',
            'description' => 'Turned in directly to the security desk by a student finder and stored on the Priority shelf.',
            'building' => 'Perpustakaan UPH',
            'floor_detail' => 'Quiet Reading Area Floor 2',
            'event_date' => now()->subMonths(3)->subDays(5)->toDateString(),
            'photo_path' => null,
            'is_sensitive' => false,
            'custody_location' => 'Gudang Lost & Found — Rak Priority/Recent (Bulan 1-3)',
            'deposit_note' => 'Logged by security on behalf of anonymous finder',
            'hidden_detail' => 'Cable tied with a blue velcro strap marked with initials R.S.',
            'move_due_at' => now()->subDays(5), // Past 3 months to demonstrate Extended Shelf reminder
            'expires_at' => now()->addMonths(3),
        ]);
        $foundCharger->tags()->sync([$tagMap['charger'], $tagMap['laptop']]);

        $lostJacket = Item::create([
            'user_id' => $kessie->id,
            'category_id' => $categoryMap['Clothing & Apparel'],
            'type' => 'lost',
            'status' => 'open',
            'title' => 'Navy UPH Hoodie Jacket',
            'description' => 'Last worn during lunch at Food Junction near the east escalator seating area.',
            'building' => 'Food Junction',
            'floor_detail' => 'East Seating Area',
            'event_date' => now()->subDay()->toDateString(),
            'photo_path' => null,
            'is_sensitive' => false,
            'move_due_at' => now()->addMonths(3),
            'expires_at' => now()->addMonths(6),
        ]);
        $lostJacket->tags()->sync([$tagMap['jacket']]);
    }
}
