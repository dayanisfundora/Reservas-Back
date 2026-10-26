<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Calendario de reservas</title>
    <style>
        @page { margin: 12mm 10mm; }
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
            padding-bottom: 6px;
        }
        .header td { vertical-align: bottom; padding: 0; }
        .brand {
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #0f766e;
        }
        .title {
            font-size: 18px;
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

        .legend {
            margin: 0 0 8px;
            font-size: 8px;
            color: #475569;
        }
        .legend-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 3px;
            vertical-align: middle;
        }
        .legend-item {
            display: inline-block;
            margin-right: 10px;
            margin-bottom: 3px;
        }

        /* Monthly calendar grid */
        .cal {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .cal th {
            background: #0f766e;
            color: #fff;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 5px 3px;
            text-align: center;
            border: 1px solid #0d9488;
        }
        .cal td {
            border: 1px solid #cbd5e1;
            vertical-align: top;
            height: 88px;
            padding: 3px;
            background: #fff;
        }
        .cal td.out {
            background: #f8fafc;
            color: #94a3b8;
        }
        .day-num {
            display: inline-block;
            font-size: 10px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .day-num.today {
            background: #0f766e;
            color: #fff;
            border-radius: 50%;
            width: 16px;
            height: 16px;
            line-height: 16px;
            text-align: center;
        }
        .pill {
            display: block;
            border-left: 3px solid #0f766e;
            background: #ecfdf5;
            border-radius: 3px;
            padding: 2px 3px;
            margin-bottom: 2px;
            font-size: 7px;
            line-height: 1.25;
            overflow: hidden;
        }
        .pill.pendiente {
            background: #fff7ed;
            border-left-color: #ea580c;
        }
        .pill-name { font-weight: bold; color: #0f172a; }
        .pill-meta { color: #475569; }
        .more {
            font-size: 7px;
            color: #0f766e;
            font-weight: bold;
            margin-top: 1px;
        }

        /* Day / list cards */
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #0f766e;
            margin: 8px 0 5px;
        }
        .card-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .card-table th {
            background: #f0fdfa;
            color: #0f766e;
            border: 1px solid #99f6e4;
            padding: 5px 6px;
            text-align: left;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .card-table td {
            border: 1px solid #e2e8f0;
            padding: 5px 6px;
            vertical-align: middle;
        }
        .card-table tr:nth-child(even) td { background: #f8fafc; }

        .chip {
            display: inline-block;
            padding: 1px 5px;
            border-radius: 8px;
            font-size: 7px;
            font-weight: bold;
            margin-right: 2px;
        }
        .ok { background: #dcfce7; color: #166534; }
        .warn { background: #ffedd5; color: #9a3412; }
        .pending { background: #ffedd5; color: #9a3412; }
        .confirmed { background: #ccfbf1; color: #0f766e; }
        .room-swatch {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 3px;
            vertical-align: middle;
        }

        /* Map / libro */
        .map {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .map th {
            background: #0f766e;
            color: #fff;
            font-size: 7px;
            padding: 4px 2px;
            text-align: center;
            border: 1px solid #0d9488;
        }
        .map th.corner {
            background: #134e4a;
            width: 78px;
            text-align: left;
            padding-left: 5px;
            font-size: 8px;
        }
        .map td {
            border: 1px solid #cbd5e1;
            vertical-align: top;
            padding: 2px;
            height: 42px;
            font-size: 6.5px;
            background: #fff;
        }
        .map td.room {
            background: #f0fdfa;
            font-weight: bold;
            font-size: 8px;
            color: #134e4a;
            width: 78px;
        }
        .map-pill {
            display: block;
            background: #ecfdf5;
            border-left: 2px solid #0f766e;
            border-radius: 2px;
            padding: 1px 2px;
            margin-bottom: 1px;
            line-height: 1.2;
        }
        .map-pill.pendiente {
            background: #fff7ed;
            border-left-color: #ea580c;
        }
        .empty { color: #cbd5e1; text-align: center; }
        .footer {
            margin-top: 8px;
            font-size: 7px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
        }
    </style>
</head>
<body>
@php
    $estadoLabel = fn ($e) => match ($e) {
        'pendiente' => 'Pendiente',
        'confirmada' => 'Confirmada',
        'cancelada' => 'Cancelada',
        default => $e ?: '—',
    };
    $estadoClass = fn ($e) => match ($e) {
        'pendiente' => 'pending',
        'confirmada' => 'confirmed',
        default => 'pending',
    };
    $weekdays = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
    $today = now()->format('Y-m-d');
@endphp

<table class="header">
    <tr>
        <td>
            <div class="brand">Reservas</div>
            <div class="title">
                @if($viewMode === 'mensual')
                    {{ ucfirst($monthLabel) }}
                @elseif($viewMode === 'dia')
                    {{ $selectedDate }}
                @else
                    Mapa · {{ ucfirst($monthLabel) }}
                @endif
            </div>
        </td>
        <td class="meta">
            <div><strong>Casa:</strong> {{ $casaNombre }}</div>
            <div><strong>Vista:</strong> {{ $titleView }}</div>
            <div><strong>Rango:</strong> {{ $desde }} → {{ $hasta }}</div>
            <div>Generado {{ $generatedAt }}</div>
        </td>
    </tr>
</table>

@if(!empty($habitaciones))
    <div class="legend">
        @foreach($habitaciones as $room)
            <span class="legend-item">
                <span class="legend-dot" style="background: {{ $room['color'] ?? '#0f766e' }};"></span>
                {{ $room['nombre'] ?? 'Habitación' }}
            </span>
        @endforeach
        &nbsp;&nbsp;|&nbsp;&nbsp;
        <span class="chip confirmed">Confirmada</span>
        <span class="chip pending">Pendiente</span>
        <span class="chip ok">Taxi L/S sí</span>
        <span class="chip warn">Taxi L/S no</span>
    </div>
@endif

@if($viewMode === 'mensual')
    {{-- Grilla tipo calendario --}}
    <table class="cal">
        <thead>
            <tr>
                @foreach($weekdays as $wd)
                    <th>{{ $wd }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($weeks as $week)
                <tr>
                    @foreach($week as $cell)
                        <td class="{{ $cell['in_month'] ? '' : 'out' }}">
                            <div class="day-num {{ $cell['fecha'] === $today ? 'today' : '' }}">
                                {{ $cell['day'] }}
                            </div>
                            @if($cell['in_month'])
                                @foreach(array_slice($cell['reservas'], 0, 4) as $r)
                                    @php $color = $r['habitacion_color'] ?? '#0f766e'; @endphp
                                    <div class="pill {{ ($r['estado'] ?? '') === 'pendiente' ? 'pendiente' : '' }}"
                                         style="border-left-color: {{ $color }}; background: {{ ($r['estado'] ?? '') === 'pendiente' ? '#fff7ed' : '#ecfdf5' }};">
                                        <div class="pill-name">
                                            {{ \Illuminate\Support\Str::limit($r['cliente_nombre'] ?? '—', 16) }}
                                        </div>
                                        <div class="pill-meta">
                                            {{ $r['hora_llegada'] ?? '—' }}
                                            · {{ \Illuminate\Support\Str::limit($r['habitacion_nombre'] ?? '', 10) }}
                                            · {{ $r['cantidad_personas'] ?? 0 }}p
                                        </div>
                                        <div class="pill-meta">
                                            L{{ !empty($r['taxi_llegada']) ? '✓' : '✗' }}
                                            S{{ !empty($r['taxi_salida']) ? '✓' : '✗' }}
                                            @if(($r['estado'] ?? '') === 'pendiente') · PEND @endif
                                        </div>
                                    </div>
                                @endforeach
                                @if(count($cell['reservas']) > 4)
                                    <div class="more">+{{ count($cell['reservas']) - 4 }} más</div>
                                @endif
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

@elseif($viewMode === 'dia')
    @php $rows = array_values($rowsByDay[$selectedDate] ?? []); @endphp
    <div class="section-title">Reservas del día</div>
    <table class="card-table">
        <thead>
            <tr>
                <th style="width: 18%;">Habitación</th>
                <th style="width: 24%;">Cliente</th>
                <th style="width: 10%;">Hora</th>
                <th style="width: 8%;">Pers.</th>
                <th style="width: 18%;">Taxi</th>
                <th style="width: 12%;">Estado</th>
                <th>Agencia</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $r)
                <tr>
                    <td>
                        <span class="room-swatch" style="background: {{ $r['habitacion_color'] ?? '#0f766e' }};"></span>
                        {{ $r['habitacion_nombre'] ?? '—' }}
                    </td>
                    <td><strong>{{ $r['cliente_nombre'] ?? '—' }}</strong></td>
                    <td>{{ $r['hora_llegada'] ?? '—' }}</td>
                    <td>{{ $r['cantidad_personas'] ?? '—' }}</td>
                    <td>
                        <span class="chip {{ !empty($r['taxi_llegada']) ? 'ok' : 'warn' }}">L {{ !empty($r['taxi_llegada']) ? 'Sí' : 'No' }}</span>
                        <span class="chip {{ !empty($r['taxi_salida']) ? 'ok' : 'warn' }}">S {{ !empty($r['taxi_salida']) ? 'Sí' : 'No' }}</span>
                    </td>
                    <td><span class="chip {{ $estadoClass($r['estado'] ?? '') }}">{{ $estadoLabel($r['estado'] ?? '') }}</span></td>
                    <td>{{ $r['agencia'] ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;color:#94a3b8;">Sin reservas para este día.</td></tr>
            @endforelse
        </tbody>
    </table>

@else
    {{-- Mapa: habitaciones × días --}}
    @php
        $days = array_keys($rowsByDay);
        if (empty($days)) {
            $cursor = \Carbon\Carbon::parse($desde);
            $end = \Carbon\Carbon::parse($hasta);
            while ($cursor->lte($end)) {
                $days[] = $cursor->format('Y-m-d');
                $cursor->addDay();
            }
        }
    @endphp
    <table class="map">
        <thead>
            <tr>
                <th class="corner">Habitación</th>
                @foreach($days as $day)
                    <th>
                        {{ \Carbon\Carbon::parse($day)->locale('es')->isoFormat('dd') }}<br>
                        {{ \Carbon\Carbon::parse($day)->format('d') }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($habitaciones as $room)
                <tr>
                    <td class="room">
                        <span class="room-swatch" style="background: {{ $room['color'] ?? '#0f766e' }};"></span>
                        {{ $room['nombre'] ?? 'Habitación' }}
                    </td>
                    @foreach($days as $day)
                        @php
                            $items = array_values(array_filter(
                                $rowsByDay[$day] ?? [],
                                fn ($x) => (int) ($x['habitacion_id'] ?? 0) === (int) ($room['id'] ?? -1)
                            ));
                        @endphp
                        <td>
                            @forelse($items as $r)
                                <div class="map-pill {{ ($r['estado'] ?? '') === 'pendiente' ? 'pendiente' : '' }}"
                                     style="border-left-color: {{ $room['color'] ?? '#0f766e' }};">
                                    <strong>{{ \Illuminate\Support\Str::limit($r['cliente_nombre'] ?? '', 10) }}</strong>
                                    {{ $r['cantidad_personas'] ?? 0 }}p
                                    · L{{ !empty($r['taxi_llegada']) ? '✓' : '✗' }}/S{{ !empty($r['taxi_salida']) ? '✓' : '✗' }}
                                </div>
                            @empty
                                <div class="empty">·</div>
                            @endforelse
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

<div class="footer">
    Impuestos1.0 · Calendario de reservas · {{ $casaNombre }} · {{ $titleView }}
</div>
</body>
</html>
