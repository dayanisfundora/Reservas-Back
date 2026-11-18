<#
.SYNOPSIS
    Prueba el paquete generado (salida\Reservas) sin abrir Reservas.exe.

.DESCRIPTION
    Reproduce los pasos del lanzador con una carpeta de datos temporal, un MySQL propio y puertos distintos:
    inicializa MySQL, ejecuta migraciones, seeders y system:version, levanta PHP y hace peticiones de prueba
    (SPA, API, inicio de sesión, PDF y respaldo de la base de datos). Al terminar detiene todo y borra la carpeta.

    No lee Back\.env ni se conecta al MySQL de desarrollo (3306): usa el MySQL del paquete en el puerto 3420
    y la base de datos reservas_prueba_paquete.
#>
$ErrorActionPreference = 'Stop'
$pkg = Join-Path $PSScriptRoot 'salida\Reservas'
$datos = Join-Path $env:TEMP 'reservas-prueba-paquete'
$puertoMysql = 3420
$puertoWeb = 8193
$baseDatos = 'reservas_prueba_paquete'

if (-not (Test-Path "$pkg\Reservas.exe")) { throw "No existe $pkg\Reservas.exe. Ejecute primero construir-paquete.ps1" }

# \\?\ porque la caché de OPcache crea rutas de más de 260 caracteres
if (Test-Path $datos) { Remove-Item -LiteralPath "\\?\$datos" -Recurse -Force }
New-Item -ItemType Directory -Force "$datos\logs", "$datos\opcache" | Out-Null
foreach ($s in 'app\public', 'app\temp', 'framework\cache\data', 'framework\sessions', 'framework\views', 'logs') {
    New-Item -ItemType Directory -Force "$datos\storage\$s" | Out-Null
}

