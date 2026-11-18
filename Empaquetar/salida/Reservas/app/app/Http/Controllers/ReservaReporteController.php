<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\ReservaReporteService;
use App\Support\CurrentEmpresa;
use App\Support\MovementScopeFilter;
use Barryvdh\DomPDF\Facade\Pdf as PDF;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ReservaReporteController extends Controller
{
    public const TIPO_CLIENTES_AGENCIA = 'clientes_agencia';
    public const TIPO_AGENCIAS_OCUPACION = 'agencias_ocupacion';
    public const TIPO_ANUAL = 'anual';

    private const TITULOS = [
        self::TIPO_CLIENTES_AGENCIA => 'Clientes por agencia',
        self::TIPO_AGENCIAS_OCUPACION => 'Agencias y ocupación por casa',
        self::TIPO_ANUAL => 'Resumen anual de reservas y ocupación',
    ];

    public function __construct(
        private readonly ReservaReporteService $reportes,
    ) {}

    public function index(Request $request): JsonResponse
    {
        [$tipo, $data] = $this->build($request);

        return response()->json([
            'success' => true,
            'data' => ['tipo' => $tipo, 'titulo' => self::TITULOS[$tipo], ...$data],
        ]);
    }

    public function pdf(Request $request): Response
    {
        [$tipo, $data] = $this->build($request);

        $pdf = PDF::loadView('pdf.reservas_reporte', [
            'tipo' => $tipo,
            'titulo' => self::TITULOS[$tipo],
            'data' => $data,
            'generatedAt' => now()->format('d/m/Y H:i'),
        ])->setPaper('a4', $tipo === self::TIPO_AGENCIAS_OCUPACION ? 'landscape' : 'portrait');

        $slug = Str::slug((string) $data['periodo'], '_') ?: 'reporte';

        return $pdf->download("reporte_{$tipo}_{$slug}.pdf");
    }

    /**
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function build(Request $request): array
    {
        $validated = $request->validate([
            'tipo' => ['required', Rule::in(array_keys(self::TITULOS))],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'meses' => ['nullable', 'array', 'max:12', 'required_if:tipo,' . self::TIPO_CLIENTES_AGENCIA],
            'meses.*' => ['integer', 'min:1', 'max:12'],
            'casa_id' => ['nullable', 'integer'],
            'agencia' => ['nullable', 'string', 'max:120'],
        ], [
            'meses.required_if' => 'Seleccione al menos un mes.',
            'meses.*' => 'Los meses seleccionados no son válidos.',
            'tipo.in' => 'El tipo de reporte no es válido.',
        ]);

        $tipo = (string) $validated['tipo'];
        $anio = (int) $validated['anio'];
        $meses = array_map('intval', $validated['meses'] ?? []);
        $casaId = isset($validated['casa_id']) ? (int) $validated['casa_id'] : null;

        if ($casaId !== null) {
            $empresaId = CurrentEmpresa::id();
            if ($empresaId === null || ! MovementScopeFilter::validateCasaBelongsToEmpresa($casaId, $empresaId)) {
                throw ValidationException::withMessages([
                    'casa_id' => ['La casa seleccionada no es válida.'],
                ]);
            }
        }

        $data = match ($tipo) {
            self::TIPO_CLIENTES_AGENCIA => $this->reportes->clientesPorAgencia($anio, $meses, $casaId, $validated['agencia'] ?? null),
            self::TIPO_AGENCIAS_OCUPACION => $this->reportes->agenciasOcupacion($anio, $meses, $casaId),
            self::TIPO_ANUAL => $this->reportes->resumenAnual($anio, $casaId),
        };

        return [$tipo, $data];
    }
}
