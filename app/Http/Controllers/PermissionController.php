<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\AdminOnlyPermissions;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = Permission::query()
            ->where('guard_name', 'sanctum')
            ->whereNotIn('name', AdminOnlyPermissions::NAMES)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json(['success' => true, 'data' => $permissions]);
    }
}
