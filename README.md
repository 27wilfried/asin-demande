# API de suivi des demandes d'actes — Étude de cas DEP/ASIN 2026

API REST qui permet aux usagers de déposer des demandes d'actes administratifs (acte de naissance, casier judiciaire, certificat de résidence) et aux agents de les traiter selon leur cycle de vie.

**Stack :** PHP 8.2+, Laravel 12, base SQLite (aucun serveur de base de données à installer), PHPUnit pour les tests.

---

## 1. Installation et démarrage

### Prérequis

- PHP 8.2 ou plus, avec les extensions `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, `fileinfo`
- Composer 2

Vérification : `php -v` et `composer -V`.

### Installation rapide (une seule commande)

```bash
git clone https://github.com/27wilfried/asin-demande.git demandes-actes
cd demandes-actes

composer setup                    # installe les dépendances, crée .env, la clé, la base SQLite et les données de démo
php artisan serve                 # démarre l'application sur http://127.0.0.1:8000
```

`composer setup` remet la base à zéro à chaque exécution (`migrate:fresh --seed`).

### Installation pas à pas (équivalent)

```bash
git clone https://github.com/27wilfried/asin-demande.git demandes-actes
cd demandes-actes

composer install
cp .env.example .env              # Windows (PowerShell) : copy .env.example .env
php artisan key:generate

# Création du fichier de base SQLite
touch database/database.sqlite    # Windows (PowerShell) : New-Item database\database.sqlite

php artisan migrate --seed        # crée les tables et des données de démonstration
php artisan serve                 # démarre l'application sur http://127.0.0.1:8000
```

L'API répond alors sous `http://127.0.0.1:8000/api/...` : ouvrir `http://127.0.0.1:8000/api` dans un navigateur affiche la liste des routes disponibles, avec des liens d'exemple. L'écran est disponible sur `http://127.0.0.1:8000` : il affiche la liste des demandes d'un usager (chargée directement avec le NPI de démonstration), avec le nombre de demandes par statut sous forme de cartes cliquables qui filtrent la liste, la pagination et un skeleton loader pendant les chargements. Des boutons permettent aussi de faire avancer chaque demande dans son cycle de vie (prendre en charge, valider, rejeter avec motif). Les messages d'erreur de l'API y sont affichés tels quels.

### Données de démonstration

La commande `--seed` crée :

- pour l'usager **NPI `1234567890`** : 3 demandes déposées, 2 en cours, 1 validée et 1 rejetée, à des dates différentes, ce qui permet de vérifier le tri ;
- pour l'usager **NPI `0987654321`** : 2 demandes déposées ;
- pour l'usager **NPI `1111111111`** : 25 demandes déposées, pour voir la pagination (20 en page 1, 5 en page 2). Les 7 demandes de l'usager `1234567890` tiennent sur une seule page de 20 : pour paginer ce dernier, choisir 5 par page à l'écran, ou utiliser `?par_page=5` dans l'API.

Pour repartir d'une base vide : `php artisan migrate:fresh`. Pour la remettre avec les données de démonstration : `php artisan migrate:fresh --seed`.

### Lancer les tests automatisés

```bash
php artisan test
```

Les tests utilisent une base SQLite en mémoire et ne touchent pas aux données locales.

---

## 2. Description de l'API

Toutes les routes sont préfixées par `/api`. Les échanges se font en JSON. L'en-tête `Accept: application/json` est recommandé, mais il n'est pas obligatoire : les erreurs des routes `/api` sont toujours renvoyées en JSON.

| Méthode | Route | Rôle |
|---|---|---|
| `GET` | `/api` | Point d'entrée : liste des routes disponibles et liens d'exemple |
| `POST` | `/api/demandes` | Déposer une demande |
| `GET` | `/api/usagers/{npi}/demandes` | Demandes d'un usager, de la plus récente à la plus ancienne, avec filtre facultatif `?statut=` |
| `PATCH` | `/api/demandes/{id}/statut` | Faire avancer une demande dans son cycle de vie |
| `GET` | `/api/demandes/{id}` | Détail d'une demande |
| `GET` | `/api/demandes` | Toutes les demandes (vue agent), avec filtres facultatifs `?npi=` et `?statut=` |
| `GET` | `/api/statistiques` | Nombre de demandes par statut, avec filtre facultatif `?npi=` (bonus) |

