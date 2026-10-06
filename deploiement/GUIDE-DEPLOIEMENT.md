# Déployer Perle Casa Immobilier sur le VPS OVH (AlmaLinux 9 + cPanel/WHM)

Serveur : `vps-ab096ea2.vps.ovh.net` — IPv4 `51.210.44.174`.

Perle Casa Immobilier est un **domaine supplémentaire** du compte `gmsweb` (licence cPanel à 1 compte), avec son propre dossier, sa propre base et sa propre version de PHP. Le site `groupemondial-gms.com` et son `public_html` ne sont pas touchés. Les deux sites partagent l'utilisateur Linux `gmsweb` : gardez `groupemondial-gms.com` à jour.

- Domaine : `perlecasa.com`
- Application : `/home/gmsweb/perlecasa` (racine web : `/home/gmsweb/perlecasa/public`)
- Base : `gmsweb_perlecasa`, utilisateur MySQL `gmsweb_pcuser`

Toutes les commandes serveur se lancent dans **WHM > Terminal** (root).

---

## 1. Faire pointer le domaine vers le VPS

OVH > `perlecasa.com` > **Zone DNS** :

| Type | Nom | Valeur |
|---|---|---|
| A | `@` | `51.210.44.174` |
| A | `www` | `51.210.44.174` |

Vérification depuis votre PC : `nslookup perlecasa.com 8.8.8.8`.

## 2. Ajouter perlecasa.com au compte gmsweb

```bash
cpapi2 --user=gmsweb AddonDomain addaddondomain newdomain=perlecasa.com subdomain=perlecasa dir=perlecasa/public 2>/dev/null | grep -E "result|reason"
```

`result: 1` = domaine ajouté, avec pour racine `/home/gmsweb/perlecasa/public`.

## 3. PHP 8.3 et extensions

1. WHM > **EasyApache 4** > Personnaliser > Versions PHP : cocher **PHP 8.3** (`ea-php83`).
2. Extensions PHP pour `ea-php83` : `bcmath`, `curl`, `fileinfo`, `gd`, `intl`, `mbstring`, `mysqlnd`/`pdo_mysql`, `xml`, `zip`, `sodium`, `pcntl`, `posix`.
3. Modules Apache : cocher **mod_proxy** et **mod_proxy_wstunnel** (pour Reverb).
4. Provisionner.
5. PHP 8.3 pour `perlecasa.com` uniquement (`groupemondial-gms.com` garde sa version) :

```bash
whmapi1 php_set_vhost_versions version=ea-php83 vhost=perlecasa.com 2>/dev/null | grep -E "result|reason"
```

6. WHM > **MultiPHP INI Editor** > PHP 8.3 : `upload_max_filesize = 8M`, `post_max_size = 10M`, `memory_limit = 256M`, `expose_php = Off`.

## 4. Base de données

```bash
read -rsp "Mot de passe MySQL de Perle Casa : " DBMDP; echo
uapi --user=gmsweb Mysql create_database name=gmsweb_perlecasa | grep -E "status|errors"
uapi --user=gmsweb Mysql create_user name=gmsweb_pcuser password="$DBMDP" | grep -E "status|errors"
uapi --user=gmsweb Mysql set_privileges_on_database user=gmsweb_pcuser database=gmsweb_perlecasa privileges="ALL PRIVILEGES" | grep -E "status|errors"
unset DBMDP
```

Chaque commande doit afficher `status: 1`. Gardez ce mot de passe pour l'étape 7.

## 5. HTTPS

```bash
/usr/local/cpanel/bin/autossl_check --user=gmsweb
```

Ou WHM > cPanel de `gmsweb` > **SSL/TLS Status** > **Run AutoSSL**.

## 6. Préparer et envoyer l'application

Sur votre PC, dossier `application` :

```powershell
powershell -ExecutionPolicy Bypass -File deploiement\preparer-archive.ps1
```

Puis envoyez `deploiement\perlecasa.tar.gz` et `deploiement\installer.sh` dans `/home/gmsweb` :
- soit WHM > Liste des comptes > icône cPanel de `gmsweb` > **Gestionnaire de fichiers** > dossier racine (`/home/gmsweb`) > **Charger** ;
- soit `scp deploiement\perlecasa.tar.gz deploiement\installer.sh root@51.210.44.174:/home/gmsweb/` puis, sur le serveur, `chown gmsweb:gmsweb /home/gmsweb/perlecasa.tar.gz /home/gmsweb/installer.sh`.

