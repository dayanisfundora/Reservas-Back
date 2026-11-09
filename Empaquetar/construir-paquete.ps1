<#
.SYNOPSIS
    Genera el paquete descargable de Reservas (Reservas-<version>.zip) y opcionalmente lo sube a Nexus.

.DESCRIPTION
    El paquete contiene PHP portable, MySQL portable, Laravel con el frontend compilado dentro de public/
    e Reservas.exe, que arranca todo y abre el navegador. El usuario solo descomprime y hace doble clic.

    La versión (Mayor.Menor.CambioApp.CambioBD) se registra en system_versions de cada PC al arrancar.

    Este script NO se conecta a ninguna base de datos: no ejecuta migraciones ni seeders y no lee
    las credenciales de Back\.env. La base de datos de desarrollo no se toca.

.EXAMPLE
    .\construir-paquete.ps1 -Version 1.0.0.0

.EXAMPLE
    .\construir-paquete.ps1 -Version 1.0.0.0 -NexusUrl http://nexus.miempresa.cu -NexusRepositorio reservas
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true, HelpMessage = 'Versión Mayor.Menor.CambioApp.CambioBD, por ejemplo 1.0.0.0')]
    [ValidatePattern('^\d+\.\d+\.\d+\.\d+$')]
    [string]$Version,

    # Carpeta de un PHP para Windows x64 con pdo_mysql (por defecto, el PHP de Laragon que cumple el "php" de Back\composer.json)
    [string]$PhpDir,

    # Carpeta de MySQL 8 para Windows x64 (la que contiene bin\mysqld.exe; por defecto, el de Laragon o el del PATH)
    [string]$MysqlDir,

    # Usa el Front\dist\spa existente en lugar de compilar el frontend
    [switch]$SinCompilarFront,

    # Si se indica, sube el zip a un repositorio "raw" de Nexus
    [string]$NexusUrl,
    [string]$NexusRepositorio = 'reservas',
    [System.Management.Automation.PSCredential]$NexusCredencial
)

$ErrorActionPreference = 'Stop'

$raiz = Split-Path -Parent $PSScriptRoot
$back = Join-Path $raiz 'Back'
$front = Join-Path $raiz 'Front'
$salida = Join-Path $PSScriptRoot 'salida'
$paquete = Join-Path $salida 'Reservas'
$zip = Join-Path $salida "Reservas-$Version.zip"

function Paso([string]$texto) {
    Write-Host "`n==> $texto" -ForegroundColor Cyan
}

function Copiar-Carpeta([string]$origen, [string]$destino, [string[]]$excluirCarpetas = @(), [string[]]$excluirArchivos = @()) {
    $parametros = @($origen, $destino, '/E', '/XJ', '/NFL', '/NDL', '/NJH', '/NJS', '/NP', '/R:1', '/W:1')
    if ($excluirCarpetas.Count -gt 0) { $parametros += '/XD'; $parametros += $excluirCarpetas }
    if ($excluirArchivos.Count -gt 0) { $parametros += '/XF'; $parametros += $excluirArchivos }
    & robocopy @parametros | Out-Null
    if ($LASTEXITCODE -ge 8) { throw "robocopy falló copiando $origen (código $LASTEXITCODE)" }
}

function Invocar([string]$descripcion, [scriptblock]$comando) {
    & $comando
    if ($LASTEXITCODE -ne 0) { throw "$descripcion falló (código $LASTEXITCODE)" }
}

function Escribir-Texto([string]$ruta, [string]$contenido) {
    [IO.File]::WriteAllText($ruta, $contenido, (New-Object Text.UTF8Encoding($false)))
}

# rmdir no entra en uniones de directorio (app\public\storage apunta a los datos del usuario)
function Borrar-Carpeta([string]$ruta) {
    for ($intento = 1; $intento -le 5 -and (Test-Path $ruta); $intento++) {
        & cmd.exe /c rmdir /s /q $ruta 2>$null
        if (Test-Path $ruta) { Start-Sleep -Seconds 2 }
    }
    if (Test-Path $ruta) { throw "No se pudo borrar $ruta (¿algún archivo abierto?)." }
}

function Primera-Carpeta([string]$patron) {
    Get-ChildItem $patron -Directory -ErrorAction SilentlyContinue | Sort-Object Name -Descending | Select-Object -First 1 -ExpandProperty FullName
}

