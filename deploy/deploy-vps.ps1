# Deploy the workshop app to the VPS (serwis.mati-it.pl).
# Builds/tests run ONLY locally. This script packs sources, sends them over scp
# and unpacks them in /srv/sk-serwis. vendor/, storage/ and .env are untouched.
# A backup of the previous sources is written to /srv/backups first.
#
# Usage:
#   powershell -ExecutionPolicy Bypass -File deploy/deploy-vps.ps1
#   powershell -ExecutionPolicy Bypass -File deploy/deploy-vps.ps1 -SkipTests
param(
    [switch]$SkipTests,
    [switch]$NoBackup,
    [string]$SshHost = 'olga322.mikrus.xyz',
    [int]$SshPort = 10322,
    [string]$SshUser = 'root',
    [string]$KeyPath = "$env:USERPROFILE\.ssh\id_ed25519_providers"
)

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$pkg = Join-Path $root "release\sk-serwis-$stamp.tgz"
$remotePkg = "/tmp/sk-serwis-$stamp.tgz"
$remoteDir = '/srv/sk-serwis'
$backupDir = '/srv/backups'

if (-not (Test-Path (Split-Path $pkg))) {
    New-Item -ItemType Directory -Force (Split-Path $pkg) | Out-Null
}

$env:PHPRC = Join-Path $root 'php.ini'
$php = 'php'

Write-Host '== 1/5 Local tests ==' -ForegroundColor Cyan
if (-not $SkipTests) {
    Push-Location $root
    try {
        & $php artisan test --colors=never 2>&1 | Tee-Object -Variable testOutput | Out-Null
        $testOutput | Select-Object -Last 20
        if ($LASTEXITCODE -ne 0) {
            Write-Host 'Tests failed - deployment aborted.' -ForegroundColor Red
            exit 1
        }
    } finally {
        Pop-Location
    }
} else {
    Write-Host 'Tests skipped (-SkipTests).' -ForegroundColor Yellow
}

Write-Host '== 2/5 Packing sources ==' -ForegroundColor Cyan
$items = @(
    'app', 'bootstrap', 'config', 'database', 'deploy', 'docs', 'public',
    'resources', 'routes', 'tests',
    'artisan', 'composer.json', 'composer.lock', 'phpunit.xml', 'phpunit.dusk.xml',
    'pint.json', 'phpstan.neon', 'README.md', '.env.example', '.editorconfig',
    '.gitattributes', '.gitignore', 'build-release.ps1'
) | Where-Object { Test-Path (Join-Path $root $_) }

Push-Location $root
try {
    & tar -czf $pkg --exclude='bootstrap/cache/*.php' --exclude='storage' --exclude='vendor' @items
    if ($LASTEXITCODE -ne 0) { throw 'tar failed to create the package.' }
} finally {
    Pop-Location
}
$size = [math]::Round((Get-Item $pkg).Length / 1MB, 2)
Write-Host ('Package: ' + $pkg + ' (' + $size + ' MB)')

$scp = @('-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=accept-new', '-i', $KeyPath, '-P', "$SshPort")
$ssh = @('-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=accept-new', '-i', $KeyPath, '-p', "$SshPort", "$SshUser@$SshHost")

Write-Host '== 3/5 Upload ==' -ForegroundColor Cyan
& scp @scp $pkg "${SshUser}@${SshHost}:$remotePkg"
if ($LASTEXITCODE -ne 0) { throw 'scp failed.' }

Write-Host '== 4/5 Backup and unpack ==' -ForegroundColor Cyan
$backup = ''
if (-not $NoBackup) { $backup = "$backupDir/sk-serwis-$stamp.tar.gz" }
$remoteScript = @'
set -e
mkdir -p __BACKUP_DIR__
if [ -n "__BACKUP__" ]; then
  tar -czf "__BACKUP__" -C __REMOTE_DIR__ app bootstrap config database deploy docs public resources routes tests artisan composer.json composer.lock phpunit.xml phpunit.dusk.xml pint.json phpstan.neon README.md .env.example .editorconfig .gitattributes .gitignore build-release.ps1 2>/dev/null || true
  echo "backup: __BACKUP__"
fi
tar -xzf "__REMOTE_PKG__" -C __REMOTE_DIR__
chown -R skserwis:skserwis __REMOTE_DIR__
rm -f "__REMOTE_PKG__"
if command -v php >/dev/null 2>&1; then
  runuser -u skserwis -- php __REMOTE_DIR__/artisan optimize:clear >/dev/null 2>&1 || true
else
  rm -f __REMOTE_DIR__/bootstrap/cache/*.php 2>/dev/null || true
  rm -f __REMOTE_DIR__/storage/framework/views/*.php 2>/dev/null || true
fi
code=$(curl -s -o /dev/null -w '%{http_code}' http://127.0.0.1:8090/login || true)
echo "health /login: $code"
test "$code" = "200"
'@
$remoteScript = $remoteScript.Replace('__BACKUP_DIR__', $backupDir)
$remoteScript = $remoteScript.Replace('__BACKUP__', $backup)
$remoteScript = $remoteScript.Replace('__REMOTE_DIR__', $remoteDir)
$remoteScript = $remoteScript.Replace('__REMOTE_PKG__', $remotePkg)
$remoteScript | & ssh @ssh 'bash -s'
if ($LASTEXITCODE -ne 0) { throw 'Remote deployment failed.' }

Write-Host '== 5/5 Done ==' -ForegroundColor Green
Write-Host ('App: https://serwis.mati-it.pl (release ' + $stamp + ')')
if ($backup -ne '') { Write-Host ('Previous version backup: ' + $backup) }
Remove-Item $pkg -Force -ErrorAction SilentlyContinue
