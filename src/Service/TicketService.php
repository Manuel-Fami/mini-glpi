<?php

namespace App\Service;

use App\Entity\Ticket;
use App\Entity\User;
use App\Enum\TicketStatus;
use Doctrine\ORM\EntityManagerInterface;

class TicketService
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    public function create(
        string $title,
        string $description,
        string $priority,
        User $creator
    ): Ticket {
        $ticket = new Ticket();
        $ticket->setTitle($title);
        $ticket->setDescription($description);
        $ticket->setPriority($priority);
        $ticket->setCreatedBy($creator);

        $this->em->persist($ticket);
        $this->em->flush();

        return $ticket;
    }

    public function update(Ticket $ticket): void
    {
        $this->em->flush();
    }

    public function assign(Ticket $ticket, User $user): void
    {
        if ($ticket->getAssignedTo() !== null) {
            throw new \LogicException('Ticket already assigned.');
        }

        $ticket->setAssignedTo($user);
        $this->em->flush();
    }

    public function startProgress(Ticket $ticket): void
    {
        if ($ticket->getStatus() !== TicketStatus::OPEN) {
            throw new \LogicException('Only open tickets can start progress.');
        }

        $ticket->setStatus(TicketStatus::IN_PROGRESS);
        $this->em->flush();
    }

    public function close(Ticket $ticket): void
    {
        if ($ticket->getStatus() === TicketStatus::CLOSED) {
            throw new \LogicException('Ticket already closed.');
        }

        $ticket->setStatus(TicketStatus::CLOSED);
        $this->em->flush();
    }
}