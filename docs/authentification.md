# Mise en place de l'authentification — Mini GLPI

## Vue d'ensemble

L'authentification repose sur le composant **Security** de Symfony.
Le flux est le suivant :

```
Utilisateur non connecté
        │
        ▼
   GET /login  ──►  Formulaire email + mot de passe
        │
        ▼ (POST /login)
   Symfony vérifie les credentials via le firewall
        │
   ┌────┴────┐
   │ Échec   │  →  Retour au formulaire avec message d'erreur
   └─────────┘
   │ Succès  │  →  Redirection vers /tickets
   └─────────┘
        │
   GET /logout  →  Destruction de la session  →  Retour au login
```

---

## 1. L'entité User

**Fichier :** `src/Entity/User.php`

L'entité `User` implémente deux interfaces Symfony :

```php
class User implements UserInterface, PasswordAuthenticatedUserInterface
```

### UserInterface

Oblige à implémenter :

- `getUserIdentifier()` — identifiant unique de l'utilisateur (ici : l'email)
- `getRoles()` — tableau des rôles (`ROLE_USER` garanti pour tous)
- `eraseCredentials()` — nettoyage des données sensibles temporaires

### PasswordAuthenticatedUserInterface

Oblige à implémenter :

- `getPassword()` — retourne le mot de passe **hashé** stocké en base

Le mot de passe n'est jamais stocké en clair. Symfony le hashe automatiquement
via le `password_hasher` configuré dans `security.yaml`.

---

## 2. Le fichier security.yaml

**Fichier :** `config/packages/security.yaml`

C'est le fichier central de la configuration de sécurité.

```yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'

    providers:
        app_user_provider:
            entity:
                class: App\Entity\User
                property: email

    firewalls:
        dev:
            pattern: ^/(_profiler|_wdt|assets|build)/
            security: false
        main:
            lazy: true
            provider: app_user_provider
            form_login:
                login_path: app_login
                check_path: app_login
                default_target_path: ticket_list
                enable_csrf: true
            logout:
                path: app_logout
                target: app_login

    access_control:
        - { path: ^/login, roles: PUBLIC_ACCESS }
        - { path: ^/, roles: ROLE_USER }
```

### password_hashers

Définit l'algorithme de hashage des mots de passe.
`auto` laisse Symfony choisir le meilleur algorithme disponible (bcrypt ou argon2).

### providers

Le **User Provider** explique à Symfony comment charger un utilisateur depuis
la base de données. Ici : chercher un `User` dont le champ `email` correspond
à l'identifiant saisi.

### firewalls

Un **firewall** définit comment Symfony gère la sécurité pour un ensemble de routes.

| Firewall | Rôle |
|----------|------|
| `dev` | Désactive la sécurité pour les outils de développement (profiler, assets) |
| `main` | Firewall principal — s'applique à toutes les autres routes |

#### form_login

Active l'authentification par formulaire.

| Clé | Valeur | Explication |
|-----|--------|-------------|
| `login_path` | `app_login` | Route qui affiche le formulaire |
| `check_path` | `app_login` | Route qui reçoit le POST et vérifie les credentials |
| `default_target_path` | `ticket_list` | Redirection après connexion réussie |
| `enable_csrf` | `true` | Protection contre les attaques CSRF |

#### logout

| Clé | Valeur | Explication |
|-----|--------|-------------|
| `path` | `app_logout` | Route qui déclenche la déconnexion |
| `target` | `app_login` | Redirection après déconnexion |

### access_control

