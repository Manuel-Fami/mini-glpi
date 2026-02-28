# Mini GLPI

Application de gestion de tickets d'assistance construite avec **Symfony 8** / **PHP 8.4**.
Projet réalisé dans un contexte pédagogique pour illustrer les bonnes pratiques Symfony :
architecture en couches, tests unitaires et fonctionnels, interface moderne avec Tailwind CSS.

---

## Stack technique

| Composant | Technologie |
|-----------|------------|
| Framework | Symfony 8.0 |
| Langage | PHP 8.4 |
| Base de données | MySQL (Doctrine ORM) |
| Frontend | Tailwind CSS v4 (`symfonycasts/tailwind-bundle`) |
| Interactivité | Stimulus (`@hotwired/stimulus`) |
| Tests | PHPUnit 13 |

---

## Fonctionnalités

- **Authentification** — connexion / déconnexion via formulaire Symfony Security
- **Gestion des tickets** — création, consultation, modification, fermeture
- **Changement de statut dynamique** — boutons AJAX sans rechargement de page (Stimulus)
- **Assignation** — réservée aux utilisateurs `ROLE_TECH`
- **Validation** — contraintes déclarées sur l'entité (`NotBlank`, `Length`, `Choice`)
- **Rôles** — `ROLE_USER` (accès standard) et `ROLE_TECH` (assignation)

### Statuts d'un ticket

```
OPEN  →  IN_PROGRESS  →  CLOSED
```

---

## Prérequis

- PHP 8.4+
- Composer
- MySQL 8+
- Symfony CLI (optionnel, pour `symfony server:start`)

---

## Installation

```bash
# 1. Cloner le dépôt
git clone <url-du-repo> mini-glpi
cd mini-glpi

# 2. Installer les dépendances PHP
composer install

# 3. Configurer la base de données
# Copier .env et ajuster DATABASE_URL
cp .env .env.local
# Éditer DATABASE_URL dans .env.local

# 4. Créer la base et jouer les migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 5. Charger les utilisateurs de test
php bin/console doctrine:fixtures:load

# 6. Compiler les assets Tailwind
php bin/console tailwind:build

# 7. Démarrer le serveur
symfony server:start
# ou
php -S localhost:8000 -t public/
```

---

## Comptes de test

| Email | Mot de passe | Rôle |
|-------|-------------|------|
| `user@mini-glpi.fr` | `password` | Utilisateur standard |
| `tech@mini-glpi.fr` | `password` | Technicien (peut assigner) |

---

## Structure du projet

```
src/
├── Controller/
│   ├── SecurityController.php   ← login / logout
│   └── TicketController.php     ← 7 routes CRUD + API statut
├── Entity/
│   ├── Ticket.php               ← entité avec contraintes de validation
│   └── User.php                 ← entité utilisateur (Security)
├── Enum/
│   └── TicketStatus.php         ← OPEN | IN_PROGRESS | CLOSED
├── Form/
│   └── TicketType.php           ← formulaire lié à l'entité Ticket
├── Service/
│   └── TicketService.php        ← logique métier (create, update, assign, close…)
├── Repository/
│   ├── TicketRepository.php
│   └── UserRepository.php       ← findByRole()
└── DataFixtures/
    └── AppFixtures.php          ← utilisateurs de test

assets/
├── controllers/
│   └── status_controller.js     ← Stimulus : changement de statut AJAX
└── styles/
    └── app.css                  ← Tailwind CSS v4

templates/
├── base.html.twig               ← layout avec navbar
├── security/login.html.twig     ← formulaire de connexion
└── ticket/
    ├── list.html.twig
    ├── show.html.twig           ← boutons dynamiques (Stimulus)
    ├── new.html.twig
    └── edit.html.twig

tests/
├── Service/TicketServiceTest.php      ← 3 tests unitaires (mock EntityManager)
└── Controller/TicketControllerTest.php ← 2 tests fonctionnels (WebTestCase)

docs/
├── authentification.md          ← mise en place de la sécurité Symfony
└── crud-et-tests.md             ← formulaires, validation, routes, PHPUnit
```

---

## Routes

| Nom | Méthode | URL | Description |
|-----|---------|-----|-------------|
| `app_login` | GET/POST | `/login` | Formulaire de connexion |
| `app_logout` | GET | `/logout` | Déconnexion |
| `ticket_list` | GET | `/tickets` | Liste des tickets |
| `ticket_new` | GET/POST | `/tickets/new` | Créer un ticket |
| `ticket_show` | GET | `/tickets/{id}` | Détail d'un ticket |
| `ticket_edit` | GET/POST | `/tickets/{id}/edit` | Modifier un ticket |
| `ticket_close` | POST | `/tickets/{id}/close` | Fermer un ticket |
| `ticket_status` | POST | `/tickets/{id}/status` | Changer le statut (JSON API) |
| `ticket_assign` | POST | `/tickets/{id}/assign` | Assigner un ticket |

---

## Tests

```bash
# Préparer la base de test (une seule fois)
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test --no-interaction
php bin/console doctrine:fixtures:load --env=test --no-interaction

# Lancer tous les tests
php bin/phpunit

# Tests unitaires uniquement
php bin/phpunit tests/Service/

# Tests fonctionnels uniquement
php bin/phpunit tests/Controller/

# Avec le détail des noms
php bin/phpunit --testdox
```

Résultat attendu :

```
PHPUnit 13

.....                                    5 / 5 (100%)

OK (5 tests, 20 assertions)
```

---

## Documentation

- [Authentification](docs/authentification.md) — Security, formulaire de login, DataFixtures, rôles
- [CRUD & Tests](docs/crud-et-tests.md) — Formulaire Symfony, validation, Service, routes, PHPUnit
