<?php

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class TicketControllerTest extends WebTestCase
{
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

        // Chargement de l'utilisateur depuis la base
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['email' => 'user@mini-glpi.fr']);

        $this->assertNotNull($user, 'L\'utilisateur de test doit exister (relancer les fixtures).');

        $client->loginUser($user);

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
}
