#!/bin/bash
# Installe ou met à jour Perle Casa Immobilier dans ~/perlecasa (domaine supplémentaire perlecasa.com du compte
# gmsweb, racine ~/perlecasa/public). Ne touche jamais à public_html, qui sert groupemondial-gms.com.
# Usage : bash ~/installer.sh [chemin/vers/perlecasa.tar.gz]
set -euo pipefail

[ "$(id -un)" = "gmsweb" ] || { echo "Lancez ce script sous le compte gmsweb (pas $(id -un))."; exit 1; }

APP="$HOME/perlecasa"
ARCHIVE="${1:-$HOME/perlecasa.tar.gz}"

PHP=""
for candidat in /opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php /opt/cpanel/ea-php82/root/usr/bin/php "$(command -v php || true)"; do
    if [ -n "$candidat" ] && [ -x "$candidat" ]; then PHP="$candidat"; break; fi
done
[ -n "$PHP" ] || { echo "PHP introuvable : installez PHP 8.2+ dans WHM > EasyApache 4."; exit 1; }
COMPOSER="${COMPOSER_BIN:-$HOME/bin/composer}"

"$PHP" -r 'exit(version_compare(PHP_VERSION, "8.2.0", ">=") ? 0 : 1);' \
    || { echo "PHP 8.2 ou plus est requis ($PHP est en $("$PHP" -r 'echo PHP_VERSION;'))."; exit 1; }

manquantes=""
for ext in pdo_mysql mbstring xml intl bcmath gd zip fileinfo curl openssl tokenizer ctype; do
    "$PHP" -m | grep -qi "^$ext$" || manquantes="$manquantes $ext"
done
if [ -n "$manquantes" ]; then
    echo "Extensions PHP manquantes :$manquantes (à activer dans WHM > EasyApache 4)."
    exit 1
fi

[ -f "$ARCHIVE" ] || { echo "Archive introuvable : $ARCHIVE"; exit 1; }
mkdir -p "$APP"
[ -f "$APP/artisan" ] && [ -f "$APP/.env" ] && "$PHP" "$APP/artisan" down --retry=30 || true

echo "Extraction de l'archive..."
tar -xzf "$ARCHIVE" -C "$APP"
cd "$APP"

mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
    storage/logs storage/app/private/justificatifs storage/app/public bootstrap/cache
chmod -R u+rwX,go-rwx storage bootstrap/cache

if [ ! -f .env ]; then
    cp .env.production.example .env
    chmod 600 .env
    echo
    echo "Première installation : complétez $APP/.env (domaine, base MySQL, ADMIN_EMAIL, ADMIN_PASSWORD, REVERB_*),"
    echo "puis relancez : bash ~/installer.sh"
    exit 0
fi

echo "Installation des dépendances PHP..."
"$PHP" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction

grep -q '^APP_KEY=base64:' .env || "$PHP" artisan key:generate --force

"$PHP" artisan migrate --force

if [ ! -f storage/app/.installe ]; then
    "$PHP" artisan db:seed --force
    touch storage/app/.installe
    echo "Administrateur créé. Pensez à vider ADMIN_PASSWORD dans .env après votre première connexion."
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
