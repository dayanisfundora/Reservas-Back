<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoTur - Reporte de Trazas</title>
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
            font-size: 7px;
            margin-top: 8px;
            border: 1px solid #ddd;
        }

        th {
            background: none;
            padding: 6px 4px;
            text-align: center;
            font-weight: bold;
            font-size: 7px;
            border: 1px solid #ddd;
        }

        /* Ajustando anchos de columnas optimizados */
        th:nth-child(1) { width: 30px; }  /* ID */
        th:nth-child(2) { width: 70px; }  /* Usuario */
        th:nth-child(3) { width: 45px; }  /* Acción */
        th:nth-child(4) { width: 50px; }  /* Fecha */
        th:nth-child(5) { width: 40px; }  /* Hora */
        th:nth-child(6) { width: 55px; }  /* IP */
        th:nth-child(7) { text-align: left; }  /* Descripción */
        th:nth-child(8) { text-align: left; }  /* Tabla */

        tbody tr {
            border-bottom: 1px solid #e0e0e0;
        }

        tbody tr:nth-child(4n+1),
        tbody tr:nth-child(4n+2) {
            background: none;
        }

        td {
            border: 1px solid #e0e0e0;
            padding: 4px 3px;
            font-size: 7px;
            vertical-align: middle;
        }

        /* Estilos específicos para cada columna */
        td:nth-child(1) {
            text-align: center;
            font-weight: bold;
            color: #666;
            background: none;
        }

        td:nth-child(2) {
            text-align: left;
            padding-left: 5px;
            font-weight: 600;
        }

        td:nth-child(3) {
            text-align: center;
            font-weight: bold;
        }

        td:nth-child(4),
        td:nth-child(5) {
            text-align: center;
        }

        td:nth-child(6) {
            text-align: center;
            font-size: 6px;
            font-family: 'Courier New', monospace;
        }

        td:nth-child(7) {
            text-align: left;
            padding-left: 5px;
        }

            td:nth-child(8) {
                text-align: left;
                padding-left: 5px;
                border: 1px solid #e0e0e0;
            }

        .action-create {
            color: #2e7d32;
            background-color: #e8f5e9;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .action-update {
            color: #ed6c02;
            background-color: #fff3e0;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .action-delete {
            color: #d32f2f;
            background-color: #ffebee;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .footer {
            margin-top: 15px;
            font-size: 7px;
            color: #666;
            text-align: center;
            border-top: 1px solid #ccc;
            padding-top: 5px;
        }

        .attributes-row {
            background: none !important;
            border-left: 3px solid #2196f3;
        }

        .attributes-container {
            padding: 6px 10px;
            font-size: 6.5px;
            line-height: 1.4;
        }

        .attributes-title {
            font-weight: bold;
            margin-bottom: 4px;
            color: #1976d2;
            font-size: 7px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .attribute-item {
            margin-bottom: 3px;
            padding: 2px 4px;
            background-color: #fff;
            border-radius: 2px;
            display: flex;
            align-items: flex-start;
            gap: 5px;
        }

        .attribute-name {
            font-weight: bold;
            min-width: 80px;
            color: #424242;
            text-transform: capitalize;
        }

        .attribute-values {
            flex: 1;
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
        }

        .old-value {
            color: #c62828;
            background-color: #ffebee;
            padding: 1px 4px;
            border-radius: 2px;
            text-decoration: line-through;
        }

        .new-value {
            color: #2e7d32;
            background-color: #e8f5e9;
            padding: 1px 4px;
            border-radius: 2px;
            font-weight: bold;
        }

        .arrow {
            color: #757575;
            font-size: 8px;
            font-weight: bold;
        }

        .no-attributes {
            color: #757575;
            font-style: italic;
            text-align: center;
            padding: 5px;
        }

        @page {
            margin: 15mm;
        }
    </style>
</head>

<body>
    <div class="document-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
        <div>
            <table style="width: 100%; border-collapse: collapse; border: none;">
                <tr>
                    <td style="vertical-align: top; text-align: left; border: none; background: none;">
                        <h1 class="company-name" style="margin-bottom: 2px;">Contratos TecnoTur</h1>
                        <p class="system-name" style="margin-top: 0;">Sistema de Gestión de Contratos</p>
                    </td>
                    <td style="vertical-align: top; text-align: right; width: 110px; padding-top: 0; border: none;">
                        @if(isset($entityLogo) && $entityLogo)
                            <img src="{{ public_path('storage/' . $entityLogo) }}" alt="Logo" style="max-width: 90px; max-height: 60px; display: inline-block; margin-top: 0;" />
                        @endif
                    </td>
                </tr>
            </table>
            <h2 class="report-title">Listado de Trazas</h2>
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
            </div>
            <div class="page-info">PÁGINA: {{ $currentPage }} de {{ $lastPage }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Acción</th>
                <th>Fecha</th>
                <th>Hora</th>
                <th>IP</th>
                <th>Descripción</th>
                    <th>Tabla</th>
            </tr>
        </thead>
        <tbody>
            @php
            $ordenarDatos = $datos->sortByDesc('id');
            @endphp

            @foreach($ordenarDatos as $dato)
            <tr>
                <td>{{ $dato->id }}</td>
                <td>{{ optional($dato->causer)->name ?? 'Sistema' }}</td>
                <td>
                    <span class="action-{{ strtolower($dato->event) }}">
                        @if($dato->event === 'created')
                            Creado
                        @elseif($dato->event === 'updated')
                            Editado
                        @elseif($dato->event === 'deleted')
                            Eliminado
                        @else
                            {{ ucfirst($dato->event) }}
                        @endif
                    </span>
                </td>
                <td>{{ \Carbon\Carbon::parse($dato->created_at)->format('d/m/Y') }}</td>
                <td>{{ \Carbon\Carbon::parse($dato->created_at)->format('H:i:s') }}</td>
                <td>{{ $dato->properties['ip'] ?? 'N/A' }}</td>
                <td>{{ $dato->description }}</td>
                    <td>
                        @php
                            // Extraer solo el nombre del modelo sin namespace
                            $tabla = $dato->subject_type ? class_basename($dato->subject_type) : '';
                                $traducciones = [
                                    'Application' => 'Solicitud',
                                    'Bank' => 'Banco',
                                    'Brigade' => 'Brigada',
                                    'BuysDescription' => 'Descripción de Compra',
                                    'Chain' => 'Cadena',
                                    'ComercialActivities' => 'Actividades Comerciales',
                                    'ContratType' => 'Tipo de Contrato',
                                    'Contrat' => 'Contrato',
                                    'Currency' => 'Moneda',
                                    'CustomerSupplier' => 'Cliente / Proveedor',
                                    'DocumentEvaluation' => 'Evaluación de Documento',
                                    'Entity' => 'Entidad',
                                    'EquipmentType' => 'Tipo de Equipo',
                                    'Family' => 'Familia',
                                    'Line' => 'Línea',
                                    'MaintenanceEquipment' => 'Equipo de Mantenimiento',
                                    'ProductService' => 'Producto / Servicio',
                                    'Province' => 'Provincia',
                                    'QualityEvaluation' => 'Evaluación de Calidad',
                                    'ServiceType' => 'Tipo de Servicio',
                                    'State' => 'Estado',
                                    'SupplierAnnexes' => 'Anexos de Proveedores',
                                    'SupplierType' => 'Tipo de Proveedor',
                                    'User' => 'Usuario',
                                    'MaintenanceContract' => 'Contrato de Mantenimiento Preventivo',
                                    'OrderStatus' => 'Estado de Orden de Compra',
                                    'ContractAbroad' => 'Contrato al Exterior',
                                    'Parts' => 'Partes y Piezas',
                                    'PurchaseOrder' => 'Orden de Compra',
                                    'ApplicationsAbroad' => 'Solicitud al Exterior',
                                ];
                                $tabla_es = $traducciones[$tabla] ?? $tabla;
                        @endphp
                                {{ $tabla_es }}
                    </td>
            </tr>

            @php
                $oldAttrs = [];
                $newAttrs = [];
                
                if (isset($dato->properties)) {
                    $props = is_object($dato->properties) ? (array)$dato->properties : $dato->properties;
                    $oldAttrs = isset($props['old']) && is_array($props['old']) ? $props['old'] : (isset($props['old']) && is_object($props['old']) ? (array)$props['old'] : []);
                    $newAttrs = isset($props['attributes']) && is_array($props['attributes']) ? $props['attributes'] : (isset($props['attributes']) && is_object($props['attributes']) ? (array)$props['attributes'] : []);
                }
            @endphp
            @if((count($oldAttrs) > 0) || (count($newAttrs) > 0))
            <tr class="attributes-row">
                 <td colspan="8">
                    <div class="attributes-container">
                        <div class="attributes-title">
                            @if($dato->event === 'created')
                                ✓ Registro creado con los siguientes valores:
                            @elseif($dato->event === 'updated')
                                ⚡ Cambios realizados:
                            @elseif($dato->event === 'deleted')
                                ✗ Registro eliminado con los siguientes datos:
                            @else
                                Atributos:
                            @endif
                        </div>

                        @php
                        // $oldAttrs y $newAttrs ya definidos arriba
                        $allKeys = array_unique(array_merge(array_keys($oldAttrs), array_keys($newAttrs)));
                        // Filtrar campos que no queremos mostrar
                        $excludeKeys = ['updated_at', 'password', 'remember_token'];
                        $allKeys = array_diff($allKeys, $excludeKeys);
                        @endphp

                        @if(count($allKeys) > 0)
                            @foreach($allKeys as $key)
                            <div class="attribute-item">
                                <span class="attribute-name">{{ ucwords(str_replace('_', ' ', $key)) }}:</span>
                                <div class="attribute-values">
                                    @if(isset($oldAttrs[$key]) && $dato->event === 'updated')
                                        <span class="old-value">{{ is_bool($oldAttrs[$key]) ? ($oldAttrs[$key] ? 'Sí' : 'No') : ($oldAttrs[$key] ?: 'vacío') }}</span>
                                    @endif

                                    @if(isset($oldAttrs[$key]) && isset($newAttrs[$key]) && $dato->event === 'updated')
                                        <span class="arrow">→</span>
                                    @endif

                                    @if(isset($newAttrs[$key]))
                                        <span class="new-value">{{ is_bool($newAttrs[$key]) ? ($newAttrs[$key] ? 'Sí' : 'No') : ($newAttrs[$key] ?: 'vacío') }}</span>
                                    @elseif(isset($oldAttrs[$key]) && $dato->event === 'deleted')
                                        <span class="old-value">{{ is_bool($oldAttrs[$key]) ? ($oldAttrs[$key] ? 'Sí' : 'No') : ($oldAttrs[$key] ?: 'vacío') }}</span>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        @else
                            <div class="no-attributes">No hay cambios de atributos registrados</div>
                        @endif
                    </div>
                </td>
            </tr>
            @endif
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Documento generado por Sistema TecnoTur - {{ date('d/m/Y H:i') }}
    </div>
</body>

</html>