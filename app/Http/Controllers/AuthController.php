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
        $kredensial = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($kredensial)){
            if (Auth::user()->is_blocked){
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->withErrors(['email' => 'Akun anda dinonaktifkan oleh petugas. Silahkan hubungi pos keamanan']);
            }

            $request->session()->regenerate(); //Preventing Session Fixation
            return redirect()->intended('/dashboard');
        }

        return back()->withErrors(['email' => 'Email atau Password salah.'])->onlyInput('email');
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|max:100',
            'email' => [
                'required',
                'email',
                'unique::users, email',
                function ($attribute, $value, $fail){
                    if (!str_ends_with($value, '@uph.edu') && !str_ends_with($value, '@student.uph.edu')){
                        $fail('Pendaftaran hanya dapat menggunakan email resmi kampus.');
                    }
                },
            ],
            'password' => 'required|min:6|confirmed',
        ]);

        $user = User::created([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'user',
        ]);

        Auth::login($user);
        $request->session()->regenerate();
        return redirect('/dashboard')->with('success', 'Welcome to Lostracer!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login')->with('success', 'You have been Logout');
    }
}