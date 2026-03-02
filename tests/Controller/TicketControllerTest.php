<?php

namespace App\Tests\Controller;

use App\Entity\Ticket;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\TicketService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class TicketControllerTest extends WebTestCase
{
    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function loginAs(KernelBrowser $client, string $email): User
    {
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['email' => $email]);
        $this->assertNotNull($user, "L'utilisateur $email doit exister (relancer les fixtures).");
        $client->loginUser($user);

        return $user;
    }

    private function createOpenTicket(User $creator): Ticket
    {
        $service = static::getContainer()->get(TicketService::class);

        return $service->create('Ticket de test', 'Description du ticket de test.', 'MEDIUM', $creator);
    }

    private function createClosedTicket(User $creator): Ticket
    {
        $service = static::getContainer()->get(TicketService::class);
        $ticket  = $service->create('Ticket fermé de test', 'Description du ticket fermé.', 'MEDIUM', $creator);
        $service->close($ticket);

        return $ticket;
    }

    private function createAssignedTicket(User $creator, User $assignee): Ticket
    {
        $service = static::getContainer()->get(TicketService::class);
        $ticket  = $service->create('Ticket assigné de test', 'Description du ticket assigné.', 'MEDIUM', $creator);
        $service->assign($ticket, $assignee);

        return $ticket;
    }

    // ----------------------------------------------------------------
    // Accès non authentifié → redirection vers /login
    // ----------------------------------------------------------------

    public function testUnauthenticatedAccessRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/tickets');

        $this->assertResponseRedirects('/login');
    }

    // ----------------------------------------------------------------
    // Création d'un ticket via HTTP (utilisateur connecté)
    // ----------------------------------------------------------------

    public function testCreateTicketViaHttp(): void
    {
        $client = static::createClient();
        $this->loginAs($client, 'user@mini-glpi.fr');

        // Accès au formulaire de création
        $crawler = $client->request('GET', '/tickets/new');
        $this->assertResponseIsSuccessful();

        // Soumission du formulaire
        $form = $crawler->selectButton('Créer le ticket')->form([
            'ticket[title]'       => 'Ticket de test fonctionnel',
            'ticket[description]' => 'Description suffisamment longue pour la validation.',
            'ticket[priority]'    => 'HIGH',
        ]);

        $client->submit($form);

        // Doit rediriger vers la liste
        $this->assertResponseRedirects('/tickets');

        // Vérification que le ticket apparaît dans la liste
        $client->followRedirect();
        $this->assertSelectorTextContains('td', 'Ticket de test fonctionnel');
    }

    // ----------------------------------------------------------------
    // Consultation du détail d'un ticket
    // ----------------------------------------------------------------

    public function testAuthenticatedUserCanViewTicketDetail(): void
    {
        $client = static::createClient();
        $user   = $this->loginAs($client, 'user@mini-glpi.fr');
        $ticket = $this->createOpenTicket($user);

        $client->request('GET', '/tickets/' . $ticket->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Ticket #' . $ticket->getId());
    }

    // ----------------------------------------------------------------
    // Modification d'un ticket fermé → redirection avec erreur
    // ----------------------------------------------------------------

    public function testEditClosedTicketRedirectsWithError(): void
    {
        $client = static::createClient();
        $user   = $this->loginAs($client, 'user@mini-glpi.fr');
        $ticket = $this->createClosedTicket($user);

        $client->request('GET', '/tickets/' . $ticket->getId() . '/edit');

        $this->assertResponseRedirects('/tickets/' . $ticket->getId());
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Impossible de modifier un ticket fermé.');
    }

    // ----------------------------------------------------------------
    // Commentaires — ROLE_USER non assigné ne peut pas commenter
    // ----------------------------------------------------------------

    public function testUserCannotCommentOnTicketNotAssignedToThem(): void
    {
        // Créer un ticket avec le tech (non assigné au user simple)
        $client   = static::createClient();
        $tech     = $this->loginAs($client, 'tech@mini-glpi.fr');
        $ticket   = $this->createOpenTicket($tech);
        $ticketId = $ticket->getId();

        // Même client, on change simplement l'utilisateur connecté
        $this->loginAs($client, 'user@mini-glpi.fr');

        $client->request('POST', '/tickets/' . $ticketId . '/comment', [
            'content' => 'Commentaire non autorisé.',
        ]);

        $this->assertResponseRedirects('/tickets/' . $ticketId);
        $client->followRedirect();
        $this->assertSelectorTextContains('body', "Vous n'êtes pas autorisé à commenter ce ticket.");
    }

    // ----------------------------------------------------------------
    // Réouverture — ROLE_USER ne peut pas réouvrir (JSON 403)
    // ----------------------------------------------------------------

    public function testUserCannotReopenTicket(): void
    {
        $client = static::createClient();
        $user   = $this->loginAs($client, 'user@mini-glpi.fr');
        $ticket = $this->createClosedTicket($user);

        // Récupérer le token CSRF depuis la page de détail
        $crawler = $client->request('GET', '/tickets/' . $ticket->getId());
        $token   = $crawler->filter('[data-status-token-value]')->attr('data-status-token-value');

        $client->request('POST', '/tickets/' . $ticket->getId() . '/status', [
            'status' => 'open',
            '_token' => $token,
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('Action non autorisée.', $data['error']);
    }

    // ----------------------------------------------------------------
    // Commentaires — ROLE_TECH peut commenter n'importe quel ticket
    // ----------------------------------------------------------------

    public function testTechCanCommentOnAnyOpenTicket(): void
    {
        $client = static::createClient();
        $tech   = $this->loginAs($client, 'tech@mini-glpi.fr');
        $ticket = $this->createOpenTicket($tech);

        $client->request('POST', '/tickets/' . $ticket->getId() . '/comment', [
            'content' => 'Commentaire du tech.',
        ]);

        $this->assertResponseRedirects('/tickets/' . $ticket->getId());
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Commentaire ajouté.');
    }

    // ----------------------------------------------------------------
    // Commentaires — ROLE_USER assigné peut commenter
    // ----------------------------------------------------------------

    public function testAssignedUserCanComment(): void
    {
        $client = static::createClient();
        $tech   = $this->loginAs($client, 'tech@mini-glpi.fr');
        $user   = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user@mini-glpi.fr']);
        $ticket = $this->createAssignedTicket($tech, $user);

        $this->loginAs($client, 'user@mini-glpi.fr');

        $client->request('POST', '/tickets/' . $ticket->getId() . '/comment', [
            'content' => 'Commentaire de l\'utilisateur assigné.',
        ]);

        $this->assertResponseRedirects('/tickets/' . $ticket->getId());
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Commentaire ajouté.');
    }

    // ----------------------------------------------------------------
    // Commentaires — impossible de commenter un ticket fermé
    // ----------------------------------------------------------------

    public function testCannotCommentOnClosedTicket(): void
    {
        $client = static::createClient();
        $tech   = $this->loginAs($client, 'tech@mini-glpi.fr');
        $ticket = $this->createClosedTicket($tech);

        $client->request('POST', '/tickets/' . $ticket->getId() . '/comment', [
            'content' => 'Commentaire sur ticket fermé.',
        ]);

        $this->assertResponseRedirects('/tickets/' . $ticket->getId());
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Impossible de commenter un ticket fermé.');
    }

    // ----------------------------------------------------------------
    // Assignation — ROLE_USER peut s'auto-assigner un ticket libre
    // ----------------------------------------------------------------

    public function testUserCanSelfAssignUnassignedTicket(): void
    {
        $client = static::createClient();
        $user   = $this->loginAs($client, 'user@mini-glpi.fr');
        $ticket = $this->createOpenTicket($user);

        $client->request('POST', '/tickets/' . $ticket->getId() . '/assign');

        $this->assertResponseRedirects('/tickets/' . $ticket->getId());
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Ticket assigné à vous.');
    }

    // ----------------------------------------------------------------
    // Assignation — ROLE_USER ne peut pas s'assigner un ticket déjà assigné
    // ----------------------------------------------------------------

    public function testUserCannotAssignAlreadyAssignedTicket(): void
    {
        $client = static::createClient();
        $tech   = $this->loginAs($client, 'tech@mini-glpi.fr');
        $user   = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user@mini-glpi.fr']);
        $ticket = $this->createAssignedTicket($tech, $tech);

        $this->loginAs($client, 'user@mini-glpi.fr');

        $client->request('POST', '/tickets/' . $ticket->getId() . '/assign');

        $this->assertResponseRedirects('/tickets/' . $ticket->getId());
        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Ce ticket est déjà assigné.');
    }

    // ----------------------------------------------------------------
    // Assignation — ROLE_TECH peut assigner un ticket à n'importe quel utilisateur
    // ----------------------------------------------------------------

    public function testTechCanAssignTicketToAnyUser(): void
    {
        $client = static::createClient();
        $tech   = $this->loginAs($client, 'tech@mini-glpi.fr');
        $user   = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'user@mini-glpi.fr']);
        $ticket = $this->createOpenTicket($tech);

        $client->request('POST', '/tickets/' . $ticket->getId() . '/assign', [
            'user_id' => $user->getId(),
        ]);

        $this->assertResponseRedirects('/tickets/' . $ticket->getId());
        $client->followRedirect();
        $this->assertSelectorTextContains('body', $user->getUserIdentifier());
    }

    // ----------------------------------------------------------------
    // Réouverture — ROLE_TECH peut réouvrir un ticket fermé
    // ----------------------------------------------------------------

    public function testTechCanReopenClosedTicket(): void
    {
        $client = static::createClient();
        $tech   = $this->loginAs($client, 'tech@mini-glpi.fr');
        $ticket = $this->createClosedTicket($tech);

        // Récupérer le token CSRF depuis la page de détail
        $crawler = $client->request('GET', '/tickets/' . $ticket->getId());
        $token   = $crawler->filter('[data-status-token-value]')->attr('data-status-token-value');

        $client->request('POST', '/tickets/' . $ticket->getId() . '/status', [
            'status' => 'open',
            '_token' => $token,
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('open', $data['status']);
        $this->assertSame('Ouvert', $data['label']);
    }
}
