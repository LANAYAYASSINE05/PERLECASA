# Prépare deploiement/perlecasa.tar.gz à envoyer sur le serveur.
# Les assets sont compilés ici (Node n'est pas nécessaire sur le VPS) ; vendor est installé sur le serveur.
# Usage (depuis le dossier application) : powershell -ExecutionPolicy Bypass -File deploiement\preparer-archive.ps1

$ErrorActionPreference = 'Stop'
$racine = Split-Path -Parent $PSScriptRoot
Set-Location $racine

Write-Host 'Compilation des assets (npm run build)...'
npm run build
if ($LASTEXITCODE -ne 0) { throw 'La compilation des assets a échoué.' }

if (Test-Path public\hot) { Remove-Item public\hot }

$archive = Join-Path $PSScriptRoot 'perlecasa.tar.gz'
if (Test-Path $archive) { Remove-Item $archive }

$exclusions = @(
    './.env', './.env.backup', './.env.production', './.git', './.phpunit.cache', './.cursor',
    './node_modules', './vendor', './tests', './deploiement/perlecasa.tar.gz',
    './public/storage', './public/hot', './public/apercu-*',
    './storage/logs/*', './storage/framework/cache/*', './storage/framework/sessions/*',
    './storage/framework/views/*', './storage/framework/testing', './storage/app/private/*', './storage/app/public/*',
    './bootstrap/cache/*.php', './database/database.sqlite'
) | ForEach-Object { "--exclude=$_" }

Write-Host 'Création de l''archive...'
tar -czf $archive @exclusions -C $racine .
if ($LASTEXITCODE -ne 0) { throw 'La création de l''archive a échoué.' }

$taille = [math]::Round((Get-Item $archive).Length / 1MB, 1)
Write-Host "Archive prête : $archive ($taille Mo)"
Write-Host 'Envoi : scp deploiement\perlecasa.tar.gz deploiement\installer.sh perlecasa@51.210.44.174:~/'
