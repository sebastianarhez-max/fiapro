# FiaPro - pasos posteriores a copiar los archivos nuevos.
# Ejecutar desde la carpeta raiz del proyecto (donde esta el archivo "artisan"):
#   powershell -ExecutionPolicy Bypass -File .\fiapro_post_instalacion.ps1

$ErrorActionPreference = "Stop"
if (-not (Test-Path ".\artisan")) {
    Write-Host "ERROR: ejecuta este script dentro de la carpeta del proyecto Laravel (donde esta 'artisan')." -ForegroundColor Red
    exit 1
}
$utf8 = New-Object System.Text.UTF8Encoding $false

# 1) Zona horaria de Peru (la hora de corte se compara con la hora real)
$app = Join-Path (Get-Location) "config\app.php"
$t = [System.IO.File]::ReadAllText($app)
if ($t -match "'timezone' => 'UTC'") {
    $t = $t.Replace("'timezone' => 'UTC'", "'timezone' => 'America/Lima'")
    [System.IO.File]::WriteAllText($app, $t, $utf8)
    Write-Host "[ok] Zona horaria: America/Lima"
} else {
    Write-Host "[--] Zona horaria ya configurada (revisa config/app.php si no es America/Lima)"
}

# 2) Idioma espanol para los mensajes de validacion
$envFile = Join-Path (Get-Location) ".env"
if (Test-Path $envFile) {
    $e = [System.IO.File]::ReadAllText($envFile)
    if ($e -match "(?m)^APP_LOCALE=.*$") {
        $e = [regex]::Replace($e, "(?m)^APP_LOCALE=.*$", "APP_LOCALE=es")
    } else {
        $e = $e.TrimEnd() + "`r`nAPP_LOCALE=es`r`n"
    }
    [System.IO.File]::WriteAllText($envFile, $e, $utf8)
    Write-Host "[ok] APP_LOCALE=es"
}

# 3) Vistas antiguas que ya no se usan
foreach ($v in @("resources\views\cliente.blade.php", "resources\views\tienda.blade.php")) {
    if (Test-Path $v) { Remove-Item $v; Write-Host "[ok] Eliminada vista antigua $v" }
}

# 4) Base de datos y cache
php artisan migrate --force
php artisan optimize:clear

# 5) Prueba de los juegos de datos del informe
php artisan test --filter=CalculosFinancieros

Write-Host ""
Write-Host "Listo. Inicia el sistema con:  php artisan serve" -ForegroundColor Green
Write-Host "Para que los listados se generen solos en cada corte, en otra ventana:  php artisan schedule:work" -ForegroundColor Green
