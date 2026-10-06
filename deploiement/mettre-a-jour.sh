#!/bin/bash
# Met à jour Perle Casa Immobilier sur le VPS depuis GitHub (git pull, dépendances, assets, migrations, caches).
# Usage (compte gmsweb) : bash ~/perlecasa/deploiement/mettre-a-jour.sh
# Lancé depuis le PC par deploiement/deployer.ps1.
set -euo pipefail

# Tout le script est dans une fonction : git reset peut réécrire ce fichier pendant l'exécution.
principal() {
    [ "$(id -un)" = "gmsweb" ] || { echo "Lancez ce script sous le compte gmsweb (pas $(id -un))."; exit 1; }

    local APP="$HOME/perlecasa"
    local PHP="${PHP_BIN:-/opt/cpanel/ea-php83/root/usr/bin/php}"
    local COMPOSER="${COMPOSER_BIN:-$HOME/bin/composer}"
    cd "$APP"

    command -v npm >/dev/null || { echo "npm introuvable pour gmsweb : installez Node.js (dnf module install nodejs:22)."; exit 1; }

    mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
        storage/logs storage/app/private/justificatifs storage/app/public bootstrap/cache
    chmod -R u+rwX,go-rwx storage bootstrap/cache

    if [ ! -f .env ]; then
        cp .env.production.example .env
        chmod 600 .env
        echo
        echo "Première installation : complétez $APP/.env (base MySQL, ADMIN_EMAIL, ADMIN_PASSWORD, REVERB_*),"
        echo "puis relancez le déploiement."
        exit 0
    fi

    local deja_installe=0
    [ -d vendor ] && deja_installe=1
    [ "$deja_installe" = 1 ] && "$PHP" artisan down --retry=30 || true
    trap '"$PHP" artisan up >/dev/null 2>&1 || true' EXIT

    echo "Récupération du code (origin/main)..."
    git fetch --quiet origin main
    git reset --hard --quiet origin/main
    git log --oneline -1

    echo "Dépendances PHP..."
    "$PHP" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --no-progress

    echo "Compilation des assets..."
    npm ci --no-audit --no-fund --loglevel=error
    npm run build
    rm -f public/hot

    grep -q '^APP_KEY=base64:' .env || "$PHP" artisan key:generate --force

    "$PHP" artisan migrate --force

    if [ ! -f storage/app/.installe ]; then
        "$PHP" artisan db:seed --force
        touch storage/app/.installe
        echo "Administrateur créé. Videz ADMIN_PASSWORD dans .env après votre première connexion."
    fi

    "$PHP" artisan optimize
    "$PHP" artisan filament:optimize
    "$PHP" artisan icons:cache
    "$PHP" artisan queue:restart
    "$PHP" artisan reverb:restart || true
    "$PHP" artisan up

    echo
    "$PHP" artisan about --only=environment
    echo "Déploiement terminé."
}

principal "$@"
exit
