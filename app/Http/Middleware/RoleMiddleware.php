<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!Auth::check()) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Silakan login terlebih dahulu.',
                ], 401);
            }

            return redirect()->guest(route('login'))
                ->with('error', 'Sesi Anda belum aktif atau telah berakhir. Silakan login.');
        }

        $user = Auth::user();

        // If specific roles are specified, check if user has permission
        if (!empty($roles)) {
            if (!$user->hasRole($roles)) {
                $allowed = implode('/', array_map('strtoupper', $roles));
                $errMsg = "Akses ditolak. Fitur ini hanya dapat diakses oleh {$allowed}.";

                if ($request->expectsJson() || $request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => $errMsg,
                    ], 403);
                }

                abort(403, $errMsg);
            }
        }

        return $next($request);
    }
}