# Versión mínima del "php" de composer.json (^8.5.12 -> 8.5.12); vendor\composer\platform_check.php la exige al arrancar
function Php-Minimo([string]$composerJson) {
    $restriccion = (Get-Content $composerJson -Raw | ConvertFrom-Json).require.php
    if ($restriccion -match '(\d+)\.(\d+)(?:\.(\d+))?') {
        return [version]("{0}.{1}.{2}" -f $Matches[1], $Matches[2], $(if ($Matches[3]) { $Matches[3] } else { 0 }))
    }
    return $null
}

function Version-Php([string]$carpeta) {
    $texto = & "$carpeta\php.exe" -n -r 'echo PHP_VERSION;'
    if ($texto -notmatch '^(\d+\.\d+\.\d+)') { throw "No se pudo leer la versión de $carpeta\php.exe" }
    return [version]$Matches[1]
}

# --------------------------------------------------------------------------
Paso 'Verificando herramientas'

$phpMinimo = Php-Minimo "$back\composer.json"
if (-not $PhpDir) {
    $PhpDir = Get-ChildItem 'C:\laragon\bin\php\php-*' -Directory -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -match '^php-(\d+\.\d+\.\d+)' } |
        ForEach-Object { [pscustomobject]@{ Ruta = $_.FullName; Version = [version]$Matches[1] } } |
        Where-Object { -not $phpMinimo -or ($_.Version -ge $phpMinimo -and $_.Version.Major -eq $phpMinimo.Major) } |
        Sort-Object Version | Select-Object -First 1 -ExpandProperty Ruta
}
if (-not $PhpDir) { $PhpDir = Split-Path -Parent (Get-Command php -ErrorAction Stop).Source }
if (-not $MysqlDir) { $MysqlDir = Primera-Carpeta 'C:\laragon\bin\mysql\mysql-8.*' }
if (-not $MysqlDir) { $MysqlDir = Split-Path -Parent (Split-Path -Parent (Get-Command mysql -ErrorAction Stop).Source) }
$csc = Join-Path $env:WINDIR 'Microsoft.NET\Framework64\v4.0.30319\csc.exe'

foreach ($requerido in @("$PhpDir\php.exe", "$PhpDir\ext\php_pdo_mysql.dll", "$MysqlDir\bin\mysqld.exe", $csc)) {
    if (-not (Test-Path $requerido)) { throw "No se encontró $requerido" }
}
Get-Command composer -ErrorAction Stop | Out-Null

$phpVersion = Version-Php $PhpDir
if ($phpMinimo -and $phpVersion -lt $phpMinimo) {
    throw "Back\composer.json exige PHP $phpMinimo o superior y $PhpDir es $phpVersion. Indique otro con -PhpDir."
}

Write-Host "PHP:   $PhpDir ($phpVersion)"
Write-Host "MySQL: $MysqlDir"

$abiertos = @(Get-CimInstance Win32_Process | Where-Object { $_.ExecutablePath -and $_.ExecutablePath.StartsWith($paquete, 'OrdinalIgnoreCase') })
if ($abiertos.Count -gt 0) {
    throw "Reservas está abierto desde $paquete. Ciérrelo (ícono junto al reloj > Salir) y vuelva a ejecutar el script."
}
Borrar-Carpeta $paquete
New-Item -ItemType Directory -Path $paquete | Out-Null

# --------------------------------------------------------------------------
if (-not $SinCompilarFront) {
    Paso 'Compilando el frontend (la API se toma de la misma dirección que Laravel)'
    # Sin VITE_API_BASE_URL ni VITE_BACKEND_ORIGIN el frontend usa window.location.origin + /api,
    # así el mismo dist\spa sirve para el paquete y para el servidor web.
    $apiAnterior = $env:VITE_API_BASE_URL
    $origenAnterior = $env:VITE_BACKEND_ORIGIN
    Remove-Item env:VITE_API_BASE_URL, env:VITE_BACKEND_ORIGIN -ErrorAction SilentlyContinue
    Push-Location $front
    try {
        Invocar 'quasar build' { npx quasar build }
    } finally {
        Pop-Location
        if ($apiAnterior) { $env:VITE_API_BASE_URL = $apiAnterior }
        if ($origenAnterior) { $env:VITE_BACKEND_ORIGIN = $origenAnterior }
    }
}
if (-not (Test-Path "$front\dist\spa\index.html")) { throw "No existe $front\dist\spa\index.html" }