### Valeurs acceptées

- **`type_acte`** : `acte_naissance`, `casier_judiciaire`, `certificat_residence`
- **`statut`** : `deposee`, `en_cours`, `validee`, `rejetee`

### 2.1 Déposer une demande

`POST /api/demandes`

```json
{ "npi": "1234567890", "type_acte": "casier_judiciaire", "nombre_copies": 2 }
```

Réponse **201 Created** :

```json
{
  "data": {
    "id": 10,
    "npi": "1234567890",
    "type_acte": "casier_judiciaire",
    "type_acte_libelle": "Casier judiciaire",
    "nombre_copies": 2,
    "statut": "deposee",
    "statut_libelle": "Déposée",
    "motif_rejet": null,
    "transitions_possibles": ["en_cours"],
    "traitee_le": null,
    "cree_le": "2026-10-06T11:00:00+00:00",
    "modifie_le": "2026-10-06T11:00:00+00:00"
  }
}
```

Le statut est toujours imposé à `deposee` par le serveur. Un champ `statut` envoyé par le client est ignoré.

### 2.2 Consulter les demandes d'un usager

`GET /api/usagers/1234567890/demandes`
`GET /api/usagers/1234567890/demandes?statut=en_cours`
`GET /api/usagers/1111111111/demandes?page=2`
`GET /api/usagers/1234567890/demandes?page=2&par_page=5`

Paramètres facultatifs : `statut`, `page` (1 par défaut) et `par_page` (20 par défaut, 20 au maximum). Une page au-delà de la dernière renvoie une liste `data` vide.

Réponse **200** : `data` contient les demandes, triées de la plus récente à la plus ancienne ; `meta` contient `current_page`, `last_page`, `per_page` et `total` ; `links` contient les URL des pages.

### 2.3 Faire avancer le traitement

`PATCH /api/demandes/{id}/statut`

```json
{ "statut": "en_cours" }
```

```json
{ "statut": "validee" }
```

```json
{ "statut": "rejetee", "motif": "Pièce justificative illisible." }
```

Réponse **200** : la demande mise à jour. Le champ `transitions_possibles` indique les actions encore possibles.

### 2.4 Statistiques (bonus)

`GET /api/statistiques` ou `GET /api/statistiques?npi=1234567890`

```json
{ "data": { "total": 7, "par_statut": { "deposee": 3, "en_cours": 2, "validee": 1, "rejetee": 1 } } }
```

### 2.5 Codes d'erreur

Chaque erreur renvoie un champ `message` clair, en français. Le JSON est renvoyé avec les accents en clair (`"Déposée"` et non `"Déposée"`).

