# Buduje paczkę wdrożeniową dla hostingu współdzielonego (plan 11.1, T9.2).
#
# Wynik: release-YYYYMMDD-HHmm.zip zawierający aplikację razem z katalogiem
# vendor/ — na serwerze nie ma Composera, SSH ani Node.js.
#
# Użycie:
#   pwsh ./build-release.ps1                 # pełna paczka
#   pwsh ./build-release.ps1 -SkipVendor     # bez vendor/ (gdy hosting ma Composer)

param(
    [switch]$SkipVendor,
    [string]$Output
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
Push-Location $root

try {
    $stamp = Get-Date -Format 'yyyyMMdd-HHmm'
    $releaseName = "release-$stamp"
    $stage = Join-Path $root "_release/$releaseName"

    if (Test-Path (Join-Path $root '_release')) {
        Remove-Item (Join-Path $root '_release') -Recurse -Force
    }

    New-Item -ItemType Directory -Force -Path $stage | Out-Null

    Write-Host '1/5 composer install --no-dev'
    composer install --no-dev --optimize-autoloader --no-interaction --no-progress

    Write-Host '2/5 cache konfiguracji, tras i widoków'
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    php artisan icons:generate

    Write-Host '3/5 kopiowanie plików aplikacji'
    $include = @('app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes', 'storage', 'artisan', 'composer.json', 'composer.lock')

    if (-not $SkipVendor) {
        $include += 'vendor'
    }

    foreach ($item in $include) {
        Copy-Item -Path (Join-Path $root $item) -Destination $stage -Recurse -Force
    }

    # Katalogi zapisywalne muszą istnieć, ale bez danych z maszyny deweloperskiej.
    foreach ($dir in @('storage/app/private', 'storage/app/backups', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs')) {
        $target = Join-Path $stage $dir
        New-Item -ItemType Directory -Force -Path $target | Out-Null
        New-Item -ItemType File -Force -Path (Join-Path $target '.gitignore') | Out-Null
    }

    Remove-Item (Join-Path $stage 'storage/app/data') -Recurse -Force -ErrorAction SilentlyContinue
    Remove-Item (Join-Path $stage 'storage/logs/*.log') -Force -ErrorAction SilentlyContinue

    Copy-Item -Path (Join-Path $root '.env.example') -Destination (Join-Path $stage '.env.example') -Force
    Copy-Item -Path (Join-Path $root 'deploy/htaccess-fallback.txt') -Destination (Join-Path $stage 'deploy-htaccess.txt') -Force

    Write-Host '4/5 pakowanie ZIP'
    if (-not $Output) {
        $Output = Join-Path $root "release-$stamp.zip"
    }

    if (Test-Path $Output) {
        Remove-Item $Output -Force
    }

    Compress-Archive -Path (Join-Path $stage '*') -DestinationPath $Output -CompressionLevel Optimal

    Write-Host '5/5 gotowe'
    Write-Host "Paczka: $Output"
    Write-Host 'Pamiętaj: plik .env tworzysz na serwerze na podstawie .env.example (INSTALL_TOKEN!).'
}
finally {
    Pop-Location
}