# --------------------------------------------------------------------------
Paso 'Copiando Laravel'
$app = Join-Path $paquete 'app'
Copiar-Carpeta $back $app `
    -excluirCarpetas @("$back\.git", "$back\.github", "$back\node_modules", "$back\tests", "$back\storage",
                       "$back\scripts", "$back\public\storage") `
    -excluirArchivos @('.env', '.env.*', '*.rar', '*.sql', '.phpunit.result.cache', 'phpunit.xml', 'agents.md',
                       'composer_output.txt', 'package.json', 'package-lock.json', 'vite.config.js',
                       'tailwind.config.js', 'postcss.config.js', '.editorconfig', '.gitattributes', 'README.md',
                       'test_*.php')

foreach ($sub in 'app\public', 'app\temp', 'framework\cache\data', 'framework\sessions', 'framework\views', 'logs') {
    New-Item -ItemType Directory -Force -Path (Join-Path $app "storage\$sub") | Out-Null
}
Get-ChildItem (Join-Path $app 'bootstrap\cache') -Filter *.php | Remove-Item -Force
Escribir-Texto (Join-Path $app 'VERSION') $Version

$bytesClave = New-Object byte[] 32
(New-Object Security.Cryptography.RNGCryptoServiceProvider).GetBytes($bytesClave)
Escribir-Texto (Join-Path $app '.env') (@(
    'APP_NAME=Reservas'
    'APP_ENV=production'
    "APP_KEY=base64:$([Convert]::ToBase64String($bytesClave))"
    'APP_DEBUG=false'
    'APP_TIMEZONE=UTC'
    'APP_URL=http://127.0.0.1:8093'
    'APP_LOCALE=es'
    'APP_FALLBACK_LOCALE=es'
    ''
    'LOG_CHANNEL=stack'
    'LOG_STACK=daily'
    'LOG_DEPRECATIONS_CHANNEL=null'
    'LOG_LEVEL=error'
    'LOG_DAILY_DAYS=14'
    ''
    '# Reservas.exe redefine estos valores a partir de Configuracion.ini; nunca apuntan al MySQL de desarrollo (3306)'
    'DB_CONNECTION=mysql'
    'DB_HOST=127.0.0.1'
    'DB_PORT=3320'
    'DB_DATABASE=ReservasPaquete'
    'DB_USERNAME=root'
    'DB_PASSWORD='
    ''
    'BROADCAST_CONNECTION=log'
    'CACHE_STORE=file'
    'FILESYSTEM_DISK=local'
    'QUEUE_CONNECTION=sync'
    'SESSION_DRIVER=file'
    'SESSION_LIFETIME=15'
    'SANCTUM_TOKEN_EXPIRATION=15'
    'MAIL_MAILER=log'
    ''
    '# El frontend usa token Bearer; un origen "stateful" exigiría CSRF (error 419). Reservas.exe también lo fija.'
    'SANCTUM_STATEFUL_DOMAINS=none.invalid'
    ''
) -join "`r`n")

Paso 'Quitando dependencias de desarrollo (composer --no-dev)'
Push-Location $app
try {
    Invocar 'composer install' { composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --quiet }
    Invocar 'package:discover' { php artisan package:discover --no-ansi }
} finally {
    Pop-Location
}

Paso 'Copiando el frontend compilado a app\public'
Copiar-Carpeta "$front\dist\spa" (Join-Path $app 'public') -excluirArchivos @('.htaccess', 'web.config')

# --------------------------------------------------------------------------
Paso 'Copiando PHP portable'
$php = Join-Path $paquete 'php'
Copiar-Carpeta $PhpDir $php `
    -excluirCarpetas @("$PhpDir\dev") `
    -excluirArchivos @('php.ini', 'php.ini-development', 'php.ini-production', 'phpdbg.exe', 'php8phpdbg.dll',
                       'php-cgi.exe', 'deplister.exe', '*.lib')

$iniActual = Join-Path $PhpDir 'php.ini'
$cacert = $null
if (Test-Path $iniActual) {
    $linea = Select-String -Path $iniActual -Pattern '^\s*curl\.cainfo\s*=\s*"?([^"]+)"?' | Select-Object -First 1
    if ($linea) { $cacert = $linea.Matches[0].Groups[1].Value.Trim() }
}
New-Item -ItemType Directory -Force -Path "$php\extras\ssl" | Out-Null
if ($cacert -and (Test-Path $cacert)) {
    Copy-Item $cacert "$php\extras\ssl\cacert.pem"
} else {
    Write-Warning 'No se encontró cacert.pem: las conexiones HTTPS salientes de PHP podrían fallar.'
}