Définit les règles d'accès par route, dans l'ordre (la première règle qui correspond s'applique).

| Règle | Rôle requis | Explication |
|-------|-------------|-------------|
| `^/login` | `PUBLIC_ACCESS` | Le login est accessible sans être connecté |
| `^/` | `ROLE_USER` | Tout le reste nécessite d'être connecté |

---

## 3. Le SecurityController

**Fichier :** `src/Controller/SecurityController.php`

```php
#[Route('/login', name: 'app_login')]
public function login(AuthenticationUtils $authenticationUtils): Response
{
    if ($this->getUser()) {
        return $this->redirectToRoute('ticket_list');
    }

    $error = $authenticationUtils->getLastAuthenticationError();
    $lastUsername = $authenticationUtils->getLastUsername();

    return $this->render('security/login.html.twig', [
        'last_username' => $lastUsername,
        'error' => $error,
    ]);
}
```

### Pourquoi AuthenticationUtils ?

`AuthenticationUtils` est un service Symfony qui expose :

- `getLastAuthenticationError()` — l'erreur de connexion si le dernier essai a échoué
- `getLastUsername()` — le dernier email saisi (pour pré-remplir le champ)

### La méthode logout

```php
#[Route('/logout', name: 'app_logout')]
public function logout(): void
{
    throw new \LogicException('...');
}
```

Cette méthode ne s'exécute **jamais**. Symfony intercepte la requête vers
`/logout` au niveau du firewall et détruit la session avant que le controller
ne soit atteint. Le `LogicException` est là pour signifier que si ce code
s'exécute, quelque chose est mal configuré.

---

## 4. Le formulaire de login

**Fichier :** `templates/security/login.html.twig`

```html
<form method="post">
    <input type="email" name="_username" ...>
    <input type="password" name="_password" ...>
    <input type="hidden" name="_csrf_token" value="{{ csrf_token('authenticate') }}">
    <button type="submit">Se connecter</button>
</form>
```

### Noms des champs

Les noms `_username` et `_password` sont les noms par défaut attendus par
le composant `form_login` de Symfony. Ils peuvent être personnalisés dans
`security.yaml` via `username_parameter` et `password_parameter`.

### Protection CSRF

Le champ caché `_csrf_token` avec l'intention `authenticate` protège contre
les attaques **Cross-Site Request Forgery** : une soumission de formulaire
depuis un site tiers sera rejetée car le token sera invalide.

---

## 5. Le flux complet expliqué

```
1. Utilisateur visite /tickets (non connecté)
        │
        ▼
2. access_control détecte ROLE_USER requis → redirige vers /login

3. Utilisateur soumet le formulaire (POST /login)
        │
        ▼
4. Symfony valide le token CSRF
        │
        ▼
5. Le UserProvider charge l'utilisateur via l'email
        │
        ▼
6. Symfony compare le mot de passe soumis au hash en base
        │
   ┌────┴────────────────┐
   │ Mauvais mot de passe │ → Redirige vers /login avec erreur
   └──────────────────────┘
   │ Correct              │ → Crée une session, redirige vers /tickets
   └──────────────────────┘

7. GET /logout → Symfony détruit la session → redirige vers /login
```

---

## 6. Insertion des utilisateurs de test — DataFixtures

**Fichier :** `src/DataFixtures/AppFixtures.php`

En développement, on ne crée pas les utilisateurs à la main. On utilise les
**DataFixtures** : des classes PHP qui peuplent la base de données avec des
données contrôlées et reproductibles.

### Installation du bundle

```bash
composer require --dev doctrine/doctrine-fixtures-bundle
```

L'option `--dev` est importante : les fixtures ne doivent jamais être
disponibles en production.

### La classe AppFixtures

```php
class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $hasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        $users = [
            ['email' => 'user@mini-glpi.fr', 'password' => 'password', 'roles' => ['ROLE_USER']],
            ['email' => 'tech@mini-glpi.fr', 'password' => 'password', 'roles' => ['ROLE_TECH']],
        ];

        foreach ($users as $data) {
            $user = new User();
            $user->setEmail($data['email']);
            $user->setRoles($data['roles']);
            $user->setPassword(
                $this->hasher->hashPassword($user, $data['password'])
            );
            $manager->persist($user);
        }

        $manager->flush();
    }
}
```

### Points clés

**`UserPasswordHasherInterface`**

On n'assigne jamais le mot de passe en clair. `hashPassword()` prend l'entité
User et le mot de passe en clair, et retourne le hash selon l'algorithme
configuré dans `security.yaml` (`auto` → bcrypt ou argon2).

```php
// ❌ Jamais
$user->setPassword('password');

// ✅ Toujours
$user->setPassword($this->hasher->hashPassword($user, 'password'));
```

**`persist()` + `flush()`**

- `persist($user)` — informe Doctrine de suivre l'entité (pas encore en base)
- `flush()` — exécute toutes les requêtes SQL en une seule transaction

On persiste tous les utilisateurs dans la boucle, puis on flush une seule fois
à la fin : c'est plus efficace qu'un flush à chaque itération.

### Commande d'exécution

```bash
php bin/console doctrine:fixtures:load
```

> ⚠️ Cette commande **purge toute la base avant de recharger**. Elle est
> réservée au développement. Ne jamais l'exécuter en production.

Pour ajouter sans purger (append) :

```bash
php bin/console doctrine:fixtures:load --append
```

### Utilisateurs créés

| Email | Mot de passe | Rôle |
|-------|-------------|------|
| `user@mini-glpi.fr` | `password` | `ROLE_USER` |
| `tech@mini-glpi.fr` | `password` | `ROLE_TECH` |

---

## 7. Rôles disponibles

| Rôle | Description |
|------|-------------|
| `ROLE_USER` | Rôle de base, attribué à tous les utilisateurs |
| `ROLE_TECH` | Technicien — pourra être utilisé pour restreindre l'assignation de tickets |

Les rôles sont stockés en JSON dans la colonne `roles` de la table `user`.

---

## Concepts clés à retenir (avec DataFixtures)

| Concept | Explication |
|---------|-------------|
| **Firewall** | Périmètre de sécurité qui intercepte les requêtes avant les controllers |
| **Provider** | Composant qui sait comment charger un User depuis une source de données |
| **Authenticator** | Composant qui vérifie les credentials (form_login en est un) |
| **access_control** | Liste de règles URL → rôle requis |
| **CSRF** | Attaque où un site tiers soumet un formulaire à la place de l'utilisateur |
| **Password hasher** | Jamais de mot de passe en clair — toujours hashé avant persistance |
| **DataFixtures** | Classes PHP qui peuplent la base avec des données de test reproductibles |
| **persist() / flush()** | persist() enregistre l'intention, flush() exécute les SQL en base |
| **--dev** | Les fixtures sont une dépendance de développement uniquement |
