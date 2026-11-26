# Wdrożenie aplikacji warsztatu na VPS (serwis.mati-it.pl).
#
# Budowanie i testy wykonują się TYLKO na komputerze lokalnym. Ten skrypt nic nie
# kompiluje po stronie serwera: pakuje źródła, wgrywa je przez scp i rozpakowuje
# w /srv/sk-serwis, zostawiając nietknięte vendor/, storage/ i .env.
#
# Użycie:
#   pwsh ./deploy/deploy-vps.ps1                 # testy + wdrożenie
#   pwsh ./deploy/deploy-vps.ps1 -SkipTests      # szybkie wdrożenie (testy już zrobione)
#   pwsh ./deploy/deploy-vps.ps1 -NoBackup       # bez kopii poprzednich źródeł
#
# Wymagania: PHP + tar + scp/ssh (OpenSSH) na Windows, klucz mikrus_olga322.

param(
    [string]$SshHost = 'olga322.mikrus.xyz',
    [int]$SshPort = 10322,
    [string]$SshUser = 'root',
    [string]$KeyPath = "$env:USERPROFILE\.ssh\mikrus_olga322",
    [string]$Target = '/srv/sk-serwis',
    [switch]$SkipTests,
    [switch]$NoBackup
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
Push-Location $root

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$stage = Join-Path $root "_deploy/sk-serwis-$stamp"
$archive = Join-Path $root "_deploy/sk-serwis-$stamp.tar.gz"
$remoteArchive = '/tmp/sk-serwis-deploy.tar.gz'

$sshOpts = @('-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=accept-new', '-i', $KeyPath, '-p', "$SshPort", "$SshUser@$SshHost")
$scpOpts = @('-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=accept-new', '-i', $KeyPath, '-P', "$SshPort")

function Invoke-Remote([string]$Command) {
    $out = & ssh @sshOpts $Command 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Polecenie na VPS nie powiodło się: $Command`n$out"
    }
    return $out
}

try {
    if (-not (Test-Path $KeyPath)) {
        throw "Brak klucza SSH: $KeyPath"
    }

    if (-not $SkipTests) {
        Write-Host '1/5 testy lokalne (Pest, magazyn JSON)'
        php artisan test --colors=never
        if ($LASTEXITCODE -ne 0) {
            throw 'Testy nie przeszły — wdrożenie przerwane.'
        }
    } else {
        Write-Host '1/5 testy pominięte (-SkipTests)'
    }

    Write-Host '2/5 pakowanie źródeł'
    if (Test-Path $stage) {
        Remove-Item $stage -Recurse -Force
    }
    New-Item -ItemType Directory -Force -Path $stage | Out-Null

    $include = @(
        'app', 'bootstrap', 'config', 'database', 'public', 'resources', 'routes',
        'artisan', 'composer.json', 'composer.lock', 'phpunit.xml', 'phpunit.dusk.xml',
        'pint.json', 'phpstan.neon', 'README.md', '.env.example', 'deploy'
    )

    foreach ($item in $include) {
        Copy-Item -Path (Join-Path $root $item) -Destination $stage -Recurse -Force
    }

    # Cache i artefakty lokalne nie jadą na serwer; storage/, vendor/ i .env zostają
    # nietknięte po stronie VPS.
    Remove-Item (Join-Path $stage 'bootstrap/cache/*.php') -Force -ErrorAction SilentlyContinue
    Remove-Item (Join-Path $stage 'public/js/axe.min.js') -Force -ErrorAction SilentlyContinue
    Remove-Item (Join-Path $stage 'deploy/deploy-vps.ps1') -Force -ErrorAction SilentlyContinue

    if (Test-Path $archive) {
        Remove-Item $archive -Force
    }

    & tar -czf $archive -C $stage .
    if ($LASTEXITCODE -ne 0) {
        throw 'Nie udało się spakować źródeł (tar).'
    }

    Write-Host '3/5 wysyłka paczki'
    & scp @scpOpts $archive "$SshUser@$SshHost`:$remoteArchive"
    if ($LASTEXITCODE -ne 0) {
        throw 'scp nie powiodło się.'
    }

    Write-Host '4/5 kopia poprzednich źródeł'
    if (-not $NoBackup) {
        $backup = "/srv/backups/sk-serwis-$stamp.tar.gz"
        Invoke-Remote "mkdir -p /srv/backups && tar -czf $backup -C $Target --exclude=storage --exclude=vendor --exclude=.env ." | Out-Null
        Write-Host "    kopia: $backup"
    } else {
        Write-Host '    kopia pominięta (-NoBackup)'
    }

    Write-Host '5/5 rozpakowanie i czyszczenie cache'
    $remoteScript = @"
set -e
tar -xzf $remoteArchive -C $Target
rm -f $remoteArchive
mkdir -p $Target/storage/framework/{cache/data,sessions,views} $Target/storage/logs $Target/bootstrap/cache
chown -R skserwis:skserwis $Target
find $Target/storage $Target/bootstrap/cache -type d -exec chmod 775 {} \;
runuser -u skserwis -- php $Target/artisan optimize:clear
echo 'OK: wdrożono źródła; vendor/, storage/ i .env bez zmian.'
"@
    $result = & ssh @sshOpts $remoteScript 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Wdrożenie na VPS nie powiodło się:`n$result"
    }
    $result

    Write-Host ''
    Write-Host 'Gotowe. Sprawdź: https://serwis.mati-it.pl/ (200, ekran logowania).'
    Write-Host 'Przypomnienie: ASSET_VERSION w /srv/sk-serwis/.env podbij po zmianie CSS/JS.'
}
finally {
    Pop-Location
}