| Code | Cas | Exemple de réponse |
|---|---|---|
| `422` | Saisie invalide (NPI, type d'acte, nombre de copies, statut, rejet sans motif, pagination) | `{"message": "Les données envoyées sont invalides.", "errors": {"npi": ["Le NPI doit comporter exactement 10 chiffres."]}}` |
| `409` | Action interdite par le cycle de vie | `{"message": "Action interdite : une demande « Déposée » ne peut pas passer à « Validée ». Statut(s) possible(s) : « En cours de traitement ».", "statut_actuel": "deposee", "transitions_possibles": ["en_cours"]}` |
| `404` | Demande ou route inexistante | `{"message": "Demande introuvable."}` |
| `405` | Méthode HTTP non prévue sur la route | `{"message": "Méthode HTTP non autorisée pour cette route."}` |

### 2.6 Scénario de test rapide (curl)

Sous Windows PowerShell, utilisez `curl.exe` (et non `curl`, qui est un alias d'une autre commande), ou un outil comme Postman. Si les accents s'affichent mal dans la console, passez-la en UTF-8 avec `chcp 65001`.

```bash
# 1. Déposer une demande
curl -X POST http://127.0.0.1:8000/api/demandes -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"npi\":\"1112223334\",\"type_acte\":\"acte_naissance\",\"nombre_copies\":3}"

# 2. Saisie invalide (NPI de 9 chiffres, 6 copies)  -> 422
curl -X POST http://127.0.0.1:8000/api/demandes -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"npi\":\"111222333\",\"type_acte\":\"acte_naissance\",\"nombre_copies\":6}"

# 3. Lister les demandes de l'usager
curl http://127.0.0.1:8000/api/usagers/1112223334/demandes

# 4. Valider directement une demande déposée  -> 409 (interdit)
curl -X PATCH http://127.0.0.1:8000/api/demandes/1/statut -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"statut\":\"validee\"}"

# 5. Passer en cours, puis rejeter sans motif (-> 422), puis avec motif (-> 200)
curl -X PATCH http://127.0.0.1:8000/api/demandes/1/statut -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"statut\":\"en_cours\"}"
curl -X PATCH http://127.0.0.1:8000/api/demandes/1/statut -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"statut\":\"rejetee\"}"
curl -X PATCH http://127.0.0.1:8000/api/demandes/1/statut -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"statut\":\"rejetee\",\"motif\":\"Document illisible\"}"
```

Remplacez `1` par l'`id` renvoyé à l'étape 1.

---

## 3. Modèle de données et règles de gestion

### Table `demandes`

| Colonne | Type | Description |
|---|---|---|
| `id` | entier auto-incrémenté | Identifiant de la demande |
| `npi` | texte (10) | NPI de l'usager, stocké en texte pour garder les zéros en tête |
| `type_acte` | texte | `acte_naissance`, `casier_judiciaire` ou `certificat_residence` |
| `nombre_copies` | entier | De 1 à 5 |
| `statut` | texte | `deposee` par défaut |
| `motif_rejet` | texte, nullable | Obligatoire en cas de rejet |
| `traitee_le` | date, nullable | Date de la validation ou du rejet |
| `created_at`, `updated_at` | dates | Dates de dépôt et de dernière modification |

Index : `(npi, created_at)` pour la liste d'un usager triée par date, et `statut` pour le filtre et les statistiques.

### Cycle de vie

```
deposee ──► en_cours ──► validee   (final)
                     └─► rejetee   (final, motif obligatoire)
```

Toute autre transition est refusée avec un code 409, et une demande validée ou rejetée ne peut plus changer de statut.

### Règles de gestion et leur mise en œuvre

| Règle | Implémentation |
|---|---|
| Le NPI comporte exactement 10 chiffres | `regex:/^\d{10}$/` dans `StoreDemandeRequest` et `ListeDemandesRequest` |
| Le type d'acte fait partie des 3 types cités | Enum `TypeActe` et règle `in` |
| Le nombre de copies est compris entre 1 et 5 | `numeric`, `integer` et `between:1,5` (`numeric` refuse `true`, que `integer` seul accepterait comme 1) |
| Statut initial « déposée » | Imposé par `DemandeService::deposer()` |
| Cycle de vie | Défini à un seul endroit : `StatutDemande::transitionsPossibles()`, appliqué par `DemandeService::changerStatut()` |
| Un rejet doit toujours être motivé | `required_if` dans `ChangerStatutRequest`, avec un double contrôle dans le service |
| Message clair pour une saisie invalide ou une action interdite | Réponses JSON 422, 409 et 404 configurées dans `bootstrap/app.php` et `TransitionInterditeException` |

---

## 4. Organisation du code

```
app/
├── Enums/
│   ├── TypeActe.php                 # types d'actes et libellés
│   └── StatutDemande.php            # statuts et cycle de vie (transitions autorisées)
├── Exceptions/
│   └── TransitionInterditeException.php   # action interdite -> HTTP 409
├── Http/
│   ├── Controllers/
│   │   ├── AccueilApiController.php # GET /api : liste des routes
│   │   ├── DemandeController.php    # endpoints des demandes (contrôleur fin)
│   │   └── StatistiqueController.php
│   ├── Middleware/
│   │   └── JsonLisible.php          # JSON avec accents en clair
│   ├── Requests/                    # validation des entrées et messages d'erreur
│   │   ├── StoreDemandeRequest.php
│   │   ├── ListeDemandesRequest.php
│   │   └── ChangerStatutRequest.php
│   └── Resources/
│       └── DemandeResource.php      # format JSON de sortie
├── Models/Demande.php
└── Services/DemandeService.php      # règles métier (dépôt, changement de statut)
bootstrap/app.php                    # routes API et format JSON des erreurs
database/
├── migrations/..._create_demandes_table.php
├── factories/DemandeFactory.php
└── seeders/DatabaseSeeder.php       # données de démonstration
resources/views/demandes.blade.php   # écran : consultation et traitement (bonus)
routes/api.php                       # routes de l'API
routes/web.php                       # route de l'écran
tests/
├── Feature/DemandeApiTest.php       # règles de gestion testées via l'API HTTP
└── Unit/StatutDemandeTest.php       # cycle de vie : toutes les transitions statut -> statut
```

### Choix de conception

- **Contrôleur fin, service métier** : la validation du format est dans les FormRequest, et les règles du cycle de vie sont dans `DemandeService`. Le contrôleur ne fait qu'orchestrer.
- **Enums PHP** pour les types et les statuts : les valeurs possibles et les transitions sont définies à un seul endroit, ce qui évite les chaînes de caractères dispersées dans le code.
- **Une seule route de changement de statut** (`PATCH /demandes/{id}/statut`) : le serveur décide si la transition est autorisée. Pour ajouter un statut, il suffit de modifier l'enum.
- **422 et 409 bien distingués** : 422 signale une donnée mal formée, 409 une action incompatible avec l'état actuel de la demande.
- **Verrou de ligne** (`lockForUpdate`) dans une transaction lors du changement de statut, pour que deux agents ne puissent pas traiter la même demande en même temps.
- **SQLite** pour que le jury puisse démarrer l'application sans installer de serveur de base de données. Le passage à MySQL ou PostgreSQL se fait uniquement par le fichier `.env`.

---

## 5. État d'avancement

### Ce qui fonctionne

**Socle obligatoire**

- Dépôt d'une demande avec NPI, type d'acte et nombre de copies, identifiant et statut « déposée »
- Consultation des demandes d'un usager, de la plus récente à la plus ancienne, avec filtre facultatif par statut
- Avancement du traitement selon le cycle de vie, avec motif obligatoire en cas de rejet et blocage des statuts finaux
- Refus des saisies invalides et des actions interdites, avec un message clair

**Bonus**

- Pagination de la liste, 20 demandes par page au maximum
- Nombre de demandes par statut (`/api/statistiques`)
- Tests automatisés des règles de gestion (`php artisan test`) : 46 tests, dont les 16 combinaisons de transitions du cycle de vie
- Écran simple (`http://127.0.0.1:8000`) : liste des demandes d'un usager, avec statistiques par statut mises en avant, filtre, pagination, skeleton loader et boutons de traitement. Le dépôt se fait par l'API (`POST /api/demandes`), comme le demande le sujet

### Ce qui manque, et pourquoi

Ces points sortent du périmètre demandé ou n'ont pas pu être traités dans le temps imparti de 1 h 30 :

- **Authentification et rôles (usager / agent)** : le sujet ne la demande pas. En production, il faudrait protéger les routes, par exemple avec Laravel Sanctum, pour qu'un usager ne voie que ses propres demandes et que seul un agent puisse changer un statut.
- **Historique des changements de statut** (qui a fait quoi et quand) : seule la date de décision finale (`traitee_le`) est conservée. Une table `historique_statuts` serait l'étape suivante pour la traçabilité.
- **Documentation OpenAPI / Swagger** : l'API est documentée dans ce README.
- **Traduction des libellés de pagination** : les liens `meta.links` gardent les libellés par défaut de Laravel (« Previous », « Next »). Les fichiers de langue français n'ont pas été ajoutés, car tous les messages d'erreur métier sont déjà rédigés en français dans les FormRequest.
