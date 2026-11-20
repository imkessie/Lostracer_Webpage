<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Item;
use App\Models\Message;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ItemAndPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private User $otherStudent;

    private User $security;

    private User $itAdmin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');

        $this->student = User::create([
            'name' => 'Kessie',
            'email' => 'kessie@student.uph.edu',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);

        $this->otherStudent = User::create([
            'name' => 'Alvaro',
            'email' => 'alvaro@student.uph.edu',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);

        $this->security = User::create([
            'name' => 'Petugas UPH',
            'email' => 'security@uph.edu',
            'password' => Hash::make('password'),
            'role' => 'security',
        ]);

        $this->itAdmin = User::create([
            'name' => 'Admin IT',
            'email' => 'admin@uph.edu',
            'password' => Hash::make('password'),
            'role' => 'it_admin',
        ]);

        $this->category = Category::create(['name' => 'Kartu Identitas (KTM/KTP/SIM)']);
    }

    public function test_ac06_found_report_requires_photo_and_deposit_location_and_sets_retention_dates(): void
    {
        $invalid = $this->actingAs($this->student)->post('/items', [
            'type' => 'found',
            'category_id' => $this->category->id,
            'title' => 'KTM Mahasiswa',
            'description' => 'Ditemukan di tangga.',
            'building' => 'Building B',
            'floor_detail' => 'Lantai 2',
            'event_date' => now()->toDateString(),
            'tags' => 'ktm, kartu',
        ]);

        $invalid->assertSessionHasErrors(['photo', 'custody_location', 'deposit_note']);

        $valid = $this->actingAs($this->student)->post('/items', [
            'type' => 'found',
            'category_id' => $this->category->id,
            'title' => 'KTM Mahasiswa Biru',
            'description' => 'Ditemukan di dekat lift Building B.',
            'building' => 'Building B',
            'floor_detail' => 'Lantai 2',
            'event_date' => now()->toDateString(),
            'photo' => UploadedFile::fake()->image('ktm.jpg'),
            'custody_location' => 'Pos Keamanan Gedung B',
            'deposit_note' => 'Diserahkan ke Pak Budi',
            'hidden_detail' => 'Ada stiker kecil di belakang kartu',
            'tags' => '#ktm, kartu',
            'is_sensitive' => '1',
        ]);

        $item = Item::first();
        $this->assertNotNull($item);
        $valid->assertRedirect(route('items.show', $item));
        $this->assertEquals('open', $item->status);
        $this->assertTrue($item->is_sensitive);
        $this->assertNotNull($item->move_due_at);
        $this->assertNotNull($item->expires_at);

        // PRV-05: Foto sensitif wajib tersimpan di disk privat 'local'
        Storage::disk('local')->assertExists($item->photo_path);
        Storage::disk('public')->assertMissing($item->photo_path);
    }

    public function test_prv05_sensitive_photo_and_hidden_detail_are_protected_from_non_security(): void
    {
        $path = UploadedFile::fake()->image('ktm.jpg')->store('items/sensitive', 'local');

        $item = Item::create([
            'user_id' => $this->student->id,
            'category_id' => $this->category->id,
            'type' => 'found',
            'status' => 'open',
            'title' => 'KTM UPH',
            'description' => 'Ditemukan di lobby.',
            'building' => 'Building A',
            'floor_detail' => 'Lantai 1',
            'event_date' => now()->toDateString(),
            'photo_path' => $path,
            'is_sensitive' => true,
            'custody_location' => 'Pos Keamanan Gedung A',
            'deposit_note' => 'Pos A',
            'hidden_detail' => 'Kode rahasia 12345',
        ]);

        // Detail rahasia tidak boleh bocor ke user biasa maupun it_admin
        $this->actingAs($this->otherStudent)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertDontSee('Kode rahasia 12345');

        $this->actingAs($this->itAdmin)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertDontSee('Kode rahasia 12345');

        $this->actingAs($this->security)
            ->get(route('items.show', $item))
            ->assertOk()
            ->assertSee('Kode rahasia 12345');

        // Endpoint foto sensitif mengembalikan gambar ter-blur/SVG terkunci untuk user, asli untuk security
        $userPhoto = $this->actingAs($this->otherStudent)->get(route('items.photo', $item));
        $userPhoto->assertOk();
        $this->assertNotEquals(Storage::disk('local')->get($path), $userPhoto->getContent());

        $securityPhoto = $this->actingAs($this->security)->get(route('items.photo', $item));
        $securityPhoto->assertOk();
    }

    public function test_ac02_security_can_relocate_custody_location_and_rejects_unconfigured_location(): void
    {
        $item = Item::create([
            'user_id' => $this->student->id,
            'category_id' => $this->category->id,
            'type' => 'found',
            'status' => 'open',
            'title' => 'Tumbler Biru',
            'description' => 'Ditemukan di kelas.',
            'building' => 'Building C',
            'floor_detail' => 'C201',
            'event_date' => now()->toDateString(),
            'custody_location' => 'Pos Keamanan Gedung C / D',
            'deposit_note' => 'Shift pagi',
        ]);

        $this->actingAs($this->security)
            ->post(route('items.location.update', $item), [
                'custody_location' => 'Lokasi Sembarang Tidak Resmi',
            ])
            ->assertSessionHasErrors('custody_location');

        $targetLocation = 'Gudang Lost & Found — Rak Priority/Recent (Bulan 1-3)';

        $this->actingAs($this->security)
            ->get(route('items.location', $item))
            ->assertOk()
            ->assertSee('Custody Relocation');

        $this->actingAs($this->security)
            ->post(route('items.location.update', $item), [
                'custody_location' => $targetLocation,
                'notes' => 'Dipindah ke gudang pusat.',
            ])
            ->assertRedirect(route('items.show', $item));

        $item->refresh();
        $this->assertEquals($targetLocation, $item->custody_location);
        $this->assertEquals('open', $item->status);
        $this->assertDatabaseHas('audit_logs', [
            'item_id' => $item->id,
            'action' => 'ITEM_MOVED',
        ]);
    }

    public function test_fr02_and_fr03_dual_feed_and_hashtag_search_including_tag_relation(): void
    {
        $tag = Tag::create(['name' => 'corkcicle']);

        $foundClaimed = Item::create([
            'user_id' => $this->student->id,
            'category_id' => $this->category->id,
            'type' => 'found',
            'status' => 'claimed',
            'title' => 'Botol Minum Hijau',
            'description' => 'Botol minum di perpustakaan.',
            'building' => 'Perpustakaan UPH',
            'floor_detail' => 'Lantai 2',
            'event_date' => now()->toDateString(),
            'custody_location' => 'Lobby Gedung B',
            'deposit_note' => 'Lobby B',
        ]);
        $foundClaimed->tags()->sync([$tag->id]);

        // Tab found menampilkan barang open maupun claimed (FR-02) dan pencarian #hashtag (FR-03)
        $this->actingAs($this->otherStudent)
            ->get('/dashboard?type=found&q=%23corkcicle')
            ->assertOk()
            ->assertSee('Botol Minum Hijau');

        // Filter kategori dan gedung pada katalog Dual Feed
        $this->actingAs($this->otherStudent)
            ->get('/dashboard?type=found&category='.$this->category->id.'&building=Perpustakaan+UPH')
            ->assertOk()
            ->assertSee('Botol Minum Hijau');

        $this->actingAs($this->otherStudent)
            ->get('/dashboard?type=found&building=Building+A')
            ->assertOk()
            ->assertDontSee('Botol Minum Hijau');

        // Relasi terbalik Tag -> items() berjalan benar (bug v1 belongsTo sudah diperbaiki)
        $this->assertCount(1, $tag->items);
    }

    public function test_rep04_and_sta04_reporter_edit_lock_and_close_lost_report(): void
    {
        $lostItem = Item::create([
            'user_id' => $this->student->id,
            'category_id' => $this->category->id,
            'type' => 'lost',
            'status' => 'open',
            'title' => 'Dompet Hitam Hilang',
            'description' => 'Hilang di kantin.',
            'building' => 'Food Junction',
            'floor_detail' => 'Lantai 1',
            'event_date' => now()->toDateString(),
        ]);

        // Pelapor dapat mengedit laporan sendiri selama belum ada klaim (REP-04)
        $this->actingAs($this->student)
            ->get(route('items.create'))
            ->assertOk()
            ->assertSee('Create New Post');

        $this->actingAs($this->student)
            ->get(route('items.edit', $lostItem))
            ->assertOk()
            ->assertSee('Edit Post');

        $this->actingAs($this->student)
            ->put(route('items.update', $lostItem), [
                'category_id' => $this->category->id,
                'title' => 'Dompet Kulit Hitam Hilang',
                'description' => 'Hilang di kantin dekat kasir.',
                'building' => 'Food Junction',
                'floor_detail' => 'Lantai 1',
                'event_date' => now()->toDateString(),
                'tags' => 'dompet, hitam',
            ])
            ->assertRedirect(route('items.show', $lostItem));

        $this->assertEquals('Dompet Kulit Hitam Hilang', $lostItem->fresh()->title);

        // Pelapor dapat menutup laporan kehilangan sendiri (STA-04)
        $this->actingAs($this->student)
            ->post(route('items.close', $lostItem))
            ->assertRedirect(route('items.show', $lostItem));

        $this->assertEquals('returned', $lostItem->fresh()->status);

        // Setelah returned, laporan tidak bisa diedit lagi (STA-03)
        $this->actingAs($this->student)
            ->get(route('items.edit', $lostItem))
            ->assertForbidden();

        // Scope "mine" menampilkan seluruh post milik user termasuk returned; scope "archive" tersedia untuk security
        $this->actingAs($this->student)
            ->get('/dashboard?scope=mine')
            ->assertOk()
            ->assertSee('Dompet Kulit Hitam Hilang');

        $this->actingAs($this->security)
            ->get('/dashboard?scope=archive')
            ->assertOk()
            ->assertSee('Dompet Kulit Hitam Hilang');
    }

    public function test_user_can_take_down_own_post_as_cancelation_before_claims_exist(): void
    {
        $ownPost = Item::create([
            'user_id' => $this->student->id,
            'category_id' => $this->category->id,
            'type' => 'found',
            'status' => 'open',
            'title' => 'Posted By Mistake',
            'description' => 'Accidental duplicate post.',
            'building' => 'Building A',
            'floor_detail' => 'Floor 1',
            'event_date' => now()->toDateString(),
            'custody_location' => 'Pos Keamanan Gedung A',
            'deposit_note' => 'Pos A',
        ]);

        // Another user cannot take down someone else's post
        $this->actingAs($this->otherStudent)
            ->delete(route('items.destroy', $ownPost))
            ->assertForbidden();

        // Owner sees Cancel & Take Down Post button and can take it down before any claim exists
        $this->actingAs($this->student)
            ->get(route('items.show', $ownPost))
            ->assertOk()
            ->assertSee('Take Down Post');

        // Owner can still view their own post even if hidden, while other users get 404
        $hiddenOwnPost = Item::create([
            'user_id' => $this->student->id,
            'category_id' => $this->category->id,
            'type' => 'found',
            'status' => 'open',
            'title' => 'Hidden Own Post',
            'description' => 'Hidden after temporary block.',
            'building' => 'Food Junction',
            'floor_detail' => 'Floor 1',
            'event_date' => now()->toDateString(),
            'custody_location' => 'Pos Keamanan Gedung A',
            'deposit_note' => 'Pos A',
            'is_hidden' => true,
        ]);

        $this->actingAs($this->student)->get(route('items.show', $hiddenOwnPost))->assertOk()->assertSee('Hidden Own Post');
        $this->actingAs($this->otherStudent)->get(route('items.show', $hiddenOwnPost))->assertNotFound();

        $this->actingAs($this->student)
            ->delete(route('items.destroy', $ownPost))
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseMissing('items', ['id' => $ownPost->id]);
    }

    public function test_dashboard_sort_orders_by_event_date_and_most_discussed(): void
    {
        $olderEventItem = Item::create([
            'user_id' => $this->student->id,
            'category_id' => $this->category->id,
            'type' => 'found',
            'status' => 'open',
            'title' => 'Alpha Older Event Discussed',
            'description' => 'Item from 5 days ago with chat.',
            'building' => 'Building A',
            'floor_detail' => 'Floor 1',
            'event_date' => now()->subDays(5)->toDateString(),
            'custody_location' => 'Pos Keamanan Gedung A',
            'deposit_note' => 'Pos A',
        ]);

        $newerEventItem = Item::create([
            'user_id' => $this->otherStudent->id,
            'category_id' => $this->category->id,
            'type' => 'found',
            'status' => 'open',
            'title' => 'Beta Newer Event Quiet',
            'description' => 'Item from today with zero chat.',
            'building' => 'Building B',
            'floor_detail' => 'Floor 2',
            'event_date' => now()->toDateString(),
            'custody_location' => 'Pos Keamanan Gedung A',
            'deposit_note' => 'Pos A',
        ]);

        Message::create([
            'item_id' => $olderEventItem->id,
            'sender_id' => $this->otherStudent->id,
            'receiver_id' => $this->student->id,
            'message_body' => 'Asking about Alpha item',
        ]);

        $this->actingAs($this->student)
            ->get(route('dashboard', ['type' => 'found', 'sort' => 'event_desc']))
            ->assertOk()
            ->assertSeeInOrder(['Beta Newer Event Quiet', 'Alpha Older Event Discussed']);

        $this->actingAs($this->student)
            ->get(route('dashboard', ['type' => 'found', 'sort' => 'most_active']))
            ->assertOk()
            ->assertSeeInOrder(['Alpha Older Event Discussed', 'Beta Newer Event Quiet']);
    }
}
