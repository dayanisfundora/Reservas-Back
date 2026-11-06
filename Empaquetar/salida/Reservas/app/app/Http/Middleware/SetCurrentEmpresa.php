<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\CurrentEmpresa;
use App\Support\EmpresaAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentEmpresa
{
    /** @var list<string> */
    private array $exemptPatterns = ['api/auth/*', 'api/empresas', 'api/empresas/*'];

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->isExempt($request)) {
            return $next($request);
        }

        $empresaId = $request->header('X-Empresa-Id');

        if ($empresaId === null || $empresaId === '') {
            return response()->json(['success' => false, 'message' => 'Debe seleccionar una empresa para continuar.'], 422);
        }

        if (! ctype_digit((string) $empresaId)) {
            return response()->json(['success' => false, 'message' => 'Identificador de empresa inválido.'], 422);
        }

        $user = $request->user();
        $empresaId = (int) $empresaId;

        if ($user === null || ! EmpresaAccess::userCanAccess($user, $empresaId)) {
            return response()->json(['success' => false, 'message' => 'No tiene acceso a la empresa seleccionada.'], 403);
        }

        CurrentEmpresa::set($empresaId);

        return $next($request);
    }

    private function isExempt(Request $request): bool
    {
        foreach ($this->exemptPatterns as $pattern) {
            if ($request->is($pattern)) {
                return true;
            }
        }

        return false;
    }
}
