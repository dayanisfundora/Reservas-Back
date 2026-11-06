<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        if (! $request->user()) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated'], 401);
        }

        $required = [];
        foreach ($permissions as $permission) {
            foreach (explode('|', $permission) as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $required[] = $part;
                }
            }
        }

        foreach ($required as $permission) {
            if ($request->user()->hasPermissionTo($permission)) {
                return $next($request);
            }
        }

        return response()->json(['success' => false, 'message' => 'Unauthorized. You do not have permission: ' . implode(' or ', $required)], 403);
    }
}
