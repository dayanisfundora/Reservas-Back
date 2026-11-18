@extends('pdf.layouts.report')

@section('title', 'Reporte de Provincias')

@section('report-title', 'Listado de Provincias')

@section('content')
<table>
        <thead>
            <tr>
                <th><span class="th-underline">Código</span></th>
                <th><span class="th-underline">Descripción</span></th>
                <th><span class="th-underline">Abreviatura</span></th>
                <th><span class="th-underline">Activo</span></th>
            </tr>
        </thead>
        <tbody>
            @php
            $ordenarDatos = $datos->sortBy(function ($dato) {
            return [
            $dato->state == 1 ? 0 : 1,
            $dato->code
            ];
            });
            @endphp

            @foreach($ordenarDatos as $dato)
            <tr>
                <td>{{ $dato->code }}</td>
                <td>{{ $dato->description }}</td>
                <td>{{ $dato->abbr }}</td>
                <td>
                    @if($dato->state == 1)
                    <span class="status-active">✓</span>
                    @else
                    <span class="status-inactive">—</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
@endsection