## 7. Installation

Premier passage (crée `.env` et s'arrête) :

```bash
su - gmsweb -s /bin/bash -c "bash ~/installer.sh"
```

Générez les clés Reverb, puis complétez `.env` :

```bash
openssl rand -hex 3; openssl rand -hex 20; openssl rand -hex 20
nano /home/gmsweb/perlecasa/.env
```

| Clé | Valeur |
|---|---|
| `DB_DATABASE` | `gmsweb_perlecasa` |
| `DB_USERNAME` | `gmsweb_pcuser` |
| `DB_PASSWORD` | celui de l'étape 4 |
| `ADMIN_EMAIL` / `ADMIN_PASSWORD` | votre compte administrateur (10 caractères min., majuscules, minuscules, chiffres) |
| `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` | les trois valeurs générées, dans l'ordre |
| `SOCIETE_*` | identité imprimée sur les factures |

`Ctrl+O`, `Entrée`, `Ctrl+X`. Puis :

```bash
su - gmsweb -s /bin/bash -c "bash ~/installer.sh"
```

Le script installe les dépendances, génère `APP_KEY`, crée les tables et l'administrateur, met en cache la configuration.

## 8. Tâche planifiée

```bash
(crontab -u gmsweb -l 2>/dev/null; echo '* * * * * cd ~/perlecasa && /opt/cpanel/ea-php83/root/usr/bin/php artisan schedule:run >> /dev/null 2>&1') | crontab -u gmsweb -
crontab -u gmsweb -l
```

## 9. Reverb et file d'attente

```bash
cd /home/gmsweb/perlecasa/deploiement
cp perlecasa-reverb.service perlecasa-queue.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now perlecasa-reverb perlecasa-queue
systemctl status perlecasa-reverb --no-pager

mkdir -p /etc/apache2/conf.d/userdata/ssl/2_4/gmsweb/perlecasa.com
cp reverb-apache.conf /etc/apache2/conf.d/userdata/ssl/2_4/gmsweb/perlecasa.com/reverb.conf
/scripts/rebuildhttpdconf && /scripts/restartsrv_httpd
```

Le port 8080 n'écoute que sur `127.0.0.1` : il n'est pas exposé sur Internet.

## 10. Vérifications

- `https://groupemondial-gms.com` fonctionne toujours.
- `https://perlecasa.com/login` s'ouvre avec le cadenas HTTPS.
- Connexion avec `ADMIN_EMAIL`, puis **videz `ADMIN_PASSWORD`** dans `.env` et lancez `su - gmsweb -s /bin/bash -c "cd ~/perlecasa && /opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache"`.
- Créez les comptes comptable et caissier depuis l'écran Utilisateurs.
- Deux navigateurs (comptable et caissier) : une opération de caisse saisie par le caissier fait sonner la cloche du comptable en moins d'une seconde.

## 11. Mises à jour suivantes

Sur votre PC : `preparer-archive.ps1`, puis envoyez l'archive (étape 6). Sur le serveur :

```bash
su - gmsweb -s /bin/bash -c "bash ~/installer.sh"
```

Le script met le site en maintenance, remplace le code, migre la base, vide les caches et redémarre Reverb et la file d'attente. Le `.env`, les justificatifs et la base ne sont jamais touchés.

## 12. Sauvegardes

WHM > **Configuration de la sauvegarde** : la sauvegarde du compte `gmsweb` inclut `~/perlecasa` (justificatifs compris) et la base `gmsweb_perlecasa`. Testez une restauration une fois.

## En cas de problème

| Symptôme | Où regarder |
|---|---|
| Page blanche ou erreur 500 | `/home/gmsweb/perlecasa/storage/logs/laravel-AAAA-MM-JJ.log` |
| « Extensions PHP manquantes » | Étape 3 (EasyApache 4) |
| Cloche non instantanée | `systemctl status perlecasa-reverb` ; module `mod_proxy_wstunnel` ; `REVERB_*` dans `.env` puis `config:cache` |
| Erreur 419 « page expirée » | site encore en HTTP : lancer AutoSSL (étape 5) |
| Remettre en ligne après un échec | `su - gmsweb -s /bin/bash -c "cd ~/perlecasa && /opt/cpanel/ea-php83/root/usr/bin/php artisan optimize:clear && /opt/cpanel/ea-php83/root/usr/bin/php artisan up"` |
