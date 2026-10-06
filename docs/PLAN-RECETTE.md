# Plan de recette — Perle Casa Immobilier (service Finance)

Application : Laravel 12, Filament 3.3, Livewire 3, Reverb (WebSocket), MySQL.
Objectif : valider la conformité fonctionnelle et la sécurité avant la mise en production.

Mode d'emploi : chaque ligne se coche dans la colonne « OK » pendant la recette. Les points marqués **[auto]** sont couverts par la suite Pest (`php artisan test`) ; les autres se vérifient à la main sur l'environnement de préproduction, configuré comme la production.

Rôles de test : administrateur (tout), comptable (finance sans administration), caissier (tableau de bord et opérations de caisse uniquement).

---

## Partie 1 — Conformité et sécurité de l'écosystème Laravel

### 1.1 Environnement et configuration

| ID | Catégorie | Point à vérifier | Risque si ignoré | OK |
|---|---|---|---|---|
| ENV-01 | Environnement | `APP_ENV=production` dans le `.env` du serveur | Comportements de développement actifs (pages d'erreur détaillées, outils de debug) | ☐ |
| ENV-02 | Environnement | `APP_DEBUG=false` | Affichage des traces, requêtes SQL, variables d'environnement et secrets en cas d'erreur | ☐ |
| ENV-03 | Environnement | `APP_KEY` générée sur le serveur (`php artisan key:generate`), unique, jamais commitée ni partagée entre environnements | Déchiffrement des cookies et sessions, falsification de données signées | ☐ |
| ENV-04 | Environnement | `APP_URL` en `https://` avec le vrai domaine | Liens, redirections et URL signées vers HTTP ou localhost | ☐ |
| ENV-05 | Environnement | `.env` absent du dépôt (`.gitignore`) et hors de la racine web (seul `public/` est exposé par le serveur web) | Fuite de tous les secrets (base, Reverb, mails) | ☐ |
| ENV-06 | Environnement | `LOG_LEVEL=warning` (ou `error`), canal `daily` avec rétention | Journaux volumineux contenant des données personnelles ; disque saturé | ☐ |
| ENV-07 | Environnement | Droits : `storage/` et `bootstrap/cache/` inscriptibles par le serveur web uniquement ; aucun fichier en 777 | Écriture ou exécution de fichiers par un tiers | ☐ |
| ENV-08 | Environnement | Aucun outil de debug en production (Telescope, Debugbar, Ignition exposé) — `composer install --no-dev` | Exposition des requêtes, sessions et données métier | ☐ |
| ENV-09 | Environnement | Mots de passe d'amorçage (`ADMIN_PASSWORD`, `DEMO_PASSWORD`) changés après installation ; comptes de démonstration supprimés ou désactivés | Connexion avec des identifiants connus | ☐ |
| ENV-10 | Environnement | Données de démonstration (`DemoSeeder`) non lancées en production | Fausses opérations dans la comptabilité réelle | ☐ |

### 1.2 Formulaires et requêtes

| ID | Catégorie | Point à vérifier | Risque si ignoré | OK |
|---|---|---|---|---|
| REQ-01 | CSRF | Middleware `VerifyCsrfToken` actif dans le groupe `web` ; formulaires Blade avec `@csrf` ; Livewire envoie le jeton sur chaque requête **[auto]** | Actions exécutées à l'insu d'un utilisateur connecté (CSRF) | ☐ |
| REQ-02 | CSRF | Aucune route exclue de la vérification CSRF sans justification | Contournement de la protection sur ces routes | ☐ |
| REQ-03 | Validation | Toute saisie est validée côté serveur (règles des champs Filament, `ControleFinance`, Form Requests pour les éventuels contrôleurs) ; la validation navigateur n'est qu'un confort | Données incohérentes ou malveillantes en base | ☐ |
| REQ-04 | Validation | Montants : numériques, strictement positifs, plafonnés ; dates : pas dans le futur pour les opérations réalisées **[auto]** | Fausses écritures, soldes négatifs | ☐ |
| REQ-05 | Affectation de masse | Tous les modèles définissent `$fillable` (jamais `$guarded = []`) ; `role`, `actif` et `cree_par` ne sont modifiables que depuis les écrans prévus | Élévation de privilèges par ajout de champs à la requête | ☐ |
| REQ-06 | Injection SQL | Requêtes via Eloquent / Query Builder avec liaisons de paramètres ; les `whereRaw`/`selectRaw`/`DB::raw` ne contiennent aucune donnée utilisateur concaténée | Lecture ou destruction de la base | ☐ |
| REQ-07 | Injection SQL | Recherche des tableaux (`like "%…%"`) passe par des paramètres liés **[auto]** | Injection via le champ de recherche | ☐ |
| REQ-08 | Identifiants | Les identifiants présents dans l'URL (`/ventes/{vente}/facture`…) et dans les requêtes Livewire sont revérifiés côté serveur **[auto]** | Accès aux pièces d'un autre périmètre (IDOR) | ☐ |
| REQ-09 | Double soumission | Boutons désactivés pendant l'envoi ; numérotation (`Numerotation::suivant`) atomique | Doublons d'encaissements, numéros en double | ☐ |

### 1.3 Authentification et autorisations

| ID | Catégorie | Point à vérifier | Risque si ignoré | OK |
|---|---|---|---|---|
| AUTH-01 | Hachage | Mots de passe hachés (cast `hashed`, bcrypt) ; jamais stockés ni journalisés en clair | Vol de tous les comptes en cas de fuite de la base | ☐ |
| AUTH-02 | Mot de passe | Politique : 10 caractères minimum, majuscules et minuscules, chiffres, vérification de fuite (`Password::defaults()`) **[auto]** | Comptes devinables par force brute ou dictionnaire | ☐ |
| AUTH-03 | Force brute | Connexion limitée à 5 tentatives par minute **[auto]** | Attaque par dictionnaire sur la page de connexion | ☐ |
| AUTH-04 | Comptes | Un compte désactivé (`actif = false`) ne peut ni se connecter ni continuer à naviguer avec une session ouverte **[auto]** | Ancien employé toujours actif | ☐ |
| AUTH-05 | Session | Session régénérée à la connexion, invalidée à la déconnexion ; durée (`SESSION_LIFETIME`) adaptée | Fixation ou vol de session | ☐ |
| AUTH-06 | Session | `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax` | Lecture ou vol du cookie de session | ☐ |
| AUTH-07 | Autorisations | Une **Policy** par modèle (`app/Policies`) : voir, créer, modifier, supprimer selon `User::accede()` **[auto]** | Actions possibles par appel direct même si le bouton est masqué | ☐ |
| AUTH-08 | Autorisations | Règles fines : le caissier ne voit, ne modifie et ne supprime aucune opération hors caisse ; un administrateur ne peut pas se supprimer ni se désactiver **[auto]** | Fraude interne, perte du dernier administrateur | ☐ |
| AUTH-09 | Autorisations | Écran Utilisateurs réservé à l'administrateur **[auto]** | Création de comptes ou changement de rôle par un non-administrateur | ☐ |
| AUTH-10 | Panel | `canAccessPanel()` vérifie `actif` | Accès au panneau par un compte désactivé | ☐ |
| AUTH-11 | Temps réel | Canal privé `App.Models.User.{id}` autorisé uniquement pour son propriétaire (`routes/channels.php`) **[auto]** | Lecture des notifications d'un autre utilisateur | ☐ |

### 1.4 Protection XSS

| ID | Catégorie | Point à vérifier | Risque si ignoré | OK |
|---|---|---|---|---|
| XSS-01 | Blade | Affichage par `{{ }}` (échappé) ; aucun `{!! !!}` sur une donnée saisie (`rg "\{!!" resources/views`) | Exécution de JavaScript dans le navigateur des collègues | ☐ |
| XSS-02 | HTML construit en PHP | Les `HtmlString` (`Avatar`, `Jauge`) échappent chaque valeur avec `e()` **[auto]** | Injection via un nom de client ou de fournisseur | ☐ |
| XSS-03 | Filament | Aucune colonne `->html()` ni `->markdown()` sur une donnée libre sans échappement | Injection dans les tableaux | ☐ |
| XSS-04 | Notifications | Titre et corps des notifications (cloche, pop-ups) échappés **[auto]** | Injection propagée en temps réel à tous les utilisateurs | ☐ |
| XSS-05 | Excel | Les cellules commençant par `=`, `+`, `-`, `@` ne sont pas interprétées comme formules dans les exports | Injection de formules (CSV/Excel injection) | ☐ |
| XSS-06 | En-têtes | `Content-Security-Policy` en place (scripts limités au domaine, Livewire, Alpine, Reverb) | Pas de seconde barrière si un échappement manque | ☐ |

### 1.5 Fichiers téléversés

| ID | Catégorie | Point à vérifier | Risque si ignoré | OK |
|---|---|---|---|---|
| FILE-01 | Type | Liste blanche PDF, JPEG, PNG, WEBP contrôlée sur le **contenu réel** (type MIME détecté), pas seulement l'extension **[auto]** | Dépôt d'un script PHP déguisé | ☐ |
| FILE-02 | Type | SVG, HTML, PHP, exécutables refusés **[auto]** | XSS stocké via SVG, exécution de code | ☐ |
| FILE-03 | Taille | 5 Mo maximum ; `upload_max_filesize` et `post_max_size` PHP cohérents **[auto]** | Saturation du disque ou de la mémoire | ☐ |
| FILE-04 | Stockage | Disque privé (`storage/app`), jamais sous `public/` ; téléchargement uniquement via la route contrôlée `decaissements.piece` | Accès direct aux justificatifs par URL | ☐ |
| FILE-05 | Nom | Nom de fichier régénéré aléatoirement ; le nom d'origine n'est jamais utilisé comme chemin | Traversée de répertoire (`../../.env`), écrasement de fichiers | ☐ |
| FILE-06 | Serveur | Exécution PHP interdite dans `storage/` et dans tout dossier d'upload (configuration du serveur web) | Exécution d'un fichier déposé | ☐ |

### 1.6 Gestion des erreurs et journaux

| ID | Catégorie | Point à vérifier | Risque si ignoré | OK |
|---|---|---|---|---|
| ERR-01 | Erreurs | Pages 403, 404, 419, 500 génériques, sans trace ni nom de fichier **[auto]** | Cartographie de l'application par un attaquant | ☐ |
| ERR-02 | Erreurs | Les exceptions sont journalisées (`storage/logs`) et non affichées | Perte d'information pour le support, ou fuite si affichée | ☐ |
| ERR-03 | Journaux | Aucun mot de passe, jeton ou clé dans les journaux | Fuite de secrets par les journaux | ☐ |
| ERR-04 | Journaux | `storage/logs` non accessible depuis le web | Lecture des journaux par URL | ☐ |

### 1.7 Transport et en-têtes HTTP

| ID | Catégorie | Point à vérifier | Risque si ignoré | OK |
|---|---|---|---|---|
| HTTP-01 | TLS | HTTPS forcé (redirection 301), certificat valide, TLS 1.2+ | Interception des identifiants | ☐ |
| HTTP-02 | En-têtes | `Strict-Transport-Security` (HSTS) en production | Rétrogradation vers HTTP | ☐ |
| HTTP-03 | En-têtes | `X-Frame-Options: DENY` et `frame-ancestors 'none'` **[auto]** | Clickjacking | ☐ |
| HTTP-04 | En-têtes | `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy` **[auto]** | Interprétation de fichiers comme scripts ; fuite d'URL internes | ☐ |
| HTTP-05 | WebSocket | Reverb en `wss://` derrière le proxy TLS ; clés Reverb uniquement dans `.env` | Notifications lisibles en clair sur le réseau | ☐ |
| HTTP-06 | Serveur | En-têtes `X-Powered-By` et version du serveur masqués | Ciblage de failles connues | ☐ |

### 1.8 Dépendances

| ID | Catégorie | Point à vérifier | Risque si ignoré | OK |
|---|---|---|---|---|
| DEP-01 | PHP | `composer audit` : aucune vulnérabilité connue | Exploitation de failles publiées (ex. Filament < 3.3.53) | ☐ |
| DEP-02 | JavaScript | `npm audit --omit=dev` : aucune vulnérabilité | Failles dans le code livré au navigateur | ☐ |
| DEP-03 | Versions | Contraintes `composer.json` autorisant les correctifs (`^3.3`, `^12.0`) et `composer.lock` commité | Versions figées vulnérables ou installations différentes entre serveurs | ☐ |

### 1.9 Exploitation

| ID | Catégorie | Point à vérifier | Risque si ignoré | OK |
|---|---|---|---|---|
| OPS-01 | Tâches | Cron `* * * * * php artisan schedule:run` (rappels de charges à 8 h) | Rappels de charges jamais envoyés | ☐ |
| OPS-02 | Temps réel | `php artisan reverb:start` sous superviseur (Supervisor, service Windows) | Notifications non instantanées | ☐ |
| OPS-03 | File d'attente | Worker `php artisan queue:work` supervisé | Tâches en attente jamais traitées | ☐ |
| OPS-04 | Sauvegardes | Sauvegarde quotidienne de la base et de `storage/app/justificatifs`, restauration testée | Perte des justificatifs et de la comptabilité | ☐ |
| OPS-05 | Maintenance | Procédure `php artisan down --secret=…` / `up` documentée | Utilisateurs exposés à un déploiement à moitié terminé | ☐ |

---

## Partie 2 — Scénarios de test pratiques

Format : préconditions, étapes, résultat attendu. « Nominal » = parcours prévu ; « Limite » = erreur de saisie ou tentative de contournement. Une colonne **Pest** indique le test automatisé correspondant (`tests/Feature/SecuriteTest.php`, `ParcoursCompletTest.php`, `AccesTest.php`).

### Cas 1 — Connexion et gestion des comptes

L'application n'a pas d'inscription publique : les comptes sont créés par l'administrateur (écran Utilisateurs).

| ID | Type | Étapes | Résultat attendu | Pest | OK |
|---|---|---|---|---|---|
| C1-N1 | Nominal | Se connecter avec chaque rôle (e-mail et mot de passe valides) | Arrivée sur le tableau de bord ; menu limité aux écrans du rôle | AccesTest | ☐ |
| C1-N2 | Nominal | Se déconnecter puis appuyer sur « Précédent » | Retour à la connexion, aucune page en cache exploitable | — | ☐ |
| C1-N3 | Nominal | Administrateur : créer un comptable avec un mot de passe conforme (ex. `Perle2026Casa!`) | Compte créé, connexion possible, mot de passe haché en base | SecuriteTest | ☐ |
| C1-L1 | Limite | Créer un compte avec `abc`, `motdepasse`, `12345678` | Refus : 10 caractères, casse mixte et chiffres exigés | SecuriteTest | ☐ |
| C1-L2 | Limite | Saisir 6 fois un mauvais mot de passe | Blocage temporaire avec message « Trop de tentatives » | SecuriteTest | ☐ |
| C1-L3 | Limite | Se connecter avec un compte désactivé | Refus « identifiants incorrects » ; aucune information sur l'existence du compte | SecuriteTest | ☐ |
| C1-L4 | Limite | Désactiver un compte pendant que l'utilisateur est connecté, puis qu'il recharge la page | Accès refusé immédiatement | SecuriteTest | ☐ |
| C1-L5 | Limite | Nom de compte `<script>alert(1)</script>` puis `"><img src=x onerror=alert(1)>` | Enregistré et affiché comme texte (tableau, avatar, menu, notifications), aucune alerte | SecuriteTest | ☐ |
| C1-L6 | Limite | E-mail `' OR 1=1 --` et mot de passe quelconque | Erreur de format ou refus ; aucune erreur SQL | SecuriteTest | ☐ |
| C1-L7 | Limite | E-mail déjà utilisé lors d'une création | Refus « déjà utilisé » | — | ☐ |
| C1-L8 | Limite | Laisser l'onglet ouvert au-delà de la durée de session, puis agir | Message « page expirée » (419) et retour à la connexion | — | ☐ |
| C1-L9 | Limite | Administrateur : tenter de se désactiver ou de se supprimer lui-même (y compris par appel Livewire direct) | Interdit | SecuriteTest | ☐ |
| C1-L10 | Limite | Envoyer une requête POST sans jeton CSRF | Réponse 419 | SecuriteTest | ☐ |

### Cas 2 — Saisie de données et envoi de fichier (décaissement et pièce justificative)

| ID | Type | Étapes | Résultat attendu | Pest | OK |
|---|---|---|---|---|---|
| C2-N1 | Nominal | Caissier : créer une opération de caisse avec un PDF de 200 Ko | Enregistrée « justifiée », numéro DEC-AAAA-NNNN, notification aux comptables | AlertesTest | ☐ |
| C2-N2 | Nominal | Opération sans pièce, puis « Justifier » avec une image JPEG | Passe de « non justifiée » à « justifiée », badge rouge du menu décrémenté | ParcoursCompletTest | ☐ |
| C2-N3 | Nominal | Télécharger la pièce depuis le tableau | Fichier reçu avec le nom `DEC-…-justificatif.pdf` | ParcoursCompletTest | ☐ |
| C2-L1 | Limite | Soumettre le formulaire vide | Messages « champ obligatoire », rien en base | — | ☐ |
| C2-L2 | Limite | Montant `-500`, `0`, `abc`, `1e99` | Refus | — | ☐ |
| C2-L3 | Limite | Montant supérieur au solde de la caisse | Refus par `ControleFinance` | ReglesFinanceTest | ☐ |
| C2-L4 | Limite | Téléverser `shell.php` | Refus (type non autorisé) | SecuriteTest | ☐ |
| C2-L5 | Limite | Téléverser `shell.php.pdf` ou `facture.pdf` contenant `<?php system($_GET['c']); ?>` | Refus (contenu non PDF) | SecuriteTest | ☐ |
| C2-L6 | Limite | Téléverser une image contenant du PHP dans ses métadonnées | Acceptée comme image mais jamais exécutable : stockée hors web, servie en téléchargement | — | ☐ |
| C2-L7 | Limite | Téléverser `dessin.svg` contenant `<script>` | Refus | SecuriteTest | ☐ |
| C2-L8 | Limite | Téléverser un PDF de 6 Mo | Refus (5 Mo maximum) | SecuriteTest | ☐ |
| C2-L9 | Limite | Fichier nommé `../../.env` | Nom régénéré, enregistré dans `justificatifs/`, aucun fichier écrasé | SecuriteTest | ☐ |
| C2-L10 | Limite | Double clic rapide sur « Créer » | Une seule opération créée | — | ☐ |
| C2-L11 | Limite | Motif `<img src=x onerror=alert(1)>` | Affiché comme texte dans le tableau et la notification | SecuriteTest | ☐ |
| C2-L12 | Limite | Fournisseur dont la raison sociale commence par `=HYPERLINK(...)`, puis télécharger le bon de commande Excel | Texte affiché tel quel, aucune formule exécutée | — | ☐ |

### Cas 3 — Pages réservées et administration

| ID | Type | Étapes | Résultat attendu | Pest | OK |
|---|---|---|---|---|---|
| C3-N1 | Nominal | Comptable : parcourir tous les menus | Ventes, encaissements, décaissements, charges, fournisseurs, bons de commande, comptes, référentiel accessibles ; pas d'Utilisateurs | ParcoursCompletTest | ☐ |
| C3-N2 | Nominal | Caissier : parcourir les menus | Tableau de bord et décaissements (caisse uniquement) | ParcoursCompletTest | ☐ |
| C3-L1 | Limite | Sans session : ouvrir `/utilisateurs`, `/ventes`, `/ventes/1/facture`, `/decaissements/1/piece` | Redirection vers `/login` | ParcoursCompletTest | ☐ |
| C3-L2 | Limite | Caissier : taper `/encaissements`, `/comptes`, `/utilisateurs` dans la barre d'adresse | 403 | AccesTest | ☐ |
| C3-L3 | Limite | Comptable : taper `/utilisateurs` | 403 | AccesTest | ☐ |
| C3-L4 | Limite | Caissier : ouvrir `/ventes/1/facture`, `/encaissements/1/recu`, `/bons-commande/1/imprimer` | 403 | SecuriteTest | ☐ |
| C3-L5 | Limite | Caissier : `/decaissements/{id}/piece` d'un décaissement fournisseur | 403 | SecuriteTest | ☐ |
| C3-L6 | Limite | Caissier : appel Livewire forgé `mountTableAction('edit'|'delete')` sur un décaissement fournisseur | Refusé (action introuvable ou non autorisée), rien modifié | SecuriteTest | ☐ |
| C3-L7 | Limite | Comptable : appel Livewire forgé sur l'écran Utilisateurs | Refusé | SecuriteTest | ☐ |
| C3-L8 | Limite | S'abonner au canal privé d'un autre utilisateur (`/broadcasting/auth`) | 403 | ParcoursCompletTest | ☐ |
| C3-L9 | Limite | Modifier l'identifiant dans l'URL d'une facture vers une vente inexistante (`/ventes/99999/facture`) | 404 sans trace | SecuriteTest | ☐ |
| C3-L10 | Limite | Vérifier les en-têtes d'une page (outils de développement, onglet Réseau) | CSP, X-Frame-Options, nosniff, Referrer-Policy présents | SecuriteTest | ☐ |

---

## Annexe — Commandes à exécuter juste avant le déploiement

À lancer sur le serveur, dans le dossier `application/`, dans cet ordre.

```bash
# 0. Contrôles de sécurité (sur le poste ou en CI)
composer audit                     # vulnérabilités PHP connues (remplace enlightn/security-checker, abandonné)
npm audit --omit=dev               # vulnérabilités JavaScript livrées
php artisan test                   # suite Pest complète (fonctionnel + sécurité)

# 1. Maintenance
php artisan down --secret="jeton-temporaire"

# 2. Dépendances et assets
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build

# 3. Base de données et fichiers
php artisan migrate --force
php artisan storage:link           # uniquement si un disque public est utilisé

# 4. Caches de production
php artisan optimize               # config:cache, route:cache, view:cache, event:cache
php artisan filament:optimize      # composants et icônes Filament
php artisan icons:cache

# 5. Vérifications
php artisan about                  # Environment = production, Debug Mode = OFF, caches = CACHED
php artisan config:show app        # app.debug = false, app.env = production
php artisan route:list --except-vendor   # aucune route sensible sans middleware auth

# 6. Services
php artisan queue:restart
php artisan reverb:restart

# 7. Remise en ligne
php artisan up
```

En cas de problème après le déploiement :

```bash
php artisan optimize:clear         # vide tous les caches (config, routes, vues, événements)
php artisan down                   # remet en maintenance le temps de corriger
```

---

## État de la vérification — 06/10/2026 (poste de développement)

| Contrôle | Résultat |
|---|---|
| `php artisan test` | 98 tests verts, dont 23 dans `SecuriteTest` (XSS, upload, force brute, compte inactif, CSRF, IDOR, Livewire forgé, erreurs 500, en-têtes) |
| `composer audit` | Aucune vulnérabilité connue |
| `npm audit --omit=dev` | 0 vulnérabilité |
| `php artisan optimize` | Caches config, routes, vues, événements, icônes et Filament générés sans erreur (puis vidés avec `optimize:clear` sur le poste de dev) |
| `route:list --except-vendor` | Toutes les routes métier passent par l'authentification ; `storage/{path}` (disque local) refuse tout accès sans URL signée (403 vérifié) |
| CSP dans le navigateur | Livewire, Alpine, polices Bunny et écran de chargement fonctionnels sur `/login` |

Corrections apportées pendant la recette :

- **Upload** : un fichier PHP renommé en `.pdf` était accepté (type déduit de l'extension). Le contenu est désormais contrôlé avec `finfo`, l'extension est vérifiée, et le fichier est enregistré sous un nom ULID avec l'extension du type réel (`App\Filament\Support\PieceJustificative`).
- **Autorisations** : policies ajoutées pour tous les modèles (`app/Policies`) ; le caissier ne peut ni voir, ni modifier, ni supprimer un décaissement hors caisse, ni modifier ses propres opérations après saisie.
- **Mots de passe** : `Password::defaults()` (10 caractères, casse mixte, chiffres, vérification de fuite en production) appliqué à l'écran Utilisateurs.
- **En-têtes** : middleware `EntetesSecurite` (CSP, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, COOP, HSTS en production).
- **Notifications** : le texte saisi est échappé avant d'être placé dans le corps des notifications.
- **Production** : modèle `.env.production.example` (debug coupé, cookies sécurisés, session chiffrée, Reverb en `wss`, journaux quotidiens au niveau warning).

Limite connue : la CSP autorise `'unsafe-inline'` et `'unsafe-eval'` pour les scripts, car Alpine évalue ses expressions et Filament/Livewire injectent des scripts en ligne. Elle bloque toutefois les scripts et connexions vers des domaines tiers, l'intégration en iframe et les plugins.

Les points manuels (ENV, HTTP-01/05/06, OPS, FILE-06) restent à cocher sur le serveur de préproduction.

Outils complémentaires recommandés : scan OWASP ZAP (mode « baseline ») sur la préproduction, [securityheaders.com](https://securityheaders.com) et [SSL Labs](https://www.ssllabs.com/ssltest/) sur le domaine final.
