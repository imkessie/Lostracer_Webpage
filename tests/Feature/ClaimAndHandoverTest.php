<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Claim;
use App\Models\Item;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClaimAndHandoverTest extends TestCase
{
    use RefreshDatabase;

    private User $finder;

    private User $claimant;

    private User $otherClaimant;

    private User $security;

    private User $itAdmin;

    private Item $foundItem;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');

        $this->finder = User::create([
            'name' => 'Penemu',
            'email' => 'finder@student.uph.edu',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);

        $this->claimant = User::create([
            'name' => 'Pemilik Asli',
            'email' => 'owner@student.uph.edu',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);

        $this->otherClaimant = User::create([
            'name' => 'Pengklaim Lain',
            'email' => 'other@student.uph.edu',
            'password' => Hash::make('password'),
            'role' => 'user',
        ]);

        $this->security = User::create([
            'name' => 'Petugas Security',
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

        $category = Category::create(['name' => 'Elektronik & Gadget']);

        $this->foundItem = Item::create([
            'user_id' => $this->finder->id,
            'category_id' => $category->id,
            'type' => 'found',
            'status' => 'open',
            'title' => 'Earphone Sony WF-1000XM5',
            'description' => 'Ditemukan di meja baca.',
            'building' => 'Perpustakaan UPH',
            'floor_detail' => 'Lantai 2',
            'event_date' => now()->toDateString(),
            'custody_location' => 'Pos Keamanan Utama (Main Gate)',
            'deposit_note' => 'Pos Utama',
            'hidden_detail' => 'Case ada goresan di sisi kanan',
        ]);
    }

    public function test_ac01_ac05_claim_requires_proof_and_only_security_can_view_proof_file(): void
    {
        // Tanpa file bukti harus ditolak
        $this->actingAs($this->claimant)
            ->post(route('items.claim', $this->foundItem), [
                'proof_description' => 'Ini earphone milik saya yang hilang kemarin.',
            ])
            ->assertSessionHasErrors('proof_file');

        // Klaim valid dengan bukti
        $this->actingAs($this->claimant)
            ->post(route('items.claim', $this->foundItem), [
                'proof_description' => 'Nomor seri sesuai box dan ada goresan di sisi kanan.',
                'proof_file' => UploadedFile::fake()->image('bukti.jpg'),
            ])
            ->assertRedirect(route('claims.index'));

        $claim = Claim::first();
        $this->assertNotNull($claim);
        $this->assertEquals('pending', $claim->status);
        $this->assertEquals('claimed', $this->foundItem->fresh()->status);
        Storage::disk('local')->assertExists($claim->proof_file_path);

        // AC-05: User dan it_admin dilarang mengakses URL file bukti (HTTP 403)
        $this->actingAs($this->claimant)->get(route('claims.proof', $claim))->assertForbidden();
        $this->actingAs($this->otherClaimant)->get(route('claims.proof', $claim))->assertForbidden();
        $this->actingAs($this->itAdmin)->get(route('claims.proof', $claim))->assertForbidden();

        // Hanya security yang boleh mengakses file bukti
        $this->actingAs($this->security)->get(route('claims.proof', $claim))->assertOk();
    }

    public function test_sta05_ac03_hnd04_verify_and_handover_workflow_with_bast(): void
    {
        $proof1 = UploadedFile::fake()->image('p1.jpg')->store('claims', 'local');
        $proof2 = UploadedFile::fake()->image('p2.jpg')->store('claims', 'local');

        $claim1 = Claim::create([
            'item_id' => $this->foundItem->id,
            'user_id' => $this->claimant->id,
            'proof_description' => 'Bukti kuat',
            'proof_file_path' => $proof1,
            'status' => 'pending',
            'attempt_no' => 1,
        ]);

        $claim2 = Claim::create([
            'item_id' => $this->foundItem->id,
            'user_id' => $this->otherClaimant->id,
            'proof_description' => 'Bukti lain',
            'proof_file_path' => $proof2,
            'status' => 'pending',
            'attempt_no' => 1,
        ]);

        // Setujui claim1: status item tetap 'claimed' sampai serah-terima fisik (STA-05)
        $this->actingAs($this->security)
            ->post(route('claims.verify', $claim1), [
                'decision' => 'approve',
                'verification_notes' => 'Cocok dengan fisik barang.',
            ])
            ->assertSessionHas('success');

        $claim1->refresh();
        $this->assertEquals('approved', $claim1->status);
        $this->assertNotNull($claim1->pickup_code);
        $this->assertEquals('claimed', $this->foundItem->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'item_id' => $this->foundItem->id,
            'claim_id' => $claim1->id,
            'action' => 'CLAIM_APPROVED',
        ]);

        // AC-03: Kode pengambilan salah harus ditolak
        $this->actingAs($this->security)
            ->post(route('claims.handover', $claim1), [
                'pickup_code' => 'SALAH-123',
                'recipient_name' => 'Pemilik Asli',
                'handover_photo' => UploadedFile::fake()->image('handover.jpg'),
            ])
            ->assertSessionHasErrors('pickup_code');

        // AC-03 & HND-04: Kode valid menyelesaikan serah-terima, item -> returned, claim2 -> rejected
        $this->actingAs($this->security)
            ->post(route('claims.handover', $claim1), [
                'pickup_code' => $claim1->pickup_code,
                'recipient_name' => 'Pemilik Asli',
                'handover_photo' => UploadedFile::fake()->image('handover.jpg'),
                'notes' => 'KTM dicocokkan.',
            ])
            ->assertRedirect(route('claims.bast', $claim1));

        $this->assertEquals('returned', $this->foundItem->fresh()->status);
        $this->assertEquals('rejected', $claim2->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'item_id' => $this->foundItem->id,
            'claim_id' => $claim1->id,
            'action' => 'HANDOVER_COMPLETED',
        ]);

        // HND-05: Halaman BAST dapat diakses oleh security, dan terlihat di detail item beserta Item Audit Trail
        $this->actingAs($this->security)
            ->get(route('claims.bast', $claim1))
            ->assertOk()
            ->assertSee('OFFICIAL HANDOVER RELEASE SLIP (BAST)');

        $this->actingAs($this->security)
            ->get(route('items.show', $this->foundItem))
            ->assertOk()
            ->assertSee('Print BAST Slip')
            ->assertSee('Item Audit Trail')
            ->assertSee('HANDOVER_COMPLETED');

        // Role enforcement: user and it_admin cannot access handover, BAST, or audit logs
        $this->actingAs($this->claimant)->get(route('claims.bast', $claim1))->assertForbidden();
        $this->actingAs($this->itAdmin)->get(route('claims.bast', $claim1))->assertForbidden();
        $this->actingAs($this->claimant)->post(route('claims.handover', $claim1), [])->assertForbidden();
        $this->actingAs($this->claimant)->get(route('audit.index'))->assertForbidden();
    }

    public function test_clm07_max_two_claim_attempts_and_clm11_withdraw_reverts_item_to_open(): void
    {
        $this->actingAs($this->claimant)->post(route('items.claim', $this->foundItem), [
            'proof_description' => 'Percobaan pertama',
            'proof_file' => UploadedFile::fake()->image('b1.jpg'),
        ]);
        $c1 = Claim::first();

        // Tarik kembali klaim pending -> item kembali open (CLM-11)
        $this->actingAs($this->claimant)->post(route('claims.withdraw', $c1));
        $this->assertEquals('withdrawn', $c1->fresh()->status);
        $this->assertEquals('open', $this->foundItem->fresh()->status);

        // 2x ditolak memblokir percobaan ke-3 (CLM-07)
        for ($i = 1; $i <= 2; $i++) {
            $this->actingAs($this->claimant)->post(route('items.claim', $this->foundItem), [
                'proof_description' => 'Percobaan ke-'.$i,
                'proof_file' => UploadedFile::fake()->image("b{$i}.jpg"),
            ]);
            $pending = Claim::where('status', 'pending')->first();
            $this->actingAs($this->security)->post(route('claims.verify', $pending), [
                'decision' => 'reject',
                'verification_notes' => 'Bukti belum cukup.',
            ]);
        }

        $this->actingAs($this->claimant)->post(route('items.claim', $this->foundItem), [
            'proof_description' => 'Percobaan ketiga harus ditolak',
            'proof_file' => UploadedFile::fake()->image('b3.jpg'),
        ])->assertSessionHas('error');

        $this->assertEquals(3, Claim::where('user_id', $this->claimant->id)->count()); // 1 withdrawn + 2 rejected
    }
}
