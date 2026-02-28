<?php

namespace App\Tests\Service;

use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketStatus;
use App\Service\TicketService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class TicketServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private TicketService $service;

    protected function setUp(): void
    {
        $this->em      = $this->createMock(EntityManagerInterface::class);
        $this->service = new TicketService($this->em);
    }

    // ----------------------------------------------------------------
    // Test 1 — Création d'un ticket
    // ----------------------------------------------------------------

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

        $this->assertInstanceOf(Ticket::class, $ticket);
        $this->assertSame('Écran noir au démarrage', $ticket->getTitle());
        $this->assertSame('Mon écran reste noir après allumage.', $ticket->getDescription());
        $this->assertSame('HIGH', $ticket->getPriority());
        $this->assertSame($user, $ticket->getCreatedBy());
        $this->assertSame(TicketStatus::OPEN, $ticket->getStatus());
    }

    // ----------------------------------------------------------------
    // Test 2 — Changement de statut valide (OPEN → IN_PROGRESS)
    // ----------------------------------------------------------------

    public function testStartProgressChangesStatusToInProgress(): void
    {
        $ticket = new Ticket(); // statut OPEN par défaut

        $this->em->expects($this->once())->method('flush');

        $this->service->startProgress($ticket);

        $this->assertSame(TicketStatus::IN_PROGRESS, $ticket->getStatus());
    }

    // ----------------------------------------------------------------
    // Test 3 — Refus : fermer un ticket déjà fermé
    // ----------------------------------------------------------------

    public function testCloseAlreadyClosedTicketThrowsLogicException(): void
    {
        $ticket = new Ticket();
        $ticket->setStatus(TicketStatus::CLOSED);

        $this->em->expects($this->never())->method('flush');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Ticket already closed.');

        $this->service->close($ticket);
    }
}
