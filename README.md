# PI-Planning

Mini-application PHP (micro-MVC, **sans dépendance Composer**) pour piloter les Program Increments SAFe d'une équipe, en s'appuyant sur Jira, Confluence et OpenRouter.

- Liste des PI et statistiques du sprint actif
- Création / modification de PI, associés à un sprint *Feature* et un sprint *Enabler*
- Génération des User Stories Jira depuis les features `[B4ALL]` et les enablers `[DLK]` — **idempotente** (aucun doublon si on relance)
- Enrichissement automatique des US depuis Confluence (topic, format)
- Page « Mon sprint » : tickets assignés, dernier commentaire, synthèse + action conseillée générées par IA (mise en cache)
- Préparation de démo : sélection par statut, pré-cochage des US/Features terminées ou à tester

## Prérequis

| Élément | Détail |
|---|---|
| PHP | ≥ 8.1, avec `curl`, `pdo_mysql`, `mbstring` |
| MySQL / MariaDB | base dédiée (voir `database/schema.sql`) |
| Accès réseau | Jira, Confluence et OpenRouter joignables (VPN si télétravail) |
| Jira | un **Bearer token** + clés des boards ART et équipe |
| Confluence | un Bearer token (optionnel) |
| OpenRouter | une clé API (optionnel, pour les synthèses IA) |

Aucune dépendance Composer : l'autoloader natif (`public/index.php`) charge `app/` en PSR-4-like.

## Installation

```bash
git clone <repo> PI-Planning
cd PI-Planning
```

1. **Base de données** — appliquer le schéma :

   ```bash
   mysql -u <user> -p <base> < database/schema.sql
   ```

2. **Configuration** — copier puis renseigner :

   ```bash
   cp config/config.example.php config/config.php
   ```

   Clés à remplir : `db.*`, `jira.bearer`, `jira.username` (pour « Mes tickets »), `confluence.bearer` et `openrouter.api_key` (optionnels). Les clés `art_board_id` et `team_board_id` doivent correspondre à tes boards Jira.

   > Tout secret peut aussi venir d'une variable d'environnement : `PIPLANNING_JIRA_BEARER`, `PIPLANNING_DB_PASS`, `PIPLANNING_OPENROUTER_API_KEY`…

3. **Servir l'application** — pointer un vhost vers `public/` (le `.htaccess` racine réécrit tout vers `public/` si le vhost vise la racine du projet). Avec XAMPP, par exemple `C:\www\PI-Planning`.

## Tests (sans Composer)

```bash
php tests/run.php
```

Suite autonome (micro-runner intégré) : parseur de descriptions, classification des statuts, CSRF, URL, cibles HDFS/Hive, CRUD modèle et caches. Les tests BDD s'ignorent proprement si `config/config.php` ou la base ne sont pas disponibles. Code de sortie : `0` si tout passe, `1` sinon.

## Tâche planifiée : enrichissement IA en arrière-plan

L'analyse IA des tickets est faite par `bin/enrich-tickets.php` (CLI) et **non** dans les pages web : la page « Mon sprint » ne fait que lire le cache `ticket_ai_cache`.

Le script vérifie lui-même ses prérequis à chaque exécution (config présente, Jira joignable = VPN actif, BDD joignable, clé API et `jira.username` renseignés). S'il manque quelque chose, il **sort immédiatement** (exit 0) : on peut donc le lancer toutes les 15 minutes sans risque — le travail se fera dès que le VPN sera monté.

### Mise en place (option A — Planificateur de tâches Windows, sans droits admin)

La tâche est créée pour ton utilisateur courant : aucune élévation de privilèges n'est nécessaire, et elle ne s'exécute que pendant ta session — exactement ce qu'il faut, puisque le VPN dépend de ta session.

Ouvrir `cmd` et adapter le chemin vers `php.exe` :

```bat
schtasks /Create /TN "PI-Planning enrich IA" /SC MINUTE /MO 15 /TR "\"C:\xampp\php\php.exe\" -f \"C:\www\PI-Planning\bin\enrich-tickets.php\" --quiet" /F
```

> Si la commande est refusée ou mal enregistrée (guillemets), passe par le GUI : `taskschd.msc` → « Créer une tâche de base » → déclencheur « Une fois par jour » puis onglet *Déclencheurs* → modifier → « Répéter toutes les 15 minutes » ; action = démarrer `php.exe` avec les arguments `-f "C:\www\PI-Planning\bin\enrich-tickets.php" --quiet` (démarrer dans `C:\www\PI-Planning`).

Vérifier / supprimer :

```bat
schtasks /Query /TN "PI-Planning enrich IA"
schtasks /Delete /TN "PI-Planning enrich IA" /F
```

Le budget d'appels IA par passage est réglé par `openrouter.max_calls_per_request` (15 par défaut) : au-delà, le script s'arrête proprement et termine au passage suivant.

## Structure

```text
app/
  Controllers/    PiController, SprintController
  Core/           Config, Database, HttpClient, JiraClient, ConfluenceClient,
                  OpenRouter, Model (ORM léger), View, Factory
  Models/         Pi, CreatedUs (idempotence US), TicketCache (cache IA)
  Services/       DescriptionParser, UserStoryService
  Views/          vues + layouts + erreurs
  helpers.php     fonctions globales (url, e, flash, csrf, remember…)
bin/              enrich-tickets.php (CLI planifié)
config/           config.php (non versionné) + config.example.php
database/         schema.sql
public/           front controller, assets
tests/            run.php + bootstrap.php (sans Composer)
```

## Sécurité

- CSRF sur toutes les requêtes POST, session durcie (httponly, SameSite=Lax)
- Requêtes SQL 100 % préparées, identifiants validés avant interpolation
- Échappement HTML systématique dans les vues (`e()`)
- Tokens uniquement dans `config/config.php` (**non versionné** — vérifie `.gitignore`) ou variables d'environnement
- ⚠ Si un mot de passe a déjà été committé (historique git), **le changer côté serveur** — un `git rm` ne nettoie pas l'historique

## Dépannage rapide

| Symptôme | Cause probable |
|---|---|
| « Configuration base de données incomplète » | clé `db.*` manquante dans `config/config.php` |
| US dupliquées à la relance | table `created_us` absente → appliquer `database/schema.sql` |
| Page « Mon sprint » vide | `jira.username` vide dans la config |
| 404 sur toutes les pages | vhost / `.htaccess` : le `RewriteBase` doit pointer vers le bon dossier |
| « Jeton CSRF invalide » | cookies bloqués ou session expirée → recharger la page |