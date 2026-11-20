<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Claim;
use App\Models\Item;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MessagingModerationAndSchedulerTest extends TestCase
{
    use RefreshDatabase;

    public function test_prv02_messages_are_encrypted_at_rest_and_readonly_when_returned(): void
    {
        $user1 = User::create([
            'name' => 'Penemu',
            'email' => 'u1@student.uph.edu',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);
        $user2 = User::create([
            'name' => 'Pemilik',
            'email' => 'u2@student.uph.edu',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);
        $cat = Category::create(['name' => 'Umum']);

        $item = Item::create([
            'user_id' => $user1->id,
            'category_id' => $cat->id,
            'type' => 'found',
            'status' => 'open',
            'title' => 'Payung Hitam',
            'description' => 'Payung lipat hitam.',
            'building' => 'Building A',
            'floor_detail' => 'Lantai 1',
            'event_date' => now()->toDateString(),
            'custody_location' => 'Lobby Gedung A',
            'deposit_note' => 'Lobby A',
        ]);

        $secretText = 'Saya sudah menitipkan payungnya di meja lobby Gedung A.';

        $this->actingAs($user2)->post(route('items.messages.store', $item), [
            'receiver_id' => $user1->id,
            'message_body' => $secretText,
        ])->assertRedirect(route('items.show', ['item' => $item, 'chat_peer' => $user1->id]));

        $rawDbValue = DB::table('messages')->value('message_body');
        $this->assertNotEquals($secretText, $rawDbValue);
        $this->assertEquals($secretText, Message::first()->message_body);

        // Chat workspace (/messages) displays thread and marks incoming message as read
        $this->actingAs($user1)
            ->get(route('messages.index', ['item' => $item->id]))
            ->assertOk()
            ->assertSee('Chat')
            ->assertSee($secretText);
        $this->assertTrue(Message::first()->is_read);

        // HND-04: Setelah returned, pesan baru ditolak
        $item->update(['status' => 'returned']);
        $this->actingAs($user2)->post(route('items.messages.store', $item), [
            'receiver_id' => $user1->id,
            'message_body' => 'Pesan setelah selesai',
        ])->assertSessionHas('error');
    }

    public function test_two_users_messaging_security_on_same_post_keep_separate_threads(): void
    {
        $security = User::create([
            'name' => 'Petugas Keamanan UPH',
            'email' => 'security@uph.edu',
            'password' => Hash::make('password'),
            'role' => 'security',
        ]);
        $kessie = User::create([
            'name' => 'Kessie',
            'email' => 'kessie@student.uph.edu',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);
        $alvaro = User::create([
            'name' => 'Alvaro Rahardjo',
            'email' => 'alvaro@student.uph.edu',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);
        $cat = Category::create(['name' => 'Elektronik']);

        $item = Item::create([
            'user_id' => $security->id,
            'category_id' => $cat->id,
            'type' => 'found',
            'status' => 'open',
            'title' => 'Charger USB-C Laptop 65W',
            'description' => 'Disimpan di rak gudang.',
            'building' => 'Perpustakaan UPH',
            'floor_detail' => 'Lantai 2',
            'event_date' => now()->toDateString(),
            'custody_location' => 'Pos Keamanan Utama (Main Gate)',
            'deposit_note' => 'Pos Utama',
        ]);

        $this->actingAs($kessie)->post(route('items.messages.store', $item), [
            'receiver_id' => $security->id,
            'message_body' => 'Hey, this charger belongs to Kessie',
        ]);

        $this->actingAs($alvaro)->post(route('items.messages.store', $item), [
            'receiver_id' => $security->id,
            'message_body' => 'Hey, this is Alvaro asking about the charger',
        ]);

        // In /messages, Security sees separate 1-on-1 threads and never mixed bubbles
        $this->actingAs($security)
            ->get(route('messages.index', ['item' => $item->id, 'peer' => $kessie->id]))
            ->assertOk()
            ->assertSee('Conversations (2)')
            ->assertSee('Hey, this charger belongs to Kessie')
            ->assertDontSee('Hey, this is Alvaro asking about the charger');

        $this->actingAs($security)
            ->get(route('messages.index', ['item' => $item->id, 'peer' => $alvaro->id]))
            ->assertOk()
            ->assertSee('Hey, this is Alvaro asking about the charger')
            ->assertDontSee('Hey, this charger belongs to Kessie');

        // In /items/{id}, Security also sees separated threads via chat_peer
        $this->actingAs($security)
            ->get(route('items.show', ['item' => $item->id, 'chat_peer' => $kessie->id]))
            ->assertOk()
            ->assertSee('Hey, this charger belongs to Kessie')
            ->assertDontSee('Hey, this is Alvaro asking about the charger');
    }

    public function test_live_chat_json_endpoints_isolate_threads_and_respect_roles_and_terminal_lock(): void
    {
        $security = User::create([
            'name' => 'Petugas Keamanan UPH',
            'email' => 'security@uph.edu',
            'password' => Hash::make('password'),
            'role' => 'security',
        ]);
        $studentA = User::create([
            'name' => 'Student A',
            'email' => 'studenta@student.uph.edu',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);
        $studentB = User::create([
            'name' => 'Student B',
            'email' => 'studentb@student.uph.edu',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);
        $admin = User::create([
            'name' => 'Admin IT',
            'email' => 'admin@uph.edu',
            'password' => Hash::make('password'),
            'role' => 'it_admin',
        ]);
        $cat = Category::create(['name' => 'Elektronik']);

        $item = Item::create([
            'user_id' => $security->id,
            'category_id' => $cat->id,
            'type' => 'found',
            'status' => 'open',
            'title' => 'Powerbank Putih',
            'description' => 'Disimpan di pos keamanan.',
            'building' => 'Building B',
            'floor_detail' => 'Lantai 1',
            'event_date' => now()->toDateString(),
            'custody_location' => 'Pos Keamanan Utama (Main Gate)',
            'deposit_note' => 'Pos Utama',
        ]);

        // IT Admin cannot poll or send messages (ROL-03)
        $this->actingAs($admin)
            ->getJson(route('messages.thread', ['item' => $item->id, 'peer' => $security->id]))
            ->assertForbidden();

        // Student A & Student B send JSON messages to security
        $resA = $this->actingAs($studentA)->postJson(route('items.messages.store', $item), [
            'receiver_id' => $security->id,
            'message_body' => 'Pesan rahasia Student A',
        ]);
        $resA->assertCreated()->assertJsonPath('message.body', 'Pesan rahasia Student A');
        $firstId = $resA->json('message.id');

        $this->actingAs($studentB)->postJson(route('items.messages.store', $item), [
            'receiver_id' => $security->id,
            'message_body' => 'Pesan rahasia Student B',
        ])->assertCreated();

        // Security polling Student A thread sees only Student A messages
        $poll = $this->actingAs($security)->getJson(route('messages.thread', [
            'item' => $item->id,
            'peer' => $studentA->id,
        ]));
        $poll->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'Pesan rahasia Student A')
            ->assertJsonPath('unread_total', 1); // Student B message still unread

        // Incremental polling with after_id skips already seen messages
        $this->actingAs($security)->getJson(route('messages.thread', [
            'item' => $item->id,
            'peer' => $studentA->id,
            'after_id' => $firstId,
        ]))->assertOk()->assertJsonCount(0, 'messages');

        // Hidden item thread returns 404 to regular user
        $item->update(['is_hidden' => true]);
        $this->actingAs($studentA)
            ->getJson(route('messages.thread', ['item' => $item->id, 'peer' => $security->id]))
            ->assertNotFound();

        // Terminal status locks JSON sending with 422
        $item->update(['is_hidden' => false, 'status' => 'returned']);
        $this->actingAs($studentA)->postJson(route('items.messages.store', $item), [
            'receiver_id' => $security->id,
            'message_body' => 'Sudah ditutup',
        ])->assertStatus(422);
    }

    public function test_mod03_blocking_user_hides_open_reports_withdraws_pending_claims_and_logs_audit(): void
    {
        $security = User::create([
            'name' => 'Security',
            'email' => 'security@uph.edu',
            'password' => Hash::make('password'),
            'role' => 'security',
        ]);
        $spammer = User::create([
            'name' => 'Spammer',
            'email' => 'spammer@student.uph.edu',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);
        $honestUser = User::create([
            'name' => 'Honest',
            'email' => 'honest@student.uph.edu',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);
        $cat = Category::create(['name' => 'Umum']);

        $spammerItem = Item::create([
            'user_id' => $spammer->id,
            'category_id' => $cat->id,
            'type' => 'lost',
            'status' => 'open',
            'title' => 'Laporan Spam',
            'description' => 'Konten spam',
            'building' => 'Building A',
            'floor_detail' => 'Lantai 1',
            'event_date' => now()->toDateString(),
        ]);

        $targetItem = Item::create([
            'user_id' => $honestUser->id,
            'category_id' => $cat->id,
            'type' => 'found',
            'status' => 'claimed',
            'title' => 'Dompet Asli',
            'description' => 'Dompet di kantin.',
            'building' => 'Food Junction',
            'floor_detail' => 'Meja 3',
            'event_date' => now()->toDateString(),
            'custody_location' => 'Pos Keamanan Utama (Main Gate)',
            'deposit_note' => 'Pos Utama',
        ]);

        $claim = Claim::create([
            'item_id' => $targetItem->id,
            'user_id' => $spammer->id,
            'proof_description' => 'Klaim palsu',
            'proof_file_path' => 'claims/fake.jpg',
            'status' => 'pending',
            'attempt_no' => 1,
        ]);

        $this->actingAs($security)->post(route('security.users.block', $spammer), [
            'reason' => 'Mengirim spam dan klaim palsu berulang.',
        ])->assertSessionHas('success');

        $this->assertTrue($spammer->fresh()->is_blocked);
        $this->assertTrue($spammerItem->fresh()->is_hidden);
        $this->assertEquals('withdrawn', $claim->fresh()->status);
        $this->assertEquals('open', $targetItem->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'USER_BLOCKED',
            'item_id' => null,
        ]);
    }

    public function test_fr13_daily_maintenance_lapses_old_approved_claims_and_expires_6_month_items(): void
    {
        $security = User::create([
            'name' => 'Security',
            'email' => 'security@uph.edu',
            'password' => Hash::make('password'),
            'role' => 'security',
        ]);
        $finder = User::create([
            'name' => 'Finder',
            'email' => 'finder@student.uph.edu',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);
        $claimant = User::create([
            'name' => 'Claimant',
            'email' => 'claimant@student.uph.edu',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);
        $cat = Category::create(['name' => 'Umum']);

        // Kasus 1: Klaim approved tidak diambil > 7 hari -> lapsed, barang kembali open
        $uncollectedItem = Item::create([
            'user_id' => $finder->id,
            'category_id' => $cat->id,
            'type' => 'found',
            'status' => 'claimed',
            'title' => 'Botol Minum Tertinggal',
            'description' => 'Botol biru.',
            'building' => 'Building B',
            'floor_detail' => 'Lantai 1',
            'event_date' => now()->subDays(15)->toDateString(),
            'custody_location' => 'Pos Keamanan Gedung B',
            'deposit_note' => 'Pos B',
            'expires_at' => now()->addMonths(5),
        ]);

        $lapsedClaim = Claim::create([
            'item_id' => $uncollectedItem->id,
            'user_id' => $claimant->id,
            'proof_description' => 'Bukti',
            'proof_file_path' => 'claims/b.jpg',
            'status' => 'approved',
            'verified_by' => $security->id,
            'decided_at' => now()->subDays(8),
            'pickup_code' => 'UPH-999999',
        ]);

        // Kasus 2: Barang melewati masa simpan 6 bulan -> expired + ITEM_EXPIRED
        $oldItem = Item::create([
            'user_id' => $finder->id,
            'category_id' => $cat->id,
            'type' => 'found',
            'status' => 'open',
            'title' => 'Topi Lama 6 Bulan',
            'description' => 'Topi hitam.',
            'building' => 'Building D',
            'floor_detail' => 'Lantai 1',
            'event_date' => now()->subMonths(6)->toDateString(),
            'custody_location' => 'Gudang Lost & Found — Rak Extended Shelf (Bulan 4-6)',
            'deposit_note' => 'Pos D',
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('lostracer:daily-maintenance')->assertSuccessful();

        $this->assertEquals('lapsed', $lapsedClaim->fresh()->status);
        $this->assertEquals('open', $uncollectedItem->fresh()->status);
        $this->assertEquals('expired', $oldItem->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'item_id' => $oldItem->id,
            'action' => 'ITEM_EXPIRED',
        ]);
    }

    public function test_seeder_and_audit_export_and_it_admin_system_health(): void
    {
        $this->seed();

        $security = User::where('email', 'security@uph.edu')->firstOrFail();
        $itAdmin = User::where('email', 'admin@uph.edu')->firstOrFail();

        $this->actingAs($security)
            ->get(route('audit.index', ['q' => 'Tumbler']))
            ->assertOk();

        $this->actingAs($security)
            ->get(route('security.users', ['status' => 'active', 'q' => 'kessie']))
            ->assertOk()
            ->assertSee('User Account Moderation')
            ->assertSee('kessie@student.uph.edu');

        // Branded 404 page renders cleanly
        $this->actingAs($security)
            ->get('/items/999999')
            ->assertNotFound()
            ->assertSee('Post or Page Not Found');

        $this->actingAs($security)
            ->get(route('claims.index'))
            ->assertOk()
            ->assertSee('Verification & Handover');

        $user = User::where('email', 'kessie@student.uph.edu')->firstOrFail();
        $this->actingAs($user)
            ->get(route('claims.index'))
            ->assertOk()
            ->assertSee('My Claims');

        $csvResponse = $this->actingAs($security)->get(route('audit.export'));
        $csvResponse->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csvResponse->headers->get('Content-Type'));

        $this->actingAs($itAdmin)
            ->get(route('admin.system'))
            ->assertOk()
            ->assertSee('student.uph.edu');
    }
}
