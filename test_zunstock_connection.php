<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Agents\ZunstockInventoryAgent;

try {
    echo "Testing ZunstockLogistica connection...\n";
    echo "========================================\n\n";
    
    $agent = new ZunstockInventoryAgent();
    $result = $agent->get_inventory_with_stock();
    
    echo "✓ Connection successful!\n";
    echo "Total records: " . count($result) . "\n\n";
    
    if (count($result) > 0) {
        echo "First record:\n";
        echo "-------------\n";
        $first = $result[0];
        echo "Warehouse: {$first->cod_almacen} - {$first->desc_almacen}\n";
        echo "Section: {$first->cod_seccion} - {$first->desc_seccion}\n";
        echo "Article: {$first->cod_arti} - {$first->desc_arti_pv}\n";
        echo "Original Code: {$first->codigo_original}\n";
        echo "Stock: {$first->existencia} {$first->cod_umed}\n";
        echo "Price: {$first->precio_arti} {$first->cod_mone}\n";
        echo "Amount: {$first->importe_arti}\n";
    }
    
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Connection failed!\n";
}