Escribir-Texto "$php\php.ini" (@(
    '[PHP]'
    'memory_limit = 512M'
    'max_execution_time = 300'
    'max_input_time = 300'
    'max_input_vars = 10000'
    'post_max_size = 256M'
    'upload_max_filesize = 256M'
    'display_errors = Off'
    'log_errors = On'
    '; Con E, $_ENV conserva las variables de Windows (SystemRoot...) para los procesos que lance PHP (Symfony Process)'
    'variables_order = "EGPCS"'
    'request_order = "GP"'
    'default_charset = "UTF-8"'
    'date.timezone = UTC'
    'realpath_cache_size = 4096K'
    'realpath_cache_ttl = 600'
    ''
    '; extension_dir, curl.cainfo y openssl.cafile los define Reservas.exe con rutas absolutas'
    'extension=curl'
    'extension=fileinfo'
    'extension=gd'
    'extension=intl'
    'extension=mbstring'
    'extension=exif'
    'extension=mysqli'
    'extension=openssl'
    'extension=pdo_mysql'
    'extension=sodium'
    'extension=zip'
    ''
    # Desde PHP 8.5 OPcache viene integrado y no existe php_opcache.dll
    $(if (Test-Path "$PhpDir\ext\php_opcache.dll") { 'zend_extension=opcache' })
    'opcache.enable = 1'
    'opcache.enable_cli = 1'
    'opcache.memory_consumption = 128'
    'opcache.max_accelerated_files = 20000'
    '; opcache.file_cache lo define Reservas.exe en la carpeta de datos del usuario'
    'opcache.file_cache_fallback = 1'
    ''
) -join "`r`n")

# --------------------------------------------------------------------------
Paso 'Copiando MySQL portable'
$mysql = Join-Path $paquete 'mysql'
New-Item -ItemType Directory -Force -Path "$mysql\bin" | Out-Null
foreach ($exe in 'mysqld.exe', 'mysql.exe', 'mysqladmin.exe', 'mysqldump.exe') {
    Copy-Item "$MysqlDir\bin\$exe" "$mysql\bin\"
}
Get-ChildItem "$MysqlDir\bin\*.dll" | Where-Object { $_.Name -notlike '*-debug.dll' } | Copy-Item -Destination "$mysql\bin\"
Copiar-Carpeta "$MysqlDir\share" "$mysql\share"
if (Test-Path "$MysqlDir\LICENSE") { Copy-Item "$MysqlDir\LICENSE" "$mysql\" }

Paso 'Agregando el runtime de Visual C++ (para PCs que no lo tengan instalado)'
foreach ($dll in 'vcruntime140.dll', 'vcruntime140_1.dll', 'msvcp140.dll', 'msvcp140_1.dll', 'msvcp140_2.dll') {
    $origenDll = Join-Path $env:WINDIR "System32\$dll"
    if (Test-Path $origenDll) {
        foreach ($destinoDll in $php, "$mysql\bin") {
            if (-not (Test-Path (Join-Path $destinoDll $dll))) { Copy-Item $origenDll $destinoDll }
        }
    }
}

# --------------------------------------------------------------------------
Paso 'Compilando Reservas.exe'
$icono = Join-Path $front 'public\favicon.ico'
$iconoDesdeSvg = Join-Path $env:TEMP 'Reservas.favicon.ico'
try {
    & "$PSScriptRoot\generar-icono.ps1" -Svg (Join-Path $front 'public\favicon.svg') -Salida $iconoDesdeSvg | Out-Null
    $icono = $iconoDesdeSvg
    Copy-Item $iconoDesdeSvg (Join-Path $app 'public\favicon.ico') -Force
} catch {
    Write-Warning "No se pudo generar el ícono desde favicon.svg ($($_.Exception.Message)); se usa favicon.ico."
}
$infoEnsamblado = Join-Path $env:TEMP 'Reservas.VersionInfo.cs'
Escribir-Texto $infoEnsamblado (@(
    'using System.Reflection;'
    '[assembly: AssemblyTitle("Reservas")]'
    '[assembly: AssemblyProduct("Reservas")]'
    '[assembly: AssemblyCompany("Reservas")]'
    "[assembly: AssemblyVersion(`"$Version`")]"
    "[assembly: AssemblyFileVersion(`"$Version`")]"
    "[assembly: AssemblyInformationalVersion(`"$Version`")]"
) -join "`r`n")
try {
    Invocar 'csc' {
        $argumentoIcono = if (Test-Path $icono) { "/win32icon:$icono" } else { '/nologo' }
        & $csc /nologo /codepage:65001 /target:winexe /optimize+ /platform:anycpu "/out:$paquete\Reservas.exe" $argumentoIcono `
            /r:System.Windows.Forms.dll /r:System.Drawing.dll "$PSScriptRoot\lanzador\Reservas.cs" $infoEnsamblado
    }
} finally {
    Remove-Item $infoEnsamblado, $iconoDesdeSvg -ErrorAction SilentlyContinue
}

