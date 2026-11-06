<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 12mm 10mm 16mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #0f172a;
            margin: 0;
        }
        .header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            border-bottom: 2px solid #0f766e;
        }
        .header td { vertical-align: bottom; padding: 0 0 6px; }
        .brand {
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #0f766e;
        }
        .title {
            font-size: 17px;
            font-weight: bold;
            color: #0f172a;
            margin-top: 2px;
        }
        .meta {
            text-align: right;
            font-size: 8px;
            color: #64748b;
            line-height: 1.45;
        }
        .meta strong { color: #334155; }

        .kpis {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin: 0 -6px 10px;
        }
        .kpi {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 6px 8px;
            background: #f8fafc;
        }
        .kpi-label {
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #64748b;
        }
        .kpi-value {
            font-size: 14px;
            font-weight: bold;
            color: #0f766e;
        }

        .section-title {
            margin: 12px 0 4px;
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
        }
        .section-sub {
            font-weight: normal;
            font-size: 8px;
            color: #64748b;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }
        table.data th {
            background: #0f766e;
            color: #fff;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            padding: 4px 5px;
            text-align: left;
            border: 1px solid #0d9488;
        }
        table.data td {
            border: 1px solid #cbd5e1;
            padding: 3px 5px;
        }
        table.data tr:nth-child(even) td { background: #f8fafc; }
        table.data .num { text-align: right; white-space: nowrap; }
        table.data tr.total td {
            background: #ecfdf5;
            font-weight: bold;
            border-top: 2px solid #0f766e;
        }
        .empty {
            padding: 14px;
            text-align: center;
            color: #64748b;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
        }
        .footer {
            position: fixed;
            bottom: -10mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7.5px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    @php
        $fmtFecha = fn (?string $fecha) => $fecha ? \Carbon\Carbon::parse($fecha)->format('d/m/Y') : '—';
        $fmtNum = fn ($valor) => number_format((float) $valor, 0, ',', '.');
        $fmtPct = fn ($valor) => number_format((float) $valor, 1, ',', '.') . '%';
    @endphp

    <div class="footer">Sistema desarrollado por DoubLe Team · Generado el {{ $generatedAt }}</div>

    <table class="header">
        <tr>
            <td>
                <div class="brand">Reservas · Reportes</div>
                <div class="title">{{ $titulo }}</div>
            </td>
            <td class="meta">
                <div><strong>Período:</strong> {{ $data['periodo'] }}</div>
                <div><strong>Casa:</strong> {{ $data['casa'] }}</div>
                @if (! empty($data['agencia_filtro']))
                    <div><strong>Agencia:</strong> {{ $data['agencia_filtro'] }}</div>
                @endif
                <div><strong>Generado:</strong> {{ $generatedAt }}</div>
                <div>Solo reservas confirmadas</div>
            </td>
        </tr>
    </table>

    @if ($tipo === 'clientes_agencia')
        <table class="kpis">
            <tr>
                <td class="kpi"><div class="kpi-label">Reservas</div><div class="kpi-value">{{ $fmtNum($data['totales']['reservas']) }}</div></td>
                <td class="kpi"><div class="kpi-label">Personas</div><div class="kpi-value">{{ $fmtNum($data['totales']['personas']) }}</div></td>
                <td class="kpi"><div class="kpi-label">Agencias</div><div class="kpi-value">{{ $fmtNum($data['totales']['agencias']) }}</div></td>
            </tr>
        </table>

        @forelse ($data['agencias'] as $grupo)
            <div class="section-title">
                {{ $grupo['agencia'] }}
                <span class="section-sub">· {{ $grupo['reservas'] }} reservas · {{ $grupo['personas'] }} personas</span>
            </div>
            <table class="data">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Casa</th>
                        <th>Habitación</th>
                        <th>Entrada</th>
                        <th>Salida</th>
                        <th class="num">Noches</th>
                        <th class="num">Personas</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grupo['clientes'] as $cliente)
                        <tr>
                            <td>{{ $cliente['cliente_nombre'] }}</td>
                            <td>{{ $cliente['casa_nombre'] }}</td>
                            <td>{{ $cliente['habitacion_nombre'] }}</td>
                            <td>{{ $fmtFecha($cliente['fecha_entrada']) }}</td>
                            <td>{{ $fmtFecha($cliente['fecha_salida']) }}</td>
                            <td class="num">{{ $cliente['noches'] }}</td>
                            <td class="num">{{ $cliente['personas'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @empty
            <div class="empty">No hay reservas confirmadas con llegada en este período.</div>
        @endforelse
    @elseif ($tipo === 'agencias_ocupacion')
        <table class="kpis">
            <tr>
                <td class="kpi"><div class="kpi-label">Reservas</div><div class="kpi-value">{{ $fmtNum($data['totales']['reservas']) }}</div></td>
                <td class="kpi"><div class="kpi-label">Personas</div><div class="kpi-value">{{ $fmtNum($data['totales']['personas']) }}</div></td>
                <td class="kpi"><div class="kpi-label">Noches-habitación</div><div class="kpi-value">{{ $fmtNum($data['totales']['noches']) }}</div></td>
                <td class="kpi"><div class="kpi-label">Porciento de ocupación</div><div class="kpi-value">{{ $fmtPct($data['totales']['ocupacion']) }}</div></td>
            </tr>
        </table>

        @forelse ($data['casas'] as $casa)
            <div class="section-title">
                {{ $casa['casa_nombre'] }}
                <span class="section-sub">· {{ $casa['habitaciones'] }} habitaciones · {{ $fmtNum($casa['total']['disponibles']) }} noches disponibles</span>
            </div>
            <table class="data">
                <thead>
                    <tr>
                        <th>Agencia</th>
                        <th class="num">Reservas</th>
                        <th class="num">Personas</th>
                        <th class="num">Noches-habitación</th>
                        <th class="num">Porciento de ocupación</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($casa['agencias'] as $fila)
                        <tr>
                            <td>{{ $fila['agencia'] }}</td>
                            <td class="num">{{ $fmtNum($fila['reservas']) }}</td>
                            <td class="num">{{ $fmtNum($fila['personas']) }}</td>
                            <td class="num">{{ $fmtNum($fila['noches']) }}</td>
                            <td class="num">{{ $fmtPct($fila['ocupacion']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5">Sin reservas confirmadas en este período.</td></tr>
                    @endforelse
                    <tr class="total">
                        <td>Total de la casa</td>
                        <td class="num">{{ $fmtNum($casa['total']['reservas']) }}</td>
                        <td class="num">{{ $fmtNum($casa['total']['personas']) }}</td>
                        <td class="num">{{ $fmtNum($casa['total']['noches']) }}</td>
                        <td class="num">{{ $fmtPct($casa['total']['ocupacion']) }}</td>
                    </tr>
                </tbody>
            </table>
        @empty
            <div class="empty">No hay casas activas para este filtro.</div>
        @endforelse
    @else
        <table class="kpis">
            <tr>
                <td class="kpi"><div class="kpi-label">Reservas</div><div class="kpi-value">{{ $fmtNum($data['totales']['reservas']) }}</div></td>
                <td class="kpi"><div class="kpi-label">Personas</div><div class="kpi-value">{{ $fmtNum($data['totales']['personas']) }}</div></td>
                <td class="kpi"><div class="kpi-label">Habitaciones</div><div class="kpi-value">{{ $fmtNum($data['habitaciones']) }}</div></td>
                <td class="kpi"><div class="kpi-label">Porciento de ocupación</div><div class="kpi-value">{{ $fmtPct($data['totales']['ocupacion']) }}</div></td>
            </tr>
        </table>

        <table class="data">
            <thead>
                <tr>
                    <th>Mes</th>
                    <th class="num">Reservas</th>
                    <th class="num">Personas</th>
                    <th class="num">Noches ocupadas</th>
                    <th class="num">Noches disponibles</th>
                    <th class="num">Porciento de ocupación</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($data['meses'] as $mes)
                    <tr>
                        <td>{{ $mes['nombre'] }}</td>
                        <td class="num">{{ $fmtNum($mes['reservas']) }}</td>
                        <td class="num">{{ $fmtNum($mes['personas']) }}</td>
                        <td class="num">{{ $fmtNum($mes['noches']) }}</td>
                        <td class="num">{{ $fmtNum($mes['disponibles']) }}</td>
                        <td class="num">{{ $fmtPct($mes['ocupacion']) }}</td>
                    </tr>
                @endforeach
                <tr class="total">
                    <td>Total del año</td>
                    <td class="num">{{ $fmtNum($data['totales']['reservas']) }}</td>
                    <td class="num">{{ $fmtNum($data['totales']['personas']) }}</td>
                    <td class="num">{{ $fmtNum($data['totales']['noches']) }}</td>
                    <td class="num">{{ $fmtNum($data['totales']['disponibles']) }}</td>
                    <td class="num">{{ $fmtPct($data['totales']['ocupacion']) }}</td>
                </tr>
            </tbody>
        </table>
    @endif
</body>
</html>
