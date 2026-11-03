<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Pengguna yang dinonaktifkan langsung kehilangan akses, termasuk token mobile yang masih ada. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            return response()->json(['message' => __('auth.inactive')], 403);
        }

        return $next($request);
    }
}
