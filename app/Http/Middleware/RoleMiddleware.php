<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Memastikan pengguna yang login memiliki salah satu peran yang diizinkan.
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check() || ! Auth::user()->hasRole($roles)) {
            abort(403, 'Anda tidak memiliki hak akses untuk fitur ini.');
        }

        return $next($request);
    }
}
