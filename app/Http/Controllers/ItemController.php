<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ItemController extends Controller
{
    // 1. Menampilkan Katalog Visual Dual Feed & Pencarian
    public function index(Request $request)
    {
        $type = $request->get('type', 'found');
        $q = $request->get('q');

        // Query barang aktif berdasarkan tab (found/lost)
        $query = Item::with(['category', 'tags', 'user'])
            ->where('status', 'open')
            ->where('type', $type)
            ->latest();

        // Pencarian judul, deskripsi, lokasi gedung, atau hashtag
        if ($q) {
            $cleanedTag = strtolower(trim(ltrim($q, '#')));
            $query->where(function ($sub) use ($q, $cleanedTag) {
                $sub->where('title', 'like', '%' . $q . '%')
                    ->orWhere('description', 'like', '%' . $q . '%')
                    ->orWhere('building', 'like', '%' . $q . '%')
                    ->orWhereHas('tags', function ($tagQuery) use ($cleanedTag) {
                        $tagQuery->where('name', 'like', '%' . $cleanedTag . '%');
                    });
            });
        }

        $items = $query->get();

        // Penghitung jumlah laporan untuk badge tab
        $totalFound = Item::where('type', 'found')->where('status', 'open')->count();
        $totalLost = Item::where('type', 'lost')->where('status', 'open')->count();

        return view('dashboard', compact('items', 'type', 'q', 'totalFound', 'totalLost'));
    }

    // 2. Menampilkan Form Tambah Laporan
    public function create()
    {
        $categories = Category::orderBy('name')->get();
        return view('items.create', compact('categories'));
    }

    // 3. Menyimpan Laporan Barang & Upload Foto
    public function store(Request $request)
    {
        $request->validate([
            'type' => 'required|in:lost,found',
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|max:150',
            'description' => 'required',
            'building' => 'required',
            'floor_detail' => 'nullable|max:100',
            'event_date' => 'required|date',
            'photo' => 'required|image|max:2048',
            'tags' => 'nullable|string',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('items', 'public');
        }

        $item = Item::create([
            'user_id' => Auth::id(),
            'category_id' => $request->category_id,
            'type' => $request->type,
            'status' => 'open',
            'title' => $request->title,
            'description' => $request->description,
            'building' => $request->building,
            'floor_detail' => $request->floor_detail,
            'event_date' => $request->event_date,
            'photo_path' => $photoPath,
            'is_sensitive' => $request->has('is_sensitive'),
        ]);

        // Simpan relasi tagar
        if ($request->filled('tags')) {
            $tagNames = explode(',', $request->tags);
            $tagIds = [];

            foreach ($tagNames as $name) {
                $cleanedName = strtolower(trim(ltrim($name, '#')));
                if (!empty($cleanedName)) {
                    $tag = Tag::firstOrCreate(['name' => $cleanedName]);
                    $tagIds[] = $tag->id;
                }
            }

            $item->tags()->sync(array_unique($tagIds));
        }

        return redirect('/dashboard')->with('success', 'Laporan barang berhasil diterbitkan!');
    }
}