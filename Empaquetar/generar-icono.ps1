<#
.SYNOPSIS
    Convierte el favicon.svg del sistema en un .ico de Windows (16 a 256 px) para Impuestos.exe.

.DESCRIPTION
    Microsoft Edge (sin ventana) dibuja el SVG a 256 px con fondo transparente; los tamaños menores se
    obtienen reduciendo esa imagen. Así el ícono del programa es siempre igual al favicon del sistema.

.EXAMPLE
    .\generar-icono.ps1 -Svg ..\Front\public\favicon.svg -Salida ..\Front\public\favicon.ico
#>
param(
    [Parameter(Mandatory = $true)][string]$Svg,
    [Parameter(Mandatory = $true)][string]$Salida
)

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.Drawing

$Svg = (Resolve-Path $Svg).Path
$edge = @(
    "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
    "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe"
) | Where-Object { Test-Path $_ } | Select-Object -First 1
if (-not $edge) { throw 'No se encontró Microsoft Edge para dibujar el SVG.' }

$trabajo = Join-Path $env:TEMP ("icono-" + [guid]::NewGuid())
New-Item -ItemType Directory -Path $trabajo | Out-Null
try {
    Copy-Item $Svg (Join-Path $trabajo 'icono.svg')
    $html = Join-Path $trabajo 'icono.html'
    [IO.File]::WriteAllText($html, '<!DOCTYPE html><html><head><style>html,body{margin:0;padding:0;background:transparent;overflow:hidden}img{display:block;width:256px;height:256px}</style></head><body><img src="icono.svg"></body></html>')
    $png = Join-Path $trabajo 'icono-256.png'
    $argumentos = @(
        '--headless', '--disable-gpu', '--no-first-run', '--hide-scrollbars',
        '--default-background-color=00000000', '--force-device-scale-factor=1', '--window-size=256,256',
        "--user-data-dir=`"$(Join-Path $trabajo 'perfil')`"", "--screenshot=`"$png`"", ([Uri]$html).AbsoluteUri
    )
    Start-Process -FilePath $edge -ArgumentList $argumentos -Wait -WindowStyle Hidden
    if (-not (Test-Path $png)) { throw 'Edge no generó la imagen del ícono.' }

    $original = [Drawing.Bitmap]::FromFile($png)
    $tamanos = 16, 24, 32, 48, 64, 128, 256
    $entradas = @()
    foreach ($tam in $tamanos) {
        $bmp = New-Object Drawing.Bitmap $tam, $tam, ([Drawing.Imaging.PixelFormat]::Format32bppArgb)
        $g = [Drawing.Graphics]::FromImage($bmp)
        $g.InterpolationMode = [Drawing.Drawing2D.InterpolationMode]::HighQualityBicubic
        $g.PixelOffsetMode = [Drawing.Drawing2D.PixelOffsetMode]::HighQuality
        $g.SmoothingMode = [Drawing.Drawing2D.SmoothingMode]::HighQuality
        $g.DrawImage($original, 0, 0, $tam, $tam)
        $g.Dispose()

        $ms = New-Object IO.MemoryStream
        if ($tam -eq 256) {
            $bmp.Save($ms, [Drawing.Imaging.ImageFormat]::Png)
        } else {
            # BITMAPINFOHEADER + píxeles BGRA de abajo hacia arriba + máscara AND de 1 bit
            $bw = New-Object IO.BinaryWriter $ms
            $bw.Write([int]40); $bw.Write([int]$tam); $bw.Write([int]($tam * 2))
            $bw.Write([int16]1); $bw.Write([int16]32); $bw.Write([int]0)
            $bw.Write([int]($tam * $tam * 4)); $bw.Write([int]0); $bw.Write([int]0); $bw.Write([int]0); $bw.Write([int]0)
            for ($y = $tam - 1; $y -ge 0; $y--) {
                for ($x = 0; $x -lt $tam; $x++) {
                    $c = $bmp.GetPixel($x, $y)
                    $bw.Write([byte]$c.B); $bw.Write([byte]$c.G); $bw.Write([byte]$c.R); $bw.Write([byte]$c.A)
                }
            }
            $bytesFila = [int]([Math]::Ceiling($tam / 32.0) * 4)
            for ($y = $tam - 1; $y -ge 0; $y--) {
                $fila = New-Object byte[] $bytesFila
                for ($x = 0; $x -lt $tam; $x++) {
                    if ($bmp.GetPixel($x, $y).A -eq 0) {
                        $indice = [int][Math]::Floor($x / 8)
                        $fila[$indice] = $fila[$indice] -bor (0x80 -shr ($x % 8))
                    }
                }
                $bw.Write($fila)
            }
            $bw.Flush()
        }
        $entradas += , $ms.ToArray()
        $bmp.Dispose()
    }
    $original.Dispose()

    $fs = [IO.File]::Create($Salida)
    try {
        $bw = New-Object IO.BinaryWriter $fs
        $bw.Write([int16]0); $bw.Write([int16]1); $bw.Write([int16]$tamanos.Count)
        $offset = 6 + 16 * $tamanos.Count
        for ($i = 0; $i -lt $tamanos.Count; $i++) {
            $lado = if ($tamanos[$i] -ge 256) { 0 } else { $tamanos[$i] }
            $bw.Write([byte]$lado); $bw.Write([byte]$lado); $bw.Write([byte]0); $bw.Write([byte]0)
            $bw.Write([int16]1); $bw.Write([int16]32)
            $bw.Write([int]$entradas[$i].Length); $bw.Write([int]$offset)
            $offset += $entradas[$i].Length
        }
        foreach ($entrada in $entradas) { $bw.Write([byte[]]$entrada) }
        $bw.Flush()
    } finally {
        $fs.Close()
    }
    Write-Output "Ícono generado: $Salida"
} finally {
    Remove-Item $trabajo -Recurse -Force -ErrorAction SilentlyContinue
}
