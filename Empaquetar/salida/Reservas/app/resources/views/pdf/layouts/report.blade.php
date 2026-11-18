<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoTur - @yield('title')</title>
    <style>
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            margin: 10px;
            padding: 0;
            color: #333;
            font-size: 9px;
            line-height: 1.2;
        }

        .document-header {
            border-bottom: 2px solid #333;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }

        .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #333;
            margin: 0;
            text-transform: uppercase;
        }

        .system-name {
            font-size: 8px;
            color: #666;
            margin: 2px 0 8px 0;
            text-transform: uppercase;
        }

        .report-title {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            color: #333;
            margin: 8px 0;
            text-transform: uppercase;
        }

        .report-info {
            font-size: 8px;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2px;
        }

        .info-left {
            display: flex;
            gap: 15px;
        }

        .info-item {
            display: flex;
            align-items: center;
        }

        .info-label {
            font-weight: bold;
            margin-right: 5px;
        }

        .info-value {
            color: #333;
        }

        .page-info {
            font-weight: bold;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
            margin-top: 8px;
            border: none;
        }

        th {
            background: none;
            padding: 5px 4px;
            text-align: left;
            font-weight: bold;
            font-size: 8px;
        }

        .th-underline {
            display: inline-block;
            border-bottom: 1px solid #333;
            padding-bottom: 2px;
        }

        th:first-child {
            width: 60px;
            text-align: center;
        }

        th:last-child {
            text-align: center;
            width: 50px;
        }

        tbody tr:nth-child(even) {
            background: none;
        }

        td {
            border: none;
            padding: 4px 4px;
            font-size: 8px;
            vertical-align: top;
            background: none;
        }

        td:first-child {
            text-align: center;
            font-weight: bold;
            background: none;
        }

        td:last-child {
            text-align: center;
            font-size: 10px;
        }

        .status-active {
            color: #000;
            font-weight: bold;
        }

        .status-inactive {
            color: #666;
        }

        .footer {
            margin-top: 15px;
            font-size: 7px;
            color: #666;
            text-align: center;
            border-top: 1px solid #ccc;
            padding-top: 5px;
        }

        @page {
            margin: 15mm;
        }

        @yield('custom-styles')
    </style>
</head>

<body>
    <div class="document-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="vertical-align: top; text-align: left;">
                        <h1 class="company-name" style="margin-bottom: 2px;">Contratos TecnoTur</h1>
                        <p class="system-name" style="margin-top: 0;">Sistema de Gestión de Contratos</p>
                    </td>
                    <td style="vertical-align: top; text-align: right; width: 110px; padding-top: 0;">
                        @if(isset($entityLogo) && $entityLogo)
                        <img src="{{ public_path('storage/' . $entityLogo) }}" alt="Logo" style="max-width: 90px; max-height: 60px; display: inline-block; margin-top: 0;" />
                        @endif
                    </td>
                </tr>
            </table>
            <h2 class="report-title">@yield('report-title')</h2>
        </div>
    </div>

    <div class="report-info">
        <div class="info-row">
            <div class="info-left">
                <div class="info-item">
                    <span class="info-label">ENTIDAD:</span>
                    <span class="info-value">{{ $entityName }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">FECHA DE GENERACIÓN:</span>
                    <span class="info-value">{{ date('d/m/Y') }} - {{ date('h:i A') }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">TOTAL DE REGISTROS:</span>
                    <span class="info-value">{{ is_countable($datos) ? count($datos) : $datos->count() }}</span>
                </div>
                @yield('extra-info')
            </div>
            <div class="page-info">PÁGINA: {{ $currentPage }} de {{ $lastPage }}</div>
        </div>
    </div>

    @yield('content')

    <div class="footer">
        Documento generado por Sistema TecnoTur - {{ date('d/m/Y H:i') }}
    </div>
</body>

</html>
