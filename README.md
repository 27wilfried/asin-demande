# API de suivi des demandes d'actes — Étude de cas DEP/ASIN 2026

API REST qui permet aux usagers de déposer des demandes d'actes administratifs (acte de naissance, casier judiciaire, certificat de résidence) et aux agents de les traiter selon leur cycle de vie.

**Stack :** PHP 8.2+, Laravel 12, base SQLite (aucun serveur de base de données à installer), PHPUnit pour les tests.

**Sommaire :** [1. Installation](#1-installation-et-démarrage) · [2. API](#2-description-de-lapi) · [3. Guide de recette](#3-guide-de-recette) · [4. Modèle et règles](#4-modèle-de-données-et-règles-de-gestion) · [5. Organisation du code](#5-organisation-du-code) · [6. État d'avancement](#6-état-davancement)

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

composer setup                    # dépendances, .env, clé, base SQLite et données de démonstration
php artisan serve                 # démarre l'application sur http://127.0.0.1:8000
```

`composer setup` remet la base à zéro à chaque exécution (`migrate:fresh --seed`). Sur une machine lente, l'installation des dépendances peut prendre plusieurs minutes.

### Installation pas à pas (équivalent)

```bash
git clone https://github.com/27wilfried/asin-demande.git demandes-actes
cd demandes-actes

composer install
cp .env.example .env              # Windows (PowerShell) : copy .env.example .env
php artisan key:generate

# Création du fichier de base SQLite
touch database/database.sqlite    # Windows (PowerShell) : New-Item database\database.sqlite

php artisan migrate --seed        # crée les tables et les données de démonstration
php artisan serve                 # démarre l'application sur http://127.0.0.1:8000
```

### Où tester

| Adresse | Contenu |
|---|---|
| `http://127.0.0.1:8000` | Écran : demandes d'un usager, statistiques, pagination, traitement |
| `http://127.0.0.1:8000/api` | Liste des routes de l'API, avec des liens d'exemple |
| `http://127.0.0.1:8000/api/usagers/1234567890/demandes` | Exemple de réponse de l'API |

### Données de démonstration

`php artisan migrate --seed` (ou `composer setup`) crée toujours les mêmes demandes, avec les mêmes numéros. Le type d'acte et le nombre de copies sont tirés au hasard.

| NPI | Demandes | Sert à tester |
|---|---|---|
| `1234567890` | n° 1, 2, 3 **déposées** (il y a 1, 2 et 3 jours) ; n° 4, 5 **en cours** (il y a 5 jours) ; n° 6 **validée** ; n° 7 **rejetée** (motif « Pièce justificative illisible. ») | tri, filtre par statut, cycle de vie, statistiques |
| `0987654321` | n° 8, 9 **déposées** | les listes de deux usagers ne se mélangent pas |
| `1111111111` | n° 10 à 34 : **25 demandes déposées** (n° 10 la plus récente) | pagination : 20 en page 1, 5 en page 2 |

Pour revenir à cet état initial à tout moment : `php artisan migrate:fresh --seed`.

### Lancer les tests automatisés

```bash
php artisan test
```

Les 59 tests utilisent une base SQLite en mémoire et ne touchent pas aux données locales.

---

## 2. Description de l'API

Toutes les routes sont préfixées par `/api`. Les échanges se font en JSON (en-tête `Content-Type: application/json` pour les requêtes qui envoient un corps). L'en-tête `Accept: application/json` n'est pas obligatoire : les erreurs des routes `/api` sont toujours renvoyées en JSON.

| Méthode | Route | Rôle |
|---|---|---|
| `GET` | `/api` | Point d'entrée : liste des routes disponibles et liens d'exemple |
| `POST` | `/api/demandes` | Déposer une demande |
| `GET` | `/api/usagers/{npi}/demandes` | Demandes d'un usager, de la plus récente à la plus ancienne (`?statut=`, `?page=`, `?par_page=`) |
| `PATCH` | `/api/demandes/{id}/statut` | Faire avancer une demande dans son cycle de vie |
| `GET` | `/api/demandes/{id}` | Détail d'une demande |
| `GET` | `/api/demandes` | Toutes les demandes, vue agent (`?npi=`, `?statut=`, `?page=`, `?par_page=`) |
| `GET` | `/api/statistiques` | Nombre de demandes par statut (`?npi=` facultatif) — bonus |

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
    "id": 35,
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

Le statut est toujours imposé à `deposee` par le serveur : un champ `statut` envoyé par le client est ignoré. Un NPI envoyé comme nombre JSON (`1234567890`) est accepté et converti en texte.

### 2.2 Consulter les demandes d'un usager

```
GET /api/usagers/1234567890/demandes
GET /api/usagers/1234567890/demandes?statut=en_cours
GET /api/usagers/1111111111/demandes?page=2
GET /api/usagers/1111111111/demandes?par_page=5&page=3
```

Paramètres facultatifs : `statut`, `page` (1 par défaut) et `par_page` (20 par défaut, 20 au maximum).

Réponse **200** : `data` contient les demandes, de la plus récente à la plus ancienne (à date égale, la dernière créée d'abord) ; `meta` contient `current_page`, `last_page`, `per_page`, `from`, `to` et `total` ; `links` contient les URL des pages. Une page au-delà de la dernière renvoie une liste `data` vide.

### 2.3 Faire avancer le traitement

`PATCH /api/demandes/{id}/statut`, avec l'un de ces corps :

```json
{ "statut": "en_cours" }
{ "statut": "validee" }
{ "statut": "rejetee", "motif": "Pièce justificative illisible." }
```

Réponse **200** : la demande mise à jour. `transitions_possibles` indique les statuts encore atteignables, et `traitee_le` la date de la décision finale.

### 2.4 Statistiques (bonus)

`GET /api/statistiques` ou `GET /api/statistiques?npi=1234567890`

```json
{ "data": { "total": 7, "par_statut": { "deposee": 3, "en_cours": 2, "validee": 1, "rejetee": 1 } } }
```

Tous les statuts sont présents, même à 0.

### 2.5 Codes d'erreur

Chaque erreur renvoie un champ `message` clair, en français. Le JSON est renvoyé avec les accents en clair (`"Déposée"` et non `"Déposée"`).

| Code | Cas | Exemple de réponse |
|---|---|---|
| `400` | Corps JSON mal formé | `{"message": "Le corps de la requête n'est pas un JSON valide : vérifiez les guillemets, les virgules et les accolades."}` |
| `422` | Saisie invalide : NPI, type d'acte, nombre de copies, statut, rejet sans motif, pagination | `{"message": "Les données envoyées sont invalides.", "errors": {"npi": ["Le NPI doit comporter exactement 10 chiffres."]}}` |
| `409` | Action interdite par le cycle de vie | `{"message": "Action interdite : une demande « Déposée » ne peut pas passer à « Validée ». Statut(s) possible(s) : « En cours de traitement ».", "statut_actuel": "deposee", "transitions_possibles": ["en_cours"]}` |
| `404` | Demande ou route inexistante | `{"message": "Demande introuvable."}` |
| `405` | Méthode HTTP non prévue sur la route | `{"message": "Méthode HTTP non autorisée pour cette route."}` |

Une action interdite est toujours signalée par un 409, même si la requête est par ailleurs incomplète : rejeter sans motif une demande déjà validée renvoie « La demande est déjà « Validée » : elle ne peut plus changer de statut. », et non une demande de motif.

---

## 3. Guide de recette

Ce guide vérifie chaque exigence du sujet, une commande à la fois, avec le résultat attendu. Tous les résultats indiqués ont été obtenus en déroulant le guide dans l'ordre, sur les données de démonstration.

### 3.1 Préparation

```bash
php artisan migrate:fresh --seed    # données de démonstration, numéros connus (voir 1. Données de démonstration)
php artisan serve
```

Les commandes sont à lancer **depuis la racine du projet**, dans un second terminal. Les corps JSON sont dans le dossier [`recette/`](recette), ce qui évite tout problème de guillemets : la même commande fonctionne sous Git Bash, Linux, macOS, l'invite de commandes Windows et PowerShell.

- **Windows PowerShell :** tapez `curl.exe` au lieu de `curl` (dans PowerShell 5, `curl` désigne une autre commande). Si les accents s'affichent mal, passez la console en UTF-8 avec `chcp 65001`.
- **Postman ou Insomnia :** même méthode, même URL, et le contenu du fichier `recette/….json` comme corps JSON.

Les étapes 3.2 à 3.5 ne font que lire les données. Les étapes 3.6 et 3.7 les modifient : pour rejouer le guide depuis le début, relancez `php artisan migrate:fresh --seed`.

### Correspondance entre le sujet et la recette

| Exigence du sujet | Étapes |
|---|---|
| Socle 1 — Déposer une demande (NPI, type, copies), identifiant et statut « déposée » | 3.6 a, b, c |
| Socle 2 — Consulter les demandes d'un usager, de la plus récente à la plus ancienne, filtre par statut | 3.2 a, b, c, d |
| Socle 3 — Faire avancer le traitement selon le cycle de vie | 3.7 c, d |
| Le NPI comporte exactement 10 chiffres | 3.2 e, 3.6 d, e, f |
| Le type d'acte est l'un des trois types cités | 3.6 g |
| Le nombre de copies est compris entre 1 et 5 | 3.6 c, h, i |
| Cycle de vie : déposée → en cours → validée ou rejetée ; une demande validée ou rejetée ne change plus | 3.7 a, b, c, d, e, f, j, k |
| Un rejet doit toujours être motivé | 3.7 g, h, i |
| Une saisie invalide ou une action interdite est refusée avec un message clair | toutes les étapes en 400, 409, 422, et 3.8 |
| Bonus — Pagination, au plus 20 demandes par page | 3.3 |
| Bonus — Nombre de demandes par statut | 3.4 |
| Bonus — Tests automatisés des règles de gestion | 3.9 |
| Bonus — Écran simple qui affiche la liste des demandes d'un usager | 3.5 |

### 3.2 Consulter les demandes d'un usager (socle 2)

```bash
# a. Demandes de l'usager 1234567890, de la plus récente à la plus ancienne
#    Attendu : 200, 7 demandes dans l'ordre n° 1, 2, 3, 5, 4, 6, 7
#    (4 et 5 ont la même date de dépôt : la dernière créée, n° 5, passe d'abord)
curl "http://127.0.0.1:8000/api/usagers/1234567890/demandes"

# b. Filtre facultatif par statut
#    Attendu : 200, 2 demandes « en_cours » : n° 5 et 4
curl "http://127.0.0.1:8000/api/usagers/1234567890/demandes?statut=en_cours"

# c. Statut de filtre inconnu
#    Attendu : 422, « Le statut doit être l'un des suivants : deposee, en_cours, validee, rejetee. »
curl "http://127.0.0.1:8000/api/usagers/1234567890/demandes?statut=archivee"

# d. Un autre usager ne voit que ses demandes
#    Attendu : 200, 2 demandes : n° 9 et 8
curl "http://127.0.0.1:8000/api/usagers/0987654321/demandes"

# e. NPI invalide dans l'URL
#    Attendu : 422, « Le NPI doit comporter exactement 10 chiffres. »
curl "http://127.0.0.1:8000/api/usagers/12345/demandes"
```

### 3.3 Pagination, au plus 20 demandes par page (bonus)

```bash
# a. Usager 1111111111 (25 demandes), page 1
#    Attendu : 200, 20 demandes (n° 10 à 29), "meta": { "current_page": 1, "last_page": 2, "per_page": 20, "total": 25 }
curl "http://127.0.0.1:8000/api/usagers/1111111111/demandes"

# b. Page 2
#    Attendu : 200, 5 demandes (n° 30 à 34), "current_page": 2
curl "http://127.0.0.1:8000/api/usagers/1111111111/demandes?page=2"

# c. Taille de page choisie : 5 par page, page 3
#    Attendu : 200, 5 demandes (n° 20 à 24), "current_page": 3, "last_page": 5
curl "http://127.0.0.1:8000/api/usagers/1111111111/demandes?par_page=5&page=3"

# d. Plus de 20 par page
#    Attendu : 422, « Le paramètre par_page doit être compris entre 1 et 20. »
curl "http://127.0.0.1:8000/api/usagers/1111111111/demandes?par_page=21"

# e. Page 0
#    Attendu : 422, « Le numéro de page doit être supérieur ou égal à 1. »
curl "http://127.0.0.1:8000/api/usagers/1111111111/demandes?page=0"
```

### 3.4 Nombre de demandes par statut (bonus)

```bash
# a. Pour l'usager 1234567890
#    Attendu : 200, "total": 7, "par_statut": { "deposee": 3, "en_cours": 2, "validee": 1, "rejetee": 1 }
curl "http://127.0.0.1:8000/api/statistiques?npi=1234567890"

# b. Pour l'usager 1111111111
#    Attendu : 200, "total": 25, "par_statut": { "deposee": 25, "en_cours": 0, "validee": 0, "rejetee": 0 }
curl "http://127.0.0.1:8000/api/statistiques?npi=1111111111"

# c. Toutes les demandes
#    Attendu : 200, "total": 34, "par_statut": { "deposee": 30, "en_cours": 2, "validee": 1, "rejetee": 1 }
curl "http://127.0.0.1:8000/api/statistiques"

# d. NPI invalide
#    Attendu : 422, « Le NPI doit comporter exactement 10 chiffres. »
curl "http://127.0.0.1:8000/api/statistiques?npi=abc"
```

### 3.5 Écran simple (bonus)

Ouvrir `http://127.0.0.1:8000` dans un navigateur.

| Action | Résultat attendu |
|---|---|
| a. Ouvrir la page | Les demandes de l'usager `1234567890` s'affichent directement, 5 par page : « Page 1 sur 2 · demandes 1 à 5 », boutons de page `1` `2`. Un skeleton loader (blocs gris animés) s'affiche pendant le chargement. |
| b. Lire les cartes de statistiques | Total **7**, Déposée **3** (43 %), En cours de traitement **2** (29 %), Validée **1** (14 %), Rejetée **1** (14 %) |
| c. Cliquer sur la carte « En cours de traitement » | La liste ne montre plus que les n° 5 et 4, et la carte est encadrée. Un clic sur « Total » retire le filtre. |
| d. Cliquer sur `2` ou « Suivant → » | « Page 2 sur 2 · demandes 6 à 7 » (n° 6 et 7) |
| e. Cliquer sur la puce « 1111111111 · 25 demandes » | « Page 1 sur 5 », boutons `1` `2` … `5` |
| f. Choisir « Par page : 20 (max.) » | « Page 1 sur 2 · demandes 1 à 20 », puis 5 demandes en page 2 |
| g. Saisir le NPI `12345`, puis « Afficher » | Message : « Le NPI doit comporter exactement 10 chiffres. » |
| h. Sur une demande en cours : « Rejeter », puis « Confirmer le rejet » sans motif | La boîte de dialogue affiche « Un rejet doit toujours être motivé : le champ motif est obligatoire. » |
| i. Saisir un motif, puis confirmer | Notification « Demande n° … : statut « Rejetée » », et la demande n'a plus d'action possible (—) |

L'écran n'utilise que l'API : chaque action passe par les routes décrites en partie 2. Le dépôt d'une demande se fait par l'API (`POST /api/demandes`), l'écran demandé par le sujet servant à afficher la liste des demandes d'un usager.

### 3.6 Déposer une demande et refuser les saisies invalides (socle 1, règles)

```bash
# a. Dépôt valide : NPI, type d'acte et nombre de copies
#    Attendu : 201, "id": 35, "statut": "deposee", "statut_libelle": "Déposée", "transitions_possibles": ["en_cours"]
curl -X POST "http://127.0.0.1:8000/api/demandes" -H "Content-Type: application/json" -d "@recette/depot-valide.json"

# b. Le statut envoyé par le client est ignoré (le fichier contient "statut": "validee")
#    Attendu : 201, "id": 36, "statut": "deposee"
curl -X POST "http://127.0.0.1:8000/api/demandes" -H "Content-Type: application/json" -d "@recette/depot-statut-impose.json"

# c. Borne haute : 5 copies
#    Attendu : 201, "id": 37
curl -X POST "http://127.0.0.1:8000/api/demandes" -H "Content-Type: application/json" -d "@recette/depot-copies-5.json"

# d. NPI de 9 chiffres     -> 422, « Le NPI doit comporter exactement 10 chiffres. »
curl -X POST "http://127.0.0.1:8000/api/demandes" -H "Content-Type: application/json" -d "@recette/depot-npi-9-chiffres.json"

# e. NPI de 11 chiffres    -> 422, même message
curl -X POST "http://127.0.0.1:8000/api/demandes" -H "Content-Type: application/json" -d "@recette/depot-npi-11-chiffres.json"

# f. NPI avec des lettres  -> 422, même message
curl -X POST "http://127.0.0.1:8000/api/demandes" -H "Content-Type: application/json" -d "@recette/depot-npi-lettres.json"

# g. Type d'acte inconnu ("passeport")
#    -> 422, « Le type d'acte doit être l'un des suivants : acte_naissance, casier_judiciaire, certificat_residence. »
curl -X POST "http://127.0.0.1:8000/api/demandes" -H "Content-Type: application/json" -d "@recette/depot-type-inconnu.json"

# h. 0 copie               -> 422, « Le nombre de copies doit être compris entre 1 et 5. »
curl -X POST "http://127.0.0.1:8000/api/demandes" -H "Content-Type: application/json" -d "@recette/depot-copies-0.json"

# i. 6 copies              -> 422, même message
curl -X POST "http://127.0.0.1:8000/api/demandes" -H "Content-Type: application/json" -d "@recette/depot-copies-6.json"

# j. Corps vide {}
#    -> 422, « Le NPI est obligatoire. », « Le type d'acte est obligatoire. », « Le nombre de copies est obligatoire. »
curl -X POST "http://127.0.0.1:8000/api/demandes" -H "Content-Type: application/json" -d "@recette/depot-vide.json"

# k. JSON mal formé
#    -> 400, « Le corps de la requête n'est pas un JSON valide : vérifiez les guillemets, les virgules et les accolades. »
curl -X POST "http://127.0.0.1:8000/api/demandes" -H "Content-Type: application/json" -d "@recette/depot-json-invalide.json"

# l. Les demandes valides sont bien enregistrées
#    Attendu : 200, 3 demandes pour l'usager 1112223334 : n° 37, 36, 35 (la plus récente d'abord)
curl "http://127.0.0.1:8000/api/usagers/1112223334/demandes"
```

### 3.7 Faire avancer le traitement selon le cycle de vie (socle 3, règles)

```bash
# a. Une demande déposée ne peut pas être validée directement (n° 1)
#    -> 409, « Action interdite : une demande « Déposée » ne peut pas passer à « Validée ». Statut(s) possible(s) : « En cours de traitement ». »
curl -X PATCH "http://127.0.0.1:8000/api/demandes/1/statut" -H "Content-Type: application/json" -d "@recette/statut-validee.json"

# b. ... ni rejetée directement
#    -> 409, « ... ne peut pas passer à « Rejetée ». Statut(s) possible(s) : « En cours de traitement ». »
curl -X PATCH "http://127.0.0.1:8000/api/demandes/1/statut" -H "Content-Type: application/json" -d "@recette/statut-rejetee-sans-motif.json"

# c. Déposée -> en cours de traitement
#    -> 200, "statut": "en_cours", "transitions_possibles": ["validee", "rejetee"]
curl -X PATCH "http://127.0.0.1:8000/api/demandes/1/statut" -H "Content-Type: application/json" -d "@recette/statut-en-cours.json"

# d. En cours -> validée
#    -> 200, "statut": "validee", "transitions_possibles": [], "traitee_le" renseignée
curl -X PATCH "http://127.0.0.1:8000/api/demandes/1/statut" -H "Content-Type: application/json" -d "@recette/statut-validee.json"

# e. Une demande validée ne change plus
#    -> 409, « La demande est déjà « Validée » : elle ne peut plus changer de statut. »
curl -X PATCH "http://127.0.0.1:8000/api/demandes/1/statut" -H "Content-Type: application/json" -d "@recette/statut-en-cours.json"

# f. Pas de retour en arrière : en cours -> déposée (n° 4)
#    -> 409, « ... ne peut pas passer à « Déposée ». Statut(s) possible(s) : « Validée », « Rejetée ». »
curl -X PATCH "http://127.0.0.1:8000/api/demandes/4/statut" -H "Content-Type: application/json" -d "@recette/statut-deposee.json"

# g. Rejet sans motif (n° 4, en cours)
#    -> 422, « Un rejet doit toujours être motivé : le champ motif est obligatoire. »
curl -X PATCH "http://127.0.0.1:8000/api/demandes/4/statut" -H "Content-Type: application/json" -d "@recette/statut-rejetee-sans-motif.json"

# h. Rejet avec un motif fait d'espaces -> 422, même message
curl -X PATCH "http://127.0.0.1:8000/api/demandes/4/statut" -H "Content-Type: application/json" -d "@recette/statut-rejetee-motif-vide.json"

# i. Rejet motivé
#    -> 200, "statut": "rejetee", "motif_rejet": "Pièce justificative illisible", "traitee_le" renseignée
curl -X PATCH "http://127.0.0.1:8000/api/demandes/4/statut" -H "Content-Type: application/json" -d "@recette/statut-rejetee-avec-motif.json"

# j. Une demande rejetée ne change plus (n° 7)
#    -> 409, « La demande est déjà « Rejetée » : elle ne peut plus changer de statut. »
curl -X PATCH "http://127.0.0.1:8000/api/demandes/7/statut" -H "Content-Type: application/json" -d "@recette/statut-en-cours.json"

# k. Rejeter une demande validée, même sans motif, est une action interdite (n° 6)
#    -> 409, « La demande est déjà « Validée » : elle ne peut plus changer de statut. »
curl -X PATCH "http://127.0.0.1:8000/api/demandes/6/statut" -H "Content-Type: application/json" -d "@recette/statut-rejetee-sans-motif.json"

# l. Statut inconnu ("archivee")
#    -> 422, « Le statut doit être l'un des suivants : deposee, en_cours, validee, rejetee. »
curl -X PATCH "http://127.0.0.1:8000/api/demandes/5/statut" -H "Content-Type: application/json" -d "@recette/statut-inconnu.json"

# m. Détail d'une demande traitée
#    -> 200, n° 4 : "statut": "rejetee", "motif_rejet" et "traitee_le" renseignés
curl "http://127.0.0.1:8000/api/demandes/4"
```

### 3.8 Autres erreurs

```bash
# a. Demande inexistante      -> 404, « Demande introuvable. »
curl "http://127.0.0.1:8000/api/demandes/999"

# b. Méthode non prévue       -> 405, « Méthode HTTP non autorisée pour cette route. »
curl -X DELETE "http://127.0.0.1:8000/api/demandes/1"

# c. Point d'entrée de l'API  -> 200, liste des 6 routes et liens d'exemple
curl "http://127.0.0.1:8000/api"
```

### 3.9 Tests automatisés (bonus)

```bash
php artisan test
```

Attendu : **59 tests réussis**, sans toucher aux données de démonstration (base en mémoire).

| Fichier | Ce qui est testé |
|---|---|
| `tests/Feature/DemandeApiTest.php` | Toutes les règles via HTTP : dépôt valide, statut imposé, 11 saisies invalides (NPI, type, copies à 0, 6, décimale, texte, booléen), JSON mal formé, tri, filtre, cloisonnement, NPI invalide (y compris avec un saut de ligne), pagination à 20, cycle de vie complet, transitions interdites, rejet sans motif et motivé, statuts finaux figés, 409 prioritaire sur le motif, 404, statistiques, accents, `GET /api`, données de démonstration, écran |
| `tests/Unit/StatutDemandeTest.php` | Les 16 combinaisons (statut actuel, statut cible) du cycle de vie, et les états finaux |
| `tests/Unit/NpiTest.php` | La règle « exactement 10 chiffres » : zéros en tête, 9 et 11 chiffres, lettres, espace, saut de ligne final, chiffres non latins, vide, nombre, null |

---

## 4. Modèle de données et règles de gestion

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
| Le NPI comporte exactement 10 chiffres | Règle `App\Rules\Npi`, définie une seule fois et utilisée par le dépôt, la liste et les statistiques. Elle refuse aussi un saut de ligne final et les chiffres non latins. |
| Le type d'acte fait partie des 3 types cités | Enum `TypeActe` et règle `in` |
| Le nombre de copies est compris entre 1 et 5 | `numeric`, `integer` et `between:1,5` (`numeric` refuse `true`, que `integer` seul accepterait comme 1) |
| Statut initial « déposée » | Imposé par `DemandeService::deposer()` |
| Cycle de vie | Défini à un seul endroit, `StatutDemande::transitionsPossibles()`, et appliqué par `DemandeService::changerStatut()` |
| Un rejet doit toujours être motivé | `DemandeService::changerStatut()`, après le contrôle de la transition (un motif fait d'espaces est refusé) |
| Message clair pour une saisie invalide ou une action interdite | Réponses JSON 400, 422, 409, 404 et 405 : `bootstrap/app.php`, `TransitionInterditeException`, middleware `RefuserJsonInvalide` |

---

## 5. Organisation du code

```
app/
├── Enums/
│   ├── TypeActe.php                    # types d'actes et libellés
│   └── StatutDemande.php               # statuts et cycle de vie (transitions autorisées)
├── Exceptions/
│   └── TransitionInterditeException.php  # action interdite -> HTTP 409
├── Http/
│   ├── Controllers/
│   │   ├── AccueilApiController.php    # GET /api : liste des routes
│   │   ├── DemandeController.php       # endpoints des demandes (contrôleur fin)
│   │   └── StatistiqueController.php   # GET /api/statistiques
│   ├── Middleware/
│   │   ├── JsonLisible.php             # JSON avec accents en clair
│   │   └── RefuserJsonInvalide.php     # JSON mal formé -> HTTP 400
│   ├── Requests/                       # validation des entrées et messages d'erreur
│   │   ├── StoreDemandeRequest.php
│   │   ├── ListeDemandesRequest.php
│   │   └── ChangerStatutRequest.php
│   └── Resources/
│       └── DemandeResource.php         # format JSON de sortie
├── Models/Demande.php
├── Rules/Npi.php                       # règle « exactement 10 chiffres »
└── Services/DemandeService.php         # règles métier (dépôt, changement de statut)
bootstrap/app.php                       # routes, middlewares et format JSON des erreurs
database/
├── migrations/..._create_demandes_table.php
├── factories/DemandeFactory.php
└── seeders/DatabaseSeeder.php          # données de démonstration
recette/                                # corps JSON du guide de recette
resources/views/demandes.blade.php      # écran de consultation et de traitement (bonus)
routes/api.php                          # routes de l'API
routes/web.php                          # route de l'écran
tests/
├── Feature/DemandeApiTest.php          # règles de gestion testées via l'API HTTP
└── Unit/
    ├── StatutDemandeTest.php           # cycle de vie : toutes les transitions
    └── NpiTest.php                     # règle du NPI
```

### Choix de conception

- **Contrôleur fin, service métier** : le format des entrées est validé dans les FormRequest, et les règles métier (transition, rejet motivé) sont dans `DemandeService`. Le contrôleur ne fait qu'orchestrer.
- **Enums PHP** pour les types et les statuts : les valeurs possibles et les transitions sont définies à un seul endroit. L'écran génère ses listes et ses libellés à partir de ces mêmes enums.
- **Une seule route de changement de statut** (`PATCH /demandes/{id}/statut`) : le serveur décide si la transition est autorisée. Pour ajouter un statut, il suffit de modifier l'enum.
- **400, 422 et 409 bien distingués** : 400 signale un corps illisible, 422 une donnée invalide, 409 une action incompatible avec l'état actuel de la demande. La transition est vérifiée avant le motif, pour que le message donne la vraie raison du refus.
- **Transaction et verrou de ligne** (`lockForUpdate`) lors du changement de statut : sur MySQL ou PostgreSQL, deux agents ne peuvent pas traiter la même demande en même temps. SQLite, lui, sérialise déjà les écritures sur toute la base.
- **SQLite** pour que le jury puisse démarrer l'application sans installer de serveur de base de données. Le passage à MySQL ou PostgreSQL se fait uniquement par le fichier `.env`.

---

## 6. État d'avancement

### Ce qui fonctionne

Tout le socle et les quatre bonus sont réalisés, et vérifiés par le guide de recette (partie 3) et par les tests automatisés.

**Socle obligatoire**

- Dépôt d'une demande avec NPI, type d'acte et nombre de copies, qui reçoit un identifiant et le statut « déposée »
- Consultation des demandes d'un usager, de la plus récente à la plus ancienne, avec filtre facultatif par statut
- Avancement du traitement selon le cycle de vie, avec motif obligatoire en cas de rejet et statuts finaux figés
- Refus des saisies invalides et des actions interdites, avec un message clair en français (400, 404, 405, 409, 422)

**Bonus**

- Pagination de la liste, 20 demandes par page au maximum (taille réglable avec `par_page`)
- Nombre de demandes par statut (`/api/statistiques`, pour un usager ou pour toutes les demandes)
- Tests automatisés des règles de gestion : 59 tests (`php artisan test`)
- Écran simple (`http://127.0.0.1:8000`) qui affiche la liste des demandes d'un usager, avec les statistiques par statut mises en avant (cartes cliquables qui filtrent la liste), la pagination, un skeleton loader et des boutons de traitement

### Ce qui manque, et pourquoi

Ces points sortent du périmètre demandé ou n'ont pas pu être traités dans le temps imparti de 1 h 30 :

- **Authentification et rôles (usager / agent)** : le sujet ne la demande pas. En production, il faudrait protéger les routes, par exemple avec Laravel Sanctum, pour qu'un usager ne voie que ses propres demandes et que seul un agent puisse changer un statut.
- **Historique des changements de statut** (qui a fait quoi et quand) : seule la date de décision finale (`traitee_le`) est conservée. Une table `historique_statuts` serait l'étape suivante pour la traçabilité.
- **Dépôt depuis l'écran** : le sujet demande un écran qui affiche la liste des demandes d'un usager ; le dépôt reste donc réservé à l'API (`POST /api/demandes`).
- **Documentation OpenAPI / Swagger** : l'API est documentée dans ce README, avec un guide de recette exécutable.
- **Traduction des libellés de pagination** : les liens `meta.links` gardent les libellés par défaut de Laravel (« Previous », « Next »). Les fichiers de langue français n'ont pas été ajoutés, car tous les messages d'erreur métier sont déjà rédigés en français.
