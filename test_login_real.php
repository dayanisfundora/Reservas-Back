<?php

/**
 * Test script for login functionality using real database configuration
 * 
 * Run this script from terminal: php test_login_real.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Operador;
use App\Models\JuegoDato;
use App\Models\Dato;
use App\Models\Acceso;
use Illuminate\Support\Facades\Hash;

echo "\n=== REAL LOGIN TEST ===\n";
echo "Using database: " . config('database.connections.mysql.database') . "\n";
echo "Using IHSecurity: " . config('database.connections.ihsecurity.database') . "\n\n";

// Test credentials
$username = 'lee';
$password = 'SuperadminSA*2.0';
$id_juego = 3;

echo "Testing login with:\n";
echo "  - Username: {$username}\n";
echo "  - Password: {$password}\n";
echo "  - id_juego: {$id_juego}\n\n";

// Step 1: Check operator
echo "1. Checking operator in IHSecurity...\n";
try {
    $operador = Operador::where('nombre', $username)->first();
    
    if (!$operador) {
        echo "❌ Operator NOT FOUND\n";
        exit(1);
    }
    
    echo "✅ Operator found:\n";
    echo "   - id_oper: {$operador->id_oper}\n";
    echo "   - nombre: {$operador->nombre}\n";
    echo "   - descripcion: {$operador->descripcion}\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 2: Verify password
echo "2. Verifying password...\n";
$password_hash = strtoupper(sha1($password));
$password_valid = false;

// Try SHA-1
if ($operador->password && $password_hash === strtoupper($operador->password)) {
    $password_valid = true;
    echo "✅ Password verified (SHA-1)\n\n";
}
// Try plain text
elseif ($operador->password && $password === $operador->password) {
    $password_valid = true;
    echo "✅ Password verified (Plain text)\n\n";
}
// Try Bcrypt
elseif ($operador->password && strlen($operador->password) === 60 && str_starts_with($operador->password, '$2')) {
    if (Hash::check($password, $operador->password)) {
        $password_valid = true;
        echo "✅ Password verified (Bcrypt)\n\n";
    }
}

if (!$password_valid) {
    echo "❌ Password verification FAILED\n";
    echo "   Expected SHA-1: {$password_hash}\n";
    echo "   DB password: " . strtoupper($operador->password) . "\n";
    exit(1);
}

// Step 3: Check juego de datos
echo "3. Checking juego de datos...\n";
try {
    $juego = JuegoDato::find($id_juego);
    
    if (!$juego) {
        echo "❌ Juego NOT FOUND\n";
        $all = JuegoDato::all();
        echo "   Available juegos: " . $all->pluck('id_juego')->join(', ') . "\n";
        exit(1);
    }
    
    echo "✅ Juego found:\n";
    echo "   - id_juego: {$juego->id_juego}\n";
    echo "   - descripcion: {$juego->descripcion}\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 4: Check access
echo "4. Checking access permissions...\n";
try {
    $acceso = Acceso::where('id_oper', $operador->id_oper)
        ->whereHas('dato', function ($query) use ($id_juego) {
            $query->where('id_juego', $id_juego)
                  ->where('id_modulo', 36);
        })
        ->with('dato')
        ->first();
    
    if (!$acceso) {
        echo "❌ Access NOT FOUND\n";
        
        // Show all access for this operator
        $all_accesos = Acceso::where('id_oper', $operador->id_oper)
            ->with('dato')
            ->get();
        
        echo "   Total accesos for operator: {$all_accesos->count()}\n";
        foreach ($all_accesos as $acc) {
            if ($acc->dato) {
                echo "   - id_datos: {$acc->dato->id_datos}, id_juego: {$acc->dato->id_juego}, id_modulo: {$acc->dato->id_modulo}, base_datos: {$acc->dato->base_datos}\n";
            }
        }
        exit(1);
    }
    
    echo "✅ Access found:\n";
    echo "   - id_datos: {$acceso->dato->id_datos}\n";
    echo "   - id_juego: {$acceso->dato->id_juego}\n";
    echo "   - id_modulo: {$acceso->dato->id_modulo}\n";
    echo "   - base_datos: " . trim($acceso->dato->base_datos) . "\n";
    echo "   - descripcion: {$acceso->dato->descripcion}\n\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

// Step 5: Verify database match
echo "5. Verifying database configuration...\n";
$base_datos = trim($acceso->dato->base_datos);
$mysql_db = config('database.connections.mysql.database');

echo "   IHSecurity base_datos: '{$base_datos}'\n";
echo "   MySQL configured DB: '{$mysql_db}'\n";

if (strcasecmp($base_datos, $mysql_db) !== 0) {
    echo "❌ DATABASE MISMATCH\n";
    echo "   The databases don't match (case insensitive comparison)\n";
    exit(1);
}

echo "✅ Database configuration matches\n\n";

// Step 6: Test actual API endpoint
echo "6. Testing actual login API endpoint...\n";
$api_url = 'http://127.0.0.1:8000/api/login';
echo "   API URL: {$api_url}\n";

try {
    $response = \Illuminate\Support\Facades\Http::post($api_url, [
        'username' => $username,
        'password' => $password,
        'id_juego' => $id_juego,
    ]);
    
    echo "   Response status: {$response->status()}\n";
    
    if ($response->successful()) {
        echo "✅ LOGIN SUCCESSFUL\n\n";
        $data = $response->json();
        echo "   Response data:\n";
        echo "   - message: {$data['message']}\n";
        echo "   - user: {$data['user']['username']}\n";
        echo "   - operador: {$data['operador']['nombre']}\n";
        echo "   - juego_datos: {$data['juego_datos']['descripcion']}\n";
        echo "   - token_type: {$data['token_type']}\n";
        echo "   - token: " . substr($data['access_token'], 0, 20) . "...\n";
    } else {
        echo "❌ LOGIN FAILED\n";
        echo "   Response body:\n";
        echo json_encode($response->json(), JSON_PRETTY_PRINT) . "\n";
        exit(1);
    }
    
} catch (\Exception $e) {
    echo "❌ API ERROR: " . $e->getMessage() . "\n";
    echo "   Make sure your server is running (php artisan serve)\n";
}

echo "\n=== TEST COMPLETED SUCCESSFULLY ===\n";