Copy-Item "$PSScriptRoot\plantillas\Configuracion-inicial.ini" $paquete
Copy-Item "$PSScriptRoot\plantillas\LEEME.txt" $paquete
Escribir-Texto "$paquete\VERSION.txt" "$Version`r`n"

# --------------------------------------------------------------------------
Paso 'Revisando que no haya archivos sensibles en el paquete'
$sensibles = @(Get-ChildItem $paquete -Recurse -Force -File -ErrorAction SilentlyContinue | Where-Object {
    -not $_.FullName.StartsWith("$mysql\", 'OrdinalIgnoreCase')
} | Where-Object {
    ($_.Name -like '*.sql') -or ($_.Name -like '*.rar') -or
    ($_.Name -like '.env*' -and $_.FullName -ne (Join-Path $app '.env'))
})
if ($sensibles.Count -gt 0) {
    $sensibles | ForEach-Object { Write-Warning $_.FullName }
    throw 'El paquete contiene archivos que no deben publicarse (ver la lista anterior).'
}

# --------------------------------------------------------------------------
Paso "Comprimiendo $zip"
Add-Type -AssemblyName System.IO.Compression.FileSystem
if (Test-Path $zip) { Remove-Item $zip -Force }
[IO.Compression.ZipFile]::CreateFromDirectory($paquete, $zip, [IO.Compression.CompressionLevel]::Optimal, $true)
$tamano = [math]::Round((Get-Item $zip).Length / 1MB, 1)
Write-Host "Paquete listo: $zip ($tamano MB)" -ForegroundColor Green

# --------------------------------------------------------------------------
if ($NexusUrl) {
    Paso 'Subiendo a Nexus'
    if (-not $NexusCredencial) { $NexusCredencial = Get-Credential -Message 'Usuario y contraseña de Nexus' }
    $par = '{0}:{1}' -f $NexusCredencial.UserName, $NexusCredencial.GetNetworkCredential().Password
    $cabeceras = @{ Authorization = 'Basic ' + [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($par)) }
    $base = '{0}/repository/{1}/reservas' -f $NexusUrl.TrimEnd('/'), $NexusRepositorio

    $destinos = @("$base/$Version/Reservas-$Version.zip", "$base/ultima/Reservas.zip")
    foreach ($destino in $destinos) {
        try {
            Invoke-WebRequest -Uri $destino -Method Put -InFile $zip -Headers $cabeceras -ContentType 'application/zip' -UseBasicParsing | Out-Null
            Write-Host "Subido: $destino" -ForegroundColor Green
        } catch {
            Write-Warning "No se pudo subir a $destino : $($_.Exception.Message)"
            if ($destino -like '*/ultima/*') {
                Write-Warning 'Para sobrescribir "ultima" el repositorio debe tener Deployment policy = "Allow redeploy".'
            }
        }
    }

    $documentos = Join-Path $raiz "Docs\Nexus\$Version"
    if (Test-Path $documentos) {
        foreach ($pdf in Get-ChildItem $documentos -Filter *.pdf) {
            $destino = "$base/$Version/" + [Uri]::EscapeDataString($pdf.Name)
            try {
                Invoke-WebRequest -Uri $destino -Method Put -InFile $pdf.FullName -Headers $cabeceras -ContentType 'application/pdf' -UseBasicParsing | Out-Null
                Write-Host "Subido: $destino" -ForegroundColor Green
            } catch {
                Write-Warning "No se pudo subir a $destino : $($_.Exception.Message)"
            }
        }
    }
}
