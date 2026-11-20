<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAndRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_root_and_auth_pages_render(): void
    {
        $this->get('/login')->assertOk()->assertSee('Sign In to Lostracer');
        $this->get('/register')->assertOk()->assertSee('Create Lostracer Account');
    }

    public function test_ac04_non_campus_email_is_rejected_on_register_and_login(): void
    {
        $registerResponse = $this->post('/register', [
            'name' => 'Pengguna Luar',
            'email' => 'outsider@gmail.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $registerResponse->assertSessionHasErrors('email');
        $this->assertDatabaseMissing('users', ['email' => 'outsider@gmail.com']);

        $loginResponse = $this->post('/login', [
            'email' => 'outsider@gmail.com',
            'password' => 'password',
        ]);

        $loginResponse->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_campus_student_can_register_and_login(): void
    {
        $response = $this->post('/register', [
            'name' => 'Kessie',
            'email' => 'kessie@student.uph.edu',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'kessie@student.uph.edu',
            'role' => 'user',
        ]);
    }

    public function test_blocked_user_is_denied_login_and_logged_out_if_blocked_mid_session(): void
    {
        $user = User::create([
            'name' => 'Spammer',
            'email' => 'spam@student.uph.edu',
            'password' => Hash::make('password'),
            'role' => 'user',
            'is_blocked' => true,
        ]);

        $this->post('/login', [
            'email' => 'spam@student.uph.edu',
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();

        // Simulasi akun diblokir saat masih punya sesi aktif (MOD-03)
        $user->update(['is_blocked' => false]);
        $this->actingAs($user);
        $user->update(['is_blocked' => true]);

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_rol03_it_admin_cannot_access_user_reports_claims_or_security_pages(): void
    {
        $admin = User::create([
            'name' => 'Admin IT',
            'email' => 'admin@uph.edu',
            'password' => Hash::make('password'),
            'role' => 'it_admin',
        ]);

        $this->actingAs($admin)->get('/admin/system')->assertOk();
        $this->actingAs($admin)->get('/items/create')->assertForbidden();
        $this->actingAs($admin)->get('/messages')->assertForbidden();
        $this->actingAs($admin)->get('/claims')->assertForbidden();
        $this->actingAs($admin)->get('/audit-logs')->assertForbidden();
        $this->actingAs($admin)->get('/security/users')->assertForbidden();
    }
}
