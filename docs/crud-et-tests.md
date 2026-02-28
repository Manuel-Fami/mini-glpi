# CRUD Tickets & Tests — Mini GLPI

## Vue d'ensemble

Cette section couvre deux parties complémentaires :

1. Le **CRUD des tickets** — formulaire Symfony, validation, couche Service, routes
2. Les **tests PHPUnit** — 3 tests unitaires + 1 test fonctionnel

```
src/
├── Entity/Ticket.php          ← contraintes de validation
├── Form/TicketType.php        ← formulaire Symfony
├── Service/TicketService.php  ← logique métier (create, update, close, assign)
├── Controller/TicketController.php  ← 6 routes HTTP
└── Repository/UserRepository.php   ← findByRole()

tests/
├── Service/TicketServiceTest.php    ← 3 tests unitaires
└── Controller/TicketControllerTest.php ← 2 tests fonctionnels
```

---

## 1. Validation — Entité Ticket

**Fichier :** `src/Entity/Ticket.php`

Les contraintes Symfony Validator sont déclarées directement sur les propriétés
via des attributs PHP. Elles s'appliquent automatiquement lors de la soumission
d'un formulaire.

```php
#[ORM\Column(length: 255)]
#[Assert\NotBlank(message: 'Le titre est obligatoire.')]
#[Assert\Length(min: 3, max: 255, minMessage: 'Le titre doit faire au moins 3 caractères.')]
private string $title;

#[ORM\Column(type: Types::TEXT)]
#[Assert\NotBlank(message: 'La description est obligatoire.')]
#[Assert\Length(min: 10, minMessage: 'La description doit faire au moins 10 caractères.')]
private string $description;

#[ORM\Column(length: 50)]
#[Assert\NotBlank(message: 'La priorité est obligatoire.')]
#[Assert\Choice(choices: ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'], message: 'Priorité invalide.')]
private string $priority;
```

### Contraintes utilisées

| Contrainte | Rôle |
|------------|------|
| `NotBlank` | Refuse les valeurs vides ou null |
| `Length` | Contrôle la longueur minimale et/ou maximale |
| `Choice` | Limite les valeurs acceptées à une liste fermée |

### Pourquoi valider sur l'entité et non dans le controller ?

La validation sur l'entité est **indépendante du transport** : elle s'applique
quelle que soit la source de la donnée (formulaire HTTP, API, console…).
Le controller ne valide rien — il délègue au composant Form qui appelle le Validator.

---

## 2. Formulaire — TicketType

**Fichier :** `src/Form/TicketType.php`

```php
class TicketType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [...])
            ->add('description', TextareaType::class, [...])
            ->add('priority', ChoiceType::class, [
                'choices' => [
                    'Basse'    => 'LOW',
                    'Moyenne'  => 'MEDIUM',
                    'Haute'    => 'HIGH',
                    'Critique' => 'CRITICAL',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Ticket::class]);
    }
}
```

### Points clés

**`data_class`**

Lie le formulaire à l'entité `Ticket`. Lorsque le formulaire est soumis,
Symfony hydrate directement les propriétés de l'entité avec les valeurs saisies.

**Nommage des champs dans les templates**

Le nom du formulaire est dérivé du nom de la classe (`TicketType` → `ticket`).
Les champs sont donc nommés `ticket[title]`, `ticket[description]`, `ticket[priority]`
dans le HTML généré. C'est ce nom qui est utilisé dans les tests fonctionnels.

**CSRF**

Symfony ajoute automatiquement un token CSRF caché dans chaque formulaire.
Il est validé à la soumission — inutile de le gérer manuellement.

---

## 3. Couche Service — TicketService

**Fichier :** `src/Service/TicketService.php`

La logique métier reste dans le Service, jamais dans le Controller.

```
Controller  →  Service  →  EntityManager  →  Base de données
```

### Méthodes disponibles

| Méthode | Règle métier |
|---------|-------------|
| `create(title, description, priority, creator)` | Crée et persiste un nouveau ticket au statut OPEN |
| `update(ticket)` | Flush les modifications (titre, description, priorité) |
| `assign(ticket, user)` | Assigne si non déjà assigné — sinon `LogicException` |
| `startProgress(ticket)` | OPEN → IN_PROGRESS — sinon `LogicException` |
| `close(ticket)` | → CLOSED — refuse si déjà fermé (`LogicException`) |

### Pourquoi des LogicException ?

Une `LogicException` signale une **violation des règles du domaine métier** :
elle ne doit jamais se produire si le code appelant est correct.
Elle protège l'intégrité du modèle indépendamment de la couche HTTP.

```php
// ❌ Interdit par le domaine
$service->close($ticketDéjàFermé); // → LogicException

// ✅ Le controller attrape et affiche un message flash
try {
    $service->close($ticket);
} catch (\LogicException $e) {
    $this->addFlash('error', $e->getMessage());
}
```

---

## 4. Routes — TicketController

**Fichier :** `src/Controller/TicketController.php`

