# Mini GLPI — Trame de projet

## Vision
Développer une application web inspirée de GLPI pour la gestion de tickets IT, avec une architecture propre, testée et maintenable.

## Objectifs
- Gestion des tickets IT
- Assignation des tickets à des techniciens
- Gestion des statuts et priorités
- Séparation claire entre logique métier et contrôleurs
- Couverture de tests minimale

## Stack technique
Backend:
- PHP 8.4
- Symfony
- Doctrine ORM
- MySQL
- PHPUnit

Frontend:
- Twig
- Vue.js
- SCSS

DevOps / Qualité:
- Git
- GitHub
- Migrations Doctrine
- Tests unitaires
- Tests fonctionnels
- Cypress (si temps)

## Architecture cible
- `src/Controller/`
- `src/Entity/`
- `src/Repository/`
- `src/Service/`
- `src/DTO/`
- `src/Enum/`
- `src/Security/`
- `templates/`
- `assets/`
- `tests/`
- `migrations/`

## MVP (Minimum Viable Product)
1. Authentification
2. Gestion des tickets
3. Statuts possibles via Enums

### Authentification
- Login
- Rôles: `USER`, `TECH`

### Gestion des tickets
- Création
- Liste
- Détail
- Modification
- Assignation
- Changement de statut

### Statuts possibles
- `OPEN`
- `IN_PROGRESS`
- `CLOSED`

## Architecture métier (règle d’or)
Ne pas mettre la logique métier dans les contrôleurs.

Exemple:
```php
// ❌ Mauvais
$ticket->setStatus('CLOSED');

// ✅ Correct
$ticketService->close($ticket);
```

## Tests minimums
Unit tests:
- Création d’un ticket
- Changement de statut valide
- Refus changement de statut invalide

Functional test:
- Création d’un ticket via HTTP

## Frontend
Twig:
- Layout principal
- Liste des tickets
- Page détail

Vue:
- Bouton changement de statut dynamique
- Mise à jour instantanée sans refresh

## Plan sur 2 jours
Jour 1 – Backend fondations:
1. Setup MySQL
2. Installation ORM
3. Création Entité User
4. Création Entité Ticket
5. Mise en place Enum Status
6. Migration
7. CRUD Ticket basique
8. Service Layer

Jour 2 – Propreté & Front:
9. Validation Symfony
10. Refactor propre
11. PHPUnit (3 tests minimum)
12. Vue.js pour changement statut
13. SCSS structuré
14. README propre

## Compétences travaillées
- PSR-4
- Injection de dépendances
- Services Symfony
- Enums PHP
- Doctrine relations
- Validation
- Tests unitaires
- Architecture propre
- Intégration JS/Vue dans Symfony

## Critères de fin
- Projet montrable sur GitHub
- Code structuré
- Logique métier encapsulée
- Tests présents
- Architecture propre
- Approche professionnelle

## Suivi (checklist)
- [ ] Setup MySQL
- [ ] Installation ORM
- [ ] Entité `User`
- [ ] Entité `Ticket`
- [ ] Enum `Status`
- [ ] Migration
- [ ] CRUD Ticket
- [ ] Service Layer
- [ ] Validation Symfony
- [ ] Refactor propre
- [ ] PHPUnit (3 tests minimum)
- [ ] Vue.js changement statut
- [ ] SCSS structuré
- [ ] README propre
