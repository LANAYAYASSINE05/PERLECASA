# Déploie Perle Casa Immobilier : git push vers GitHub, puis mise à jour du VPS (git pull, composer, build, migrations).
# Usage (depuis le dossier application) : powershell -ExecutionPolicy Bypass -File deploiement\deployer.ps1 [-Tests]

param([switch]$Tests)

$ErrorActionPreference = 'Stop'
$racine = Split-Path -Parent $PSScriptRoot
Set-Location $racine

$serveur = 'gmsweb@51.210.44.174'
$cle = Join-Path $env:USERPROFILE '.ssh\perlecasa_vps'

if ((git rev-parse --abbrev-ref HEAD) -ne 'main') { throw 'Placez-vous sur la branche main avant de déployer.' }
if (git status --porcelain) { throw 'Des modifications ne sont pas commitées : git add -A puis git commit -m "..." avant de déployer.' }
if (-not (Test-Path $cle)) { throw "Clé SSH introuvable : $cle" }

if ($Tests) {
    Write-Host 'Tests...'
    php artisan test
    if ($LASTEXITCODE -ne 0) { throw 'Les tests échouent : déploiement annulé.' }
}

Write-Host 'Envoi sur GitHub...'
git push origin main
if ($LASTEXITCODE -ne 0) { throw 'git push a échoué.' }

Write-Host 'Mise à jour du serveur...'
ssh -i $cle -o IdentitiesOnly=yes $serveur 'bash ~/perlecasa/deploiement/mettre-a-jour.sh'
if ($LASTEXITCODE -ne 0) { throw 'La mise à jour du serveur a échoué (voir les messages ci-dessus).' }

Write-Host 'Déploiement terminé : https://perlecasa.com'
