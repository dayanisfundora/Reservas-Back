@extends('pdf.layouts.report')

@section('title', 'Reporte de Monedas')

@section('report-title', 'Listado de Monedas')

@section('content')
<table>
    <thead>
        <tr>
            <th><span class="th-underline">Código</span></th>
            <th><span class="th-underline">Descripción</span></th>
            <th><span class="th-underline">Abreviatura</span></th>
            <th><span class="th-underline">Tasa</span></th>
            <th style="text-align: center;"><span class="th-underline">Moneda Base</span></th>
            <th><span class="th-underline">Activo</span></th>
        </tr>
    </thead>
    <tbody>
        @foreach($datos as $dato)
        <tr>
            <td>{{ $dato->code }}</td>
            <td>{{ $dato->description }}</td>
            <td>{{ $dato->abbr }}</td>
            <td>{{ $dato->rate }}</td>
            <td style="text-align: center;">
                @if($dato->base_currency == 1)
                <span class="{{ $dato->state == 1 ? 'status-active' : 'status-inactive' }}" style="font-size: 10px;">✓</span>
                @else
                <span class="status-inactive" style="font-size: 10px;">—</span>
                @endif
            </td>
            <td>
                @if($dato->state == 1)
                <span class="status-active" style="font-size: 10px;">✓</span>
                @else
                <span class="status-inactive" style="font-size: 10px;">—</span>
                @endif
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endsection