Toutes les routes sont préfixées `/tickets` via l'attribut de classe `#[Route('/tickets')]`.

| Nom | Méthode | URL | Action |
|-----|---------|-----|--------|
| `ticket_list` | GET | `/tickets` | Liste tous les tickets |
| `ticket_new` | GET \| POST | `/tickets/new` | Affiche et traite le formulaire de création |
| `ticket_show` | GET | `/tickets/{id}` | Détail d'un ticket |
| `ticket_edit` | GET \| POST | `/tickets/{id}/edit` | Affiche et traite le formulaire d'édition |
| `ticket_close` | POST | `/tickets/{id}/close` | Ferme un ticket |
| `ticket_assign` | POST | `/tickets/{id}/assign` | Assigne un ticket (ROLE_TECH uniquement) |

### Contrainte `requirements: ['id' => '\d+']`

Le paramètre `{id}` est contraint à n'accepter que des chiffres.
Cela évite que `/tickets/new` soit capturé par la route `ticket_show`
qui attend un entier.

### Pattern GET + POST sur une même route

```php
#[Route('/new', name: 'ticket_new', methods: ['GET', 'POST'])]
public function new(Request $request, TicketService $service): Response
{
    $ticket = new Ticket();
    $form   = $this->createForm(TicketType::class, $ticket);
    $form->handleRequest($request);  // ← ne fait rien si GET

    if ($form->isSubmitted() && $form->isValid()) {
        // traitement du POST
        return $this->redirectToRoute('ticket_list');
    }

    return $this->render('ticket/new.html.twig', ['form' => $form]);
}
```

`handleRequest()` lit les données de la requête et hydrate le formulaire.
Sur un GET, il ne fait rien. Sur un POST valide, le bloc `if` s'exécute.
C'est le pattern **PRG** (Post/Redirect/Get) : après un POST réussi,
on redirige pour éviter la re-soumission du formulaire au rechargement.

### Flash messages

```php
$this->addFlash('success', 'Ticket créé avec succès.');
```

Les messages flash sont stockés en session et affichés une seule fois
dans le template via `app.flashes('success')`. Ils disparaissent après affichage.

### Injection de dépendances dans les actions

Symfony injecte automatiquement les services déclarés en paramètre :

```php
public function show(Ticket $ticket, UserRepository $userRepository): Response
```

- `Ticket $ticket` — résolution automatique via `{id}` (ParamConverter)
- `UserRepository $userRepository` — injection depuis le conteneur de services

---

## 5. Repository — findByRole()

**Fichier :** `src/Repository/UserRepository.php`

```php
public function findByRole(string $role): array
{
    return $this->createQueryBuilder('u')
        ->andWhere('u.roles LIKE :role')
        ->setParameter('role', '%"' . $role . '"%')
        ->orderBy('u.email', 'ASC')
        ->getQuery()
        ->getResult();
}
```

Les rôles sont stockés en JSON dans la colonne `roles` (ex: `["ROLE_TECH"]`).
La recherche `LIKE '%" . $role . "%'` filtre sur la présence du rôle dans
la chaîne JSON. Cette approche simple convient pour un petit nombre d'utilisateurs.

---

## 6. Tests unitaires — TicketServiceTest

**Fichier :** `tests/Service/TicketServiceTest.php`

Les tests unitaires testent **le Service isolément**, sans base de données.
L'`EntityManager` est remplacé par un **mock** (faux objet contrôlé).

```php
protected function setUp(): void
{
    $this->em      = $this->createMock(EntityManagerInterface::class);
    $this->service = new TicketService($this->em);
}
```

### Qu'est-ce qu'un mock ?

Un mock est un objet qui imite une dépendance réelle.
On peut lui dicter ce qu'il doit retourner et vérifier qu'il est
appelé exactement comme prévu.

```php
$this->em->expects($this->once())->method('persist');
// ↑ persist() doit être appelé exactement 1 fois — sinon le test échoue
```

```php
$this->em->expects($this->never())->method('flush');
// ↑ flush() ne doit JAMAIS être appelé dans ce cas
```

### Test 1 — Création d'un ticket

```php
public function testCreateTicketSetsCorrectProperties(): void
{
    $user = new User();

    $this->em->expects($this->once())->method('persist');
    $this->em->expects($this->once())->method('flush');

    $ticket = $this->service->create(
        title:       'Écran noir au démarrage',
        description: 'Mon écran reste noir après allumage.',
        priority:    'HIGH',
        creator:     $user,
    );

    $this->assertSame('Écran noir au démarrage', $ticket->getTitle());
    $this->assertSame(TicketStatus::OPEN, $ticket->getStatus());
    $this->assertSame($user, $ticket->getCreatedBy());
}
```

**Ce qui est vérifié :**
- Les propriétés sont correctement assignées
- Le statut par défaut est `OPEN`
- `persist()` et `flush()` sont appelés exactement une fois

### Test 2 — Changement de statut valide

