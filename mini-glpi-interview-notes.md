# Mini GLPI -- Simulation Questions d'Entretien & Approfondissement Technique

------------------------------------------------------------------------

## 🎯 Objectif du document

Ce document a pour but : - De consolider les concepts techniques
utilisés dans le projet - De préparer des réponses d'entretien solides -
D'approfondir les notions clés Symfony / Doctrine / Architecture

------------------------------------------------------------------------

# 1️⃣ Architecture Générale

## ❓ Question Entretien

**Pourquoi avoir utilisé une architecture avec une couche Service ?**

### ✅ Réponse attendue

J'ai isolé la logique métier dans une couche Service afin de : -
Respecter le principe de responsabilité unique (SRP) - Éviter d'avoir
des contrôleurs "gras" - Centraliser les règles métier - Faciliter les
tests unitaires

Le contrôleur gère uniquement : - L'HTTP - Les requêtes - Les réponses

La logique métier reste indépendante du transport.

------------------------------------------------------------------------

# 2️⃣ Doctrine & ORM

## ❓ Question Entretien

**Comment Doctrine fonctionne-t-il sous le capot ?**

### ✅ Réponse attendue

Doctrine utilise le pattern Unit of Work :

-   Les entités sont suivies en mémoire.
-   Lorsqu'on appelle `flush()`, Doctrine calcule les différences.
-   Il génère automatiquement les requêtes SQL nécessaires.

Doctrine ne fait PAS un `UPDATE` immédiatement lors d'un `set()`.

------------------------------------------------------------------------

## ❓ Quelle est la différence entre persist() et flush() ?

### persist()

Informe Doctrine qu'une entité doit être suivie et insérée en base.

### flush()

Exécute réellement les requêtes SQL.

------------------------------------------------------------------------

# 3️⃣ Relations Doctrine

## ❓ Explique la relation ManyToOne utilisée dans Ticket

Un Ticket appartient à un User.

Donc : Ticket → ManyToOne → User

Un User peut avoir plusieurs Tickets.

Le côté ManyToOne est le **owning side** : C'est lui qui contient la clé
étrangère.

------------------------------------------------------------------------

## ❓ Pourquoi createdBy est non nullable ?

Parce qu'un ticket doit obligatoirement avoir un créateur. Cela impose
une règle métier au niveau base de données.

------------------------------------------------------------------------

# 4️⃣ Enum PHP

## ❓ Pourquoi utiliser une Enum au lieu d'un string ?

-   Évite les magic strings
-   Permet un ensemble d'états fermés
-   Offre du type safety
-   Améliore la lisibilité

Exemple :

TicketStatus::OPEN

est plus sûr que :

"open"

------------------------------------------------------------------------

# 5️⃣ Migrations

## ❓ Comment gérez-vous les modifications de base de données ?

Via Doctrine Migrations :

1.  Modification des Entités
2.  `make:migration`
3.  `doctrine:migrations:migrate`

Cela permet : - Versioning du schéma - Traçabilité des évolutions -
Déploiements reproductibles

------------------------------------------------------------------------

# 6️⃣ Service Layer

## ❓ Pourquoi la logique métier ne doit-elle pas être dans le Controller ?

Parce que :

-   Le contrôleur dépend du framework HTTP
-   Le métier doit être indépendant du transport
-   Cela facilite les tests

Si demain l'application expose une API ou une CLI : La logique métier ne
change pas.

------------------------------------------------------------------------

# 7️⃣ Règles Métier

## ❓ Pourquoi lever des LogicException dans le Service ?

Pour protéger l'intégrité du domaine.

Exemples : - Impossible de fermer un ticket déjà fermé - Impossible
d'assigner deux fois

On protège le modèle métier.

------------------------------------------------------------------------

# 8️⃣ Questions Pièges Possibles

### ❓ Pourquoi ne pas mettre la logique directement dans l'Entity ?

On pourrait le faire (approche DDD).

Mais dans Symfony classique : On sépare souvent Entity (data) et Service
(logique).

------------------------------------------------------------------------

### ❓ Comment testeriez-vous TicketService ?

Avec des tests unitaires : - Mock de l'EntityManager - Vérification des
règles métier - Test des exceptions

------------------------------------------------------------------------

# 9️⃣ Concepts à Approfondir

-   Dependency Injection
-   Inversion of Control
-   Repository Pattern
-   Unit of Work
-   DDD Lite
-   Clean Architecture

------------------------------------------------------------------------

# 🔥 Exercice Personnel

Explique à voix haute :

-   Pourquoi cette architecture est propre
-   Quelle serait la prochaine amélioration
-   Comment scaler vers un projet plus complexe

------------------------------------------------------------------------

# 🎯 Conclusion

Ce projet montre : - Une modélisation métier claire - Une séparation des
responsabilités - Une utilisation moderne de PHP 8 - Une compréhension
de Doctrine

Objectif : Être capable d'expliquer chaque décision technique de manière
cohérente.
