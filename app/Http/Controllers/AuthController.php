<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $credentials['email'] = strtolower(trim($credentials['email']));

        // FR-01 & AC-04: Reject emails outside the allowed institutional domains
        if (! $this->isAllowedCampusEmail($credentials['email'])) {
            return back()
                ->withErrors(['email' => 'Login is restricted to official UPH campus email domains.'])
                ->onlyInput('email');
        }

        if (Auth::attempt($credentials)) {
            if (Auth::user()->is_blocked) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()
                    ->withErrors(['email' => 'Your account has been suspended by security. Please contact the security desk.'])
                    ->onlyInput('email');
            }

            $request->session()->regenerate(); // Prevent Session Fixation

            return redirect()->intended(route('dashboard'));
        }

        return back()
            ->withErrors(['email' => 'Invalid email or password.'])
            ->onlyInput('email');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $normalizedEmail = strtolower(trim((string) $request->input('email')));
        $request->merge(['email' => $normalizedEmail]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'unique:users,email',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $this->isAllowedCampusEmail((string) $value)) {
                        $fail('Registration is only allowed with an official campus email (@student.uph.edu or @uph.edu).');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'user',
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Welcome to Lostracer UPH!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'You have been signed out.');
    }

    /**
     * Memeriksa apakah domain email terdaftar di config/lostracer.php (PRV-06).
     */
    private function isAllowedCampusEmail(string $email): bool
    {
        $atPos = strrpos($email, '@');
        if ($atPos === false) {
            return false;
        }

        $domain = strtolower(substr($email, $atPos + 1));

        return in_array($domain, config('lostracer.allowed_email_domains', []), true);
    }
}
