<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Models\Empresa;
use App\Models\User;
use App\Support\EmpresaAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate(['name' => ['required', 'string'], 'password' => ['required', 'string']]);
        $login = mb_strtolower(trim($validated['name']));

        $user = User::query()
            ->where(fn ($q) => $q->whereRaw('LOWER(name) = ?', [$login])->orWhereRaw('LOWER(username) = ?', [$login])->orWhereRaw('LOWER(email) = ?', [$login]))
            ->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['name' => ['Usuario o contraseña incorrectos.']]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['name' => ['El usuario está inactivo. Contacte al administrador.']]);
        }

        if (EmpresaAccess::accessibleFor($user)->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Su usuario no tiene empresas asignadas. Contacte al administrador.'], 403);
        }

        $token = $user->createToken('api-token')->plainTextToken;
        $user->load('roles');

        return response()->json(['success' => true, 'message' => 'Login successful', 'data' => $this->buildAuthPayload($user, $token)]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->buildAuthPayload($request->user()->load('roles'))]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (! empty($validated['password']) && ! Hash::check((string) ($validated['current_password'] ?? ''), $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['La contraseña actual no es correcta.']]);
        }

        $user->fill(Arr::only($validated, ['name', 'username', 'email']));

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return response()->json(['success' => true, 'message' => 'Perfil actualizado correctamente.', 'data' => $this->buildAuthPayload($user->fresh('roles'))]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['success' => true, 'message' => 'Logout successful']);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email']]);
        $email = Str::lower(trim($validated['email']));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user) {
            $token = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(['email' => $user->email], ['token' => Hash::make($token), 'created_at' => now()]);
            $resetUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:9000')), '/') . '/reset-password?token=' . urlencode($token) . '&email=' . urlencode($user->email);
            Mail::raw("Hola {$user->name},\n\nAbre este enlace para restablecer tu contraseña:\n\n{$resetUrl}\n\nEl enlace vence en 60 minutos.", fn ($message) => $message->to($user->email)->subject('Restablecer contraseña'));
        }

        return response()->json(['success' => true, 'message' => 'Si el correo está registrado, recibirás un enlace para restablecer la contraseña.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email'], 'token' => ['required', 'string'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
        $email = Str::lower(trim($validated['email']));
        $reset = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (! $reset || ! Hash::check($validated['token'], $reset->token) || now()->diffInMinutes($reset->created_at) > 60) {
            return response()->json(['success' => false, 'message' => 'El enlace no es válido o ya expiró. Solicita uno nuevo.'], 422);
        }

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) {
            return response()->json(['success' => false, 'message' => 'El enlace no es válido o ya expiró. Solicita uno nuevo.'], 422);
        }

        $user->password = $validated['password'];
        $user->save();
        DB::table('password_reset_tokens')->where('email', $email)->delete();

        return response()->json(['success' => true, 'message' => 'Contraseña actualizada correctamente. Ya puedes iniciar sesión.']);
    }

    private function buildAuthPayload(User $user, ?string $token = null): array
    {
        $payload = [
            'user' => ['id' => $user->id, 'name' => $user->name, 'username' => $user->username, 'email' => $user->email, 'roles' => $user->roles->pluck('name')->values()],
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'empresas' => EmpresaAccess::accessibleFor($user)->map(fn (Empresa $empresa) => $empresa->toSummaryArray())->values(),
        ];

        if ($token !== null) {
            $payload['token'] = $token;
        }

        return $payload;
    }
}