$ini = "$datos\my.ini"
@(
    '[mysqld]'
    "basedir=`"$("$pkg\mysql" -replace '\\', '/')`""
    "datadir=`"$("$datos\mysql-data" -replace '\\', '/')`""
    "port=$puertoMysql"
    'bind-address=127.0.0.1'
    'mysqlx=OFF'
    'character-set-server=utf8mb4'
    'collation-server=utf8mb4_unicode_ci'
    'max_allowed_packet=256M'
    "log-error=`"$("$datos\logs\mysql.err" -replace '\\', '/')`""
) | Set-Content -Path $ini -Encoding Ascii

Write-Output '--- 1. Inicializar MySQL'
$t = Measure-Command { & "$pkg\mysql\bin\mysqld.exe" "--defaults-file=$ini" --initialize-insecure }
"codigo=$LASTEXITCODE ($([int]$t.TotalSeconds) s)"

Write-Output '--- 2. Iniciar MySQL'
$mysqld = Start-Process -FilePath "$pkg\mysql\bin\mysqld.exe" -ArgumentList "--defaults-file=`"$ini`"" -WindowStyle Hidden -PassThru
$escuchando = $false
for ($i = 0; $i -lt 120 -and -not $escuchando; $i++) {
    try { (New-Object Net.Sockets.TcpClient('127.0.0.1', $puertoMysql)).Close(); $escuchando = $true } catch { Start-Sleep -Milliseconds 500 }
}
"escuchando=$escuchando"

& "$pkg\mysql\bin\mysql.exe" --host=127.0.0.1 --port=$puertoMysql --user=root `
    "--execute=CREATE DATABASE IF NOT EXISTS ``$baseDatos`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
"crear base: codigo=$LASTEXITCODE"

$variables = @{
    DB_CONNECTION = 'mysql'; DB_HOST = '127.0.0.1'; DB_PORT = "$puertoMysql"; DB_DATABASE = $baseDatos
    DB_USERNAME = 'root'; DB_PASSWORD = ''
    APP_URL = "http://127.0.0.1:$puertoWeb"; FRONTEND_URL = "http://127.0.0.1:$puertoWeb"
    CORS_ALLOWED_ORIGINS = "http://127.0.0.1:$puertoWeb"
    SANCTUM_STATEFUL_DOMAINS = 'none.invalid'
    LARAVEL_STORAGE_PATH = "$datos\storage"
}
$anteriores = @{}
foreach ($k in $variables.Keys) {
    $anteriores[$k] = [Environment]::GetEnvironmentVariable($k, 'Process')
    Set-Item "env:$k" $variables[$k]
}

$argumentosPhp = @('-c', "$pkg\php\php.ini", '-d', "extension_dir=$pkg\php\ext",
                   '-d', "curl.cainfo=$pkg\php\extras\ssl\cacert.pem", '-d', "openssl.cafile=$pkg\php\extras\ssl\cacert.pem",
                   '-d', "opcache.file_cache=$datos\opcache")
$php = $null

try {
    Write-Output '--- 3. Extensiones del PHP del paquete'
    & "$pkg\php\php.exe" @argumentosPhp -m | Select-String -Pattern '^(pdo_mysql|mysqli|gd|zip|intl|mbstring|fileinfo|curl|openssl|Zend OPcache)$' | ForEach-Object { $_.Line }

    Push-Location "$pkg\app"
    try {
        Write-Output '--- 4. Migraciones'
        $t = Measure-Command { & "$pkg\php\php.exe" @argumentosPhp artisan migrate --force --no-interaction 2>&1 | Select-Object -Last 3 | Out-Host }
        "codigo=$LASTEXITCODE ($([int]$t.TotalSeconds) s)"

        Write-Output '--- 5. Seeders'
        & "$pkg\php\php.exe" @argumentosPhp artisan db:seed --force --no-interaction 2>&1 | Select-Object -Last 4
        "codigo=$LASTEXITCODE"

        Write-Output '--- 6. Versión'
        $version = (Get-Content "$pkg\app\VERSION" -Raw).Trim().Split('.')
        & "$pkg\php\php.exe" @argumentosPhp artisan system:version $version[0] $version[1] $version[2] $version[3] --if-different "--description=Prueba" --no-interaction
        & "$pkg\php\php.exe" @argumentosPhp artisan system:version $version[0] $version[1] $version[2] $version[3] --if-different "--description=Prueba" --no-interaction

        Write-Output '--- 7. storage_path, conexión y entorno vistos por Laravel'
        & "$pkg\php\php.exe" @argumentosPhp artisan tinker --execute="echo storage_path(), ' | ', config('database.connections.mysql.port'), ' | ', config('database.connections.mysql.database'), ' | ', app()->environment(), PHP_EOL;"
    } finally {
        Pop-Location
    }

    Write-Output '--- 8. Servidor PHP'
    $enrutador = "$pkg\app\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php"
    $argumentosServidor = @($argumentosPhp | ForEach-Object { if ($_ -match ' ') { "`"$_`"" } else { $_ } }) + @('-S', "127.0.0.1:$puertoWeb", "`"$enrutador`"")
    $php = Start-Process -FilePath "$pkg\php\php.exe" -ArgumentList $argumentosServidor -WorkingDirectory "$pkg\app\public" -WindowStyle Hidden -PassThru
    $webEscuchando = $false
    for ($i = 0; $i -lt 120 -and -not $webEscuchando -and -not $php.HasExited; $i++) {
        try { (New-Object Net.Sockets.TcpClient('127.0.0.1', $puertoWeb)).Close(); $webEscuchando = $true } catch { Start-Sleep -Milliseconds 500 }
    }
    "escuchando=$webEscuchando"

    function Probar([string]$metodo, [string]$ruta, [string]$cuerpo, [hashtable]$cabeceras = @{}) {
        $peticion = @{
            Uri = "http://127.0.0.1:$puertoWeb$ruta"; Method = $metodo; UseBasicParsing = $true; TimeoutSec = 120
            Headers = @{ Accept = 'application/json' } + $cabeceras
        }
        if ($cuerpo) { $peticion.Body = $cuerpo; $peticion.ContentType = 'application/json' }
        try {
            $r = Invoke-WebRequest @peticion
            $texto = if ($r.Content -is [byte[]]) { "($($r.Content.Length) bytes)" } else { [string]$r.Content }
            if ($texto.Length -gt 150) { $texto = $texto.Substring(0, 150) + '...' }
            '{0} {1} -> {2} [{3}] {4}' -f $metodo, $ruta, $r.StatusCode, $r.Headers['Content-Type'], ($texto -replace '\s+', ' ')
            return $r
        } catch {
            $respuesta = $_.Exception.Response
            if ($respuesta) {
                $texto = (New-Object IO.StreamReader($respuesta.GetResponseStream())).ReadToEnd()
                if ($texto.Length -gt 200) { $texto = $texto.Substring(0, 200) + '...' }
                '{0} {1} -> {2} {3}' -f $metodo, $ruta, [int]$respuesta.StatusCode, ($texto -replace '\s+', ' ')
            } else {
                '{0} {1} -> ERROR {2}' -f $metodo, $ruta, $_.Exception.Message
            }
        }
    }

    Probar GET '/' $null | Select-Object -First 1
    Probar GET '/login' $null | Select-Object -First 1
    Probar GET '/calendario' $null | Select-Object -First 1
    $js = (Get-ChildItem "$pkg\app\public\assets" -Filter 'index*.js' | Select-Object -First 1).Name
    if ($js) { Probar GET "/assets/$js" $null | Select-Object -First 1 }
    Probar GET '/api/no-existe' $null | Select-Object -First 1

    # El navegador envía Origin y Referer: así se comprueba que statefulApi() no exige CSRF (419)
    $navegador = @{ Origin = "http://127.0.0.1:$puertoWeb"; Referer = "http://127.0.0.1:$puertoWeb/login" }
    Probar POST '/api/auth/login' '{"name":"admin","password":"incorrecta"}' $navegador | Select-Object -First 1

    $login = Probar POST '/api/auth/login' '{"name":"admin","password":"Admin12345*"}' $navegador
    $login | Where-Object { $_ -is [string] }
    $respuestaLogin = $login | Where-Object { $_ -isnot [string] } | Select-Object -First 1
    if ($respuestaLogin) {
        $datosLogin = ($respuestaLogin.Content | ConvertFrom-Json).data
        $empresa = $datosLogin.empresas | Select-Object -First 1
        "empresas visibles para admin: $(@($datosLogin.empresas).Count) (primera: $($empresa.nombre))"
        $auth = @{ Authorization = "Bearer $($datosLogin.token)"; 'X-Empresa-Id' = "$($empresa.id)" } + $navegador
        Probar GET '/api/auth/me' $null $auth | Select-Object -First 1
        $casas = Probar GET '/api/casas' $null $auth
        $casas | Where-Object { $_ -is [string] }
        $casa = (($casas | Where-Object { $_ -isnot [string] } | Select-Object -First 1).Content | ConvertFrom-Json).data | Select-Object -First 1
        Probar POST '/api/habitaciones' ('{"casa_id":' + $casa.id + ',"nombre":"Prueba","tipo":"privada","capacidad":2}') $auth | Select-Object -First 1
        Probar GET '/api/habitaciones' $null $auth | Select-Object -First 1
        Probar GET '/api/reservas' $null $auth | Select-Object -First 1
        $desde = (Get-Date).ToString('yyyy-MM-01'); $hasta = (Get-Date).AddMonths(1).AddDays(-(Get-Date).Day).ToString('yyyy-MM-dd')
        Probar GET "/api/reservas/calendario?casa_id=$($casa.id)&desde=$desde&hasta=$hasta" $null $auth | Select-Object -First 1
        Probar GET "/api/reservas/calendario/pdf?casa_id=$($casa.id)&desde=$desde&hasta=$hasta" $null $auth | Select-Object -First 1
    }
} finally {
    Write-Output '--- 9. Detener'
    if ($php) { Stop-Process -Id $php.Id -Force -ErrorAction SilentlyContinue }
    & "$pkg\mysql\bin\mysqladmin.exe" --host=127.0.0.1 --port=$puertoMysql --user=root shutdown
    $mysqld.WaitForExit(30000) | Out-Null
    "mysqld detenido=$($mysqld.HasExited)"
    foreach ($k in $anteriores.Keys) { [Environment]::SetEnvironmentVariable($k, $anteriores[$k], 'Process') }
    Remove-Item -LiteralPath "\\?\$datos" -Recurse -Force -ErrorAction SilentlyContinue
}
