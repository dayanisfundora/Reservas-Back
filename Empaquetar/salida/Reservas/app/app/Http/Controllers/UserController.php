<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    private const DEFAULT_BASIC_ROLE = 'Usuario Basico';

    /**
     * Display a listing of users with pagination.
     */
    public function index(Request $request)
    {
        try {
            $query = User::with(['roles', 'permissions']);
            // Search filter by name
            $search = $request->input('search');
            if ($search) {
                $query->where('name', 'like', "%{$search}%");
            }

            // Pagination
            $perPage = $request->input('per_page', 5);
            $states = $query->paginate($perPage);

            if ($states->isEmpty()) {
                return response()->json([
                    'data' => [],
                    'message' => 'No hay bancos registrados.'
                ], 200);
            }

            return response()->json([
                'data' => $states->items(),
                'meta' => [
                    'current_page' => $states->currentPage(),
                    'last_page' => $states->lastPage(),
                    'per_page' => $states->perPage(),
                    'total' => $states->total(),
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function indexWithoutPagination()
    {
        try {
            $users = User::with(['roles', 'permissions'])
                ->orderBy('name', 'asc')
                ->get();

            if ($users->isEmpty()) {
                return response()->json(['error' => 'No hay datos registrados'], 404);
            }

            return response()->json(['data' => $users], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username|max:255',
            'email' => 'required|email|unique:users,email|max:255',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Generate random password
        $randomPassword = bin2hex(random_bytes(4)); // 8 caracteres hex

        $user = User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($randomPassword),
            'is_active' => true,
        ]);

        // Send email with temporary password
        try {
            Mail::to($user->email)->send(new \App\Mail\UserWelcomeMail($user, $randomPassword));
        } catch (\Exception $e) {
            // Ignore mail failures and keep the user created.
        }

        // Assign roles if provided
        if ($request->has('roles')) {
            $roles = Role::whereIn('id', $request->roles)->get();
            $user->syncRoles($roles);
        } else {
            $default_role = Role::where('name', self::DEFAULT_BASIC_ROLE)
                ->where('guard_name', 'sanctum')
                ->first();

            if ($default_role) {
                $user->syncRoles([$default_role]);
            }
        }

        // Assign direct permissions if provided
        if ($request->has('permissions')) {
            $user->syncPermissions($request->permissions);
        }

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'data' => $user->load(['roles', 'permissions'])
        ], 201);
    }

    /**
     * Display the specified user.
     */
    public function show($id)
    {
        $user = User::with(['roles.permissions', 'permissions'])->find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'all_permissions' => $user->getAllPermissions()
            ]
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|max:255',
            'username' => 'string|max:255|unique:users,username,' . $id,
            'email' => 'email|max:255|unique:users,email,' . $id,
            'password' => 'nullable|string|min:8|confirmed',
            'roles' => 'array',
            'roles.*' => 'exists:roles,id',
            'permissions' => 'array',
            'permissions.*' => 'exists:permissions,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Update basic fields
        if ($request->has('name')) {
            $user->name = $request->name;
        }
        if ($request->has('username')) {
            $user->username = $request->username;
        }
        if ($request->has('email')) {
            $user->email = $request->email;
        }
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        // Update roles if provided
        if ($request->has('roles')) {
            $roles = Role::whereIn('id', $request->roles)->get();
            $user->syncRoles($roles);
        }

        // Update direct permissions if provided
        if ($request->has('permissions')) {
            $user->syncPermissions($request->permissions);
        }

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente',
            'data' => $user->load(['roles', 'permissions'])
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(Request $request, $id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        // Prevent deleting own user
        if ($request->user() && $request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes eliminar tu propio usuario'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado exitosamente'
        ]);
    }

    /**
     * Toggle user active status.
     */
    public function toggle_status($id)
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        // Add is_active field if it does not exist
        $user->is_active = !($user->is_active ?? true);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Estado del usuario actualizado',
            'data' => $user
        ]);
    }
}