```php
public function testStartProgressChangesStatusToInProgress(): void
{
    $ticket = new Ticket(); // statut OPEN par défaut

    $this->em->expects($this->once())->method('flush');

    $this->service->startProgress($ticket);

    $this->assertSame(TicketStatus::IN_PROGRESS, $ticket->getStatus());
}
```

**Ce qui est vérifié :**
- Un ticket OPEN passe bien en IN_PROGRESS
- `flush()` est appelé pour persister le changement

### Test 3 — Refus d'une règle métier

```php
public function testCloseAlreadyClosedTicketThrowsLogicException(): void
{
    $ticket = new Ticket();
    $ticket->setStatus(TicketStatus::CLOSED);

    $this->em->expects($this->never())->method('flush');

    $this->expectException(\LogicException::class);
    $this->expectExceptionMessage('Ticket already closed.');

    $this->service->close($ticket);
}
```

**Ce qui est vérifié :**
- Une `LogicException` est levée (type exact)
- Le message correspond exactement à ce qui est attendu
- `flush()` n'est jamais appelé — la base n'est pas touchée

---

## 7. Test fonctionnel — TicketControllerTest

**Fichier :** `tests/Controller/TicketControllerTest.php`

Les tests fonctionnels simulent un **vrai navigateur HTTP** via `WebTestCase`.
Ils testent le système de bout en bout : routing → controller → service → DB → template.

### Infrastructure nécessaire

Le test fonctionnel nécessite une base de données de test peuplée :

```bash
php bin/console doctrine:database:create --env=test
php bin/console doctrine:migrations:migrate --env=test --no-interaction
php bin/console doctrine:fixtures:load --env=test --no-interaction
```

Symfony utilise automatiquement la base `mini_glpi_test` en environnement `test`.

### Test 1 — Accès non authentifié

```php
public function testUnauthenticatedAccessRedirectsToLogin(): void
{
    $client = static::createClient();
    $client->request('GET', '/tickets');

    $this->assertResponseRedirects('/login');
}
```

**Ce qui est vérifié :** un visiteur non connecté est bien redirigé vers `/login`
(confirme que l'`access_control` de `security.yaml` fonctionne).

### Test 2 — Création d'un ticket via HTTP

```php
public function testCreateTicketViaHttp(): void
{
    $client = static::createClient();

    $user = static::getContainer()
        ->get(UserRepository::class)
        ->findOneBy(['email' => 'user@mini-glpi.fr']);

    $client->loginUser($user);  // ← authentification programmatique

    $crawler = $client->request('GET', '/tickets/new');
    $this->assertResponseIsSuccessful();

    $form = $crawler->selectButton('Créer le ticket')->form([
        'ticket[title]'       => 'Ticket de test fonctionnel',
        'ticket[description]' => 'Description suffisamment longue pour la validation.',
        'ticket[priority]'    => 'HIGH',
    ]);

    $client->submit($form);

    $this->assertResponseRedirects('/tickets');  // ← pattern PRG

    $client->followRedirect();
    $this->assertSelectorTextContains('td', 'Ticket de test fonctionnel');
}
```

**Ce qui est vérifié étape par étape :**

| Étape | Assertion |
|-------|-----------|
| GET `/tickets/new` accessible | `assertResponseIsSuccessful()` |
| POST du formulaire → redirect | `assertResponseRedirects('/tickets')` |
| Le ticket est visible dans la liste | `assertSelectorTextContains('td', '...')` |

**`loginUser()`** — Authentification programmatique

Évite de simuler la saisie du formulaire de login.
Crée directement une session authentifiée pour l'utilisateur passé en paramètre.
Le token CSRF du formulaire de création est géré automatiquement par le client.

---

## 8. Lancer les tests

```bash
# Tous les tests
php bin/phpunit

# Tests unitaires uniquement
php bin/phpunit tests/Service/

# Test fonctionnel uniquement
php bin/phpunit tests/Controller/

# Avec détail des noms de tests
php bin/phpunit --testdox
```

### Résultat attendu

```
PHPUnit 13.0.5

.....                                    5 / 5 (100%)

OK (5 tests, 20 assertions)
```

---

## Concepts clés à retenir

| Concept | Explication |
|---------|-------------|
| **Formulaire Symfony** | Lié à une entité via `data_class`, hydrate automatiquement les propriétés |
| **Validation** | Contraintes sur l'entité, déclenchées par `$form->isValid()` |
| **Pattern PRG** | POST → traitement → Redirect → GET — évite la re-soumission |
| **Flash messages** | Messages one-shot stockés en session, affichés dans le template |
| **Mock PHPUnit** | Faux objet qui remplace une dépendance réelle pour isoler le test |
| **`expects($this->once())`** | Vérifie qu'une méthode est appelée exactement 1 fois |
| **`expects($this->never())`** | Vérifie qu'une méthode n'est jamais appelée |
| **`loginUser()`** | Authentification programmatique dans les tests fonctionnels |
| **`handleRequest()`** | Lit la requête HTTP et hydrate le formulaire — ne fait rien sur GET |
| **ParamConverter** | Résout `Ticket $ticket` depuis `{id}` automatiquement |
