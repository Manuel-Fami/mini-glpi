<?php

namespace App\Controller;

use App\Entity\Ticket;
use App\Repository\TicketRepository;
use App\Service\TicketService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/tickets')]
class TicketController extends AbstractController
{
    #[Route('', name: 'ticket_list', methods: ['GET'])]
    public function list(TicketRepository $repository): Response
    {
        $tickets = $repository->findAll();

        return $this->render('ticket/list.html.twig', [
            'tickets' => $tickets,
        ]);
    }

    #[Route('/create', name: 'ticket_create', methods: ['POST'])]
    public function create(TicketService $service): Response
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $service->create(
            title: 'Demo ticket',
            description: 'Created from controller',
            priority: 'HIGH',
            creator: $user
        );

        return $this->redirectToRoute('ticket_list');
    }

    #[Route('/{id}/close', name: 'ticket_close', methods: ['POST'])]
    public function close(Ticket $ticket, TicketService $service): Response
    {
        $service->close($ticket);

        return $this->redirectToRoute('ticket_list');
    }
}