<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Token tidak valid atau tidak disediakan.',
                ]
            ], 401);
        }

        // Check if token has the role ability
        if (!$user->tokenCan('role:' . $role)) {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Akses ditolak untuk peran ini.',
                ]
            ], 403);
        }

        return $next($request);
    }
}
