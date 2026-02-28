<?php

namespace App\Controller;

use App\Entity\Ticket;
use App\Enum\TicketStatus;
use App\Form\TicketType;
use App\Repository\TicketRepository;
use App\Repository\UserRepository;
use App\Service\TicketService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tickets')]
class TicketController extends AbstractController
{
    #[Route('', name: 'ticket_list', methods: ['GET'])]
    public function list(TicketRepository $repository): Response
    {
        return $this->render('ticket/list.html.twig', [
            'tickets' => $repository->findAll(),
        ]);
    }

    #[Route('/new', name: 'ticket_new', methods: ['GET', 'POST'])]
    public function new(Request $request, TicketService $service): Response
    {
        $ticket = new Ticket();
        $form   = $this->createForm(TicketType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $service->create(
                title:       $ticket->getTitle(),
                description: $ticket->getDescription(),
                priority:    $ticket->getPriority(),
                creator:     $user,
            );

            $this->addFlash('success', 'Ticket créé avec succès.');
            return $this->redirectToRoute('ticket_list');
        }

        return $this->render('ticket/new.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'ticket_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Ticket $ticket, UserRepository $userRepository): Response
    {
        return $this->render('ticket/show.html.twig', [
            'ticket' => $ticket,
            'techs'  => $userRepository->findByRole('ROLE_TECH'),
        ]);
    }

    #[Route('/{id}/edit', name: 'ticket_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Ticket $ticket, Request $request, TicketService $service): Response
    {
        if ($ticket->getStatus()->value === 'closed') {
            $this->addFlash('error', 'Impossible de modifier un ticket fermé.');
            return $this->redirectToRoute('ticket_show', ['id' => $ticket->getId()]);
        }

        $form = $this->createForm(TicketType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $service->update($ticket);
            $this->addFlash('success', 'Ticket mis à jour.');
            return $this->redirectToRoute('ticket_show', ['id' => $ticket->getId()]);
        }

        return $this->render('ticket/edit.html.twig', [
            'ticket' => $ticket,
            'form'   => $form,
        ]);
    }

    #[Route('/{id}/close', name: 'ticket_close', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function close(Ticket $ticket, TicketService $service): Response
    {
        try {
            $service->close($ticket);
            $this->addFlash('success', 'Ticket fermé.');
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('ticket_show', ['id' => $ticket->getId()]);
    }

    #[Route('/{id}/status', name: 'ticket_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateStatus(Ticket $ticket, Request $request, TicketService $service): JsonResponse
    {
        if (!$this->isCsrfTokenValid('ticket_status_' . $ticket->getId(), $request->request->getString('_token'))) {
            return $this->json(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $newStatus = $request->request->getString('status');

        try {
            match ($newStatus) {
                'in_progress' => $service->startProgress($ticket),
                'closed'      => $service->close($ticket),
                default       => throw new \InvalidArgumentException('Statut inconnu.'),
            };
        } catch (\LogicException | \InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $status = $ticket->getStatus();

        return $this->json([
            'status' => $status->value,
            'label'  => match ($status) {
                TicketStatus::OPEN        => 'Ouvert',
                TicketStatus::IN_PROGRESS => 'En cours',
                TicketStatus::CLOSED      => 'Fermé',
            },
        ]);
    }

    #[Route('/{id}/assign', name: 'ticket_assign', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function assign(Ticket $ticket, Request $request, TicketService $service, UserRepository $userRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_TECH');

        $userId = $request->request->getInt('user_id');
        $user   = $userRepository->find($userId);

        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('ticket_show', ['id' => $ticket->getId()]);
        }

        try {
            $service->assign($ticket, $user);
            $this->addFlash('success', 'Ticket assigné à ' . $user->getUserIdentifier() . '.');
        } catch (\LogicException $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirectToRoute('ticket_show', ['id' => $ticket->getId()]);
    }
}
