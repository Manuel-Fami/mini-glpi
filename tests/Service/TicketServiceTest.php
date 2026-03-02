<?php

namespace App\Tests\Service;

use App\Entity\Comment;
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
    // create()
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
    // startProgress()
    // ----------------------------------------------------------------

    public function testStartProgressChangesStatusToInProgress(): void
    {
        $ticket = new Ticket(); // statut OPEN par défaut

        $this->em->expects($this->once())->method('flush');

        $this->service->startProgress($ticket);

        $this->assertSame(TicketStatus::IN_PROGRESS, $ticket->getStatus());
    }

    public function testStartProgressOnNonOpenTicketThrowsLogicException(): void
    {
        $ticket = new Ticket();
        $ticket->setStatus(TicketStatus::IN_PROGRESS);

        $this->em->expects($this->never())->method('flush');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Only open tickets can start progress.');

        $this->service->startProgress($ticket);
    }

    // ----------------------------------------------------------------
    // close()
    // ----------------------------------------------------------------

    public function testCloseOpenTicketChangesStatusToClosed(): void
    {
        $ticket = new Ticket(); // statut OPEN par défaut

        $this->em->expects($this->once())->method('flush');

        $this->service->close($ticket);

        $this->assertSame(TicketStatus::CLOSED, $ticket->getStatus());
    }

    public function testCloseAlreadyClosedTicketThrowsLogicException(): void
    {
        $ticket = new Ticket();
        $ticket->setStatus(TicketStatus::CLOSED);

        $this->em->expects($this->never())->method('flush');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Ticket already closed.');

        $this->service->close($ticket);
    }

    // ----------------------------------------------------------------
    // reopen()
    // ----------------------------------------------------------------

    public function testReopenClosedTicketChangesStatusToOpen(): void
    {
        $ticket = new Ticket();
        $ticket->setStatus(TicketStatus::CLOSED);

        $this->em->expects($this->once())->method('flush');

        $this->service->reopen($ticket);

        $this->assertSame(TicketStatus::OPEN, $ticket->getStatus());
    }

    public function testReopenNonClosedTicketThrowsLogicException(): void
    {
        $ticket = new Ticket(); // OPEN par défaut

        $this->em->expects($this->never())->method('flush');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Only closed tickets can be reopened.');

        $this->service->reopen($ticket);
    }

    // ----------------------------------------------------------------
    // assign()
    // ----------------------------------------------------------------

    public function testAssignSetsAssignedToUser(): void
    {
        $ticket = new Ticket();
        $user   = new User();

        $this->em->expects($this->once())->method('flush');

        $this->service->assign($ticket, $user);

        $this->assertSame($user, $ticket->getAssignedTo());
    }

    // ----------------------------------------------------------------
    // addComment()
    // ----------------------------------------------------------------

    public function testAddCommentCreatesCommentWithCorrectProperties(): void
    {
        $ticket  = new Ticket();
        $author  = new User();
        $content = 'Ceci est un commentaire de test.';

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $comment = $this->service->addComment($ticket, $author, $content);

        $this->assertInstanceOf(Comment::class, $comment);
        $this->assertSame($content, $comment->getContent());
        $this->assertSame($author, $comment->getAuthor());
        $this->assertSame($ticket, $comment->getTicket());
    }
}
