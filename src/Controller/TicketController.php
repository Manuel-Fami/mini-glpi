<?php

namespace App\Controller;

use App\Entity\Ticket;
use App\Enum\TicketStatus;
use App\Entity\User;
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
    /** Affiche la liste de tous les tickets récupérés depuis la BDD. */
    #[Route('', name: 'ticket_list', methods: ['GET'])]
    public function list(TicketRepository $repository): Response
    {
        return $this->render('ticket/list.html.twig', [
            'tickets' => $repository->findAll(),
        ]);
    }

    /**
     * Affiche le formulaire de création (GET) et le traite à la soumission (POST).
     * Délègue la persistance au TicketService. L'auteur est l'utilisateur connecté.
     */
    #[Route('/new', name: 'ticket_new', methods: ['GET', 'POST'])]
    public function new(Request $request, TicketService $service): Response
    {
        $ticket = new Ticket();
        $form = $this->createForm(TicketType::class, $ticket);
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

    /**
     * Affiche le détail d'un ticket avec ses commentaires.
     * Le ParamConverter Doctrine résout automatiquement {id} en objet Ticket (404 si introuvable).
     * Calcule les droits de commentaire : ticket ouvert ET (ROLE_TECH OU utilisateur assigné).
     */
    #[Route('/{id}', name: 'ticket_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Ticket $ticket, UserRepository $userRepository): Response
    {
        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isAssigned  = $ticket->getAssignedTo()?->getId() === $currentUser->getId();
        $canComment  = $ticket->getStatus() !== TicketStatus::CLOSED
                       && ($this->isGranted('ROLE_TECH') || $isAssigned);

        return $this->render('ticket/show.html.twig', [
            'ticket'     => $ticket,
            'allUsers'   => $userRepository->findAll(),
            'canComment' => $canComment,
        ]);
    }

    /**
     * Affiche le formulaire de modification (GET) et le traite (POST).
     * Bloque toute modification si le ticket est à l'état "closed".
     */
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

    /**
     * Ferme un ticket via POST.
     * Attrape les LogicException levées par le service (ex: ticket déjà fermé)
     * et les affiche en flash message d'erreur.
     */
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

    /**
     * Change le statut d'un ticket via une requête AJAX — retourne du JSON, pas du HTML.
     * Vérifie le token CSRF manuellement (pas de formulaire Symfony ici).
     * Seul ROLE_TECH peut rouvrir un ticket. Les transitions invalides lèvent une exception.
     */
    #[Route('/{id}/status', name: 'ticket_status', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateStatus(Ticket $ticket, Request $request, TicketService $service): JsonResponse
    {
        if (!$this->isCsrfTokenValid('ticket_status_' . $ticket->getId(), $request->request->getString('_token'))) {
            return $this->json(['error' => 'Jeton CSRF invalide.'], Response::HTTP_FORBIDDEN);
        }

        $newStatus = $request->request->getString('status');

        if ($newStatus === 'open' && !$this->isGranted('ROLE_TECH')) {
            return $this->json(['error' => 'Action non autorisée.'], Response::HTTP_FORBIDDEN);
        }

        try {
            match ($newStatus) {
                'in_progress' => $service->startProgress($ticket),
                'closed'      => $service->close($ticket),
                'open'        => $service->reopen($ticket),
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

    /**
     * Ajoute un commentaire à un ticket.
     * Conditions : ticket non fermé ET utilisateur autorisé (ROLE_TECH ou assigné au ticket).
     */
    #[Route('/{id}/comment', name: 'ticket_comment', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function comment(Ticket $ticket, Request $request, TicketService $service): Response
    {
        if ($ticket->getStatus() === TicketStatus::CLOSED) {
            $this->addFlash('error', 'Impossible de commenter un ticket fermé.');
            return $this->redirectToRoute('ticket_show', ['id' => $ticket->getId()]);
        }

        /** @var User $currentUser */
        $currentUser = $this->getUser();
        $isAssigned  = $ticket->getAssignedTo()?->getId() === $currentUser->getId();

        if (!$this->isGranted('ROLE_TECH') && !$isAssigned) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à commenter ce ticket.');
            return $this->redirectToRoute('ticket_show', ['id' => $ticket->getId()]);
        }

        $content = trim($request->request->getString('content'));

        if ($content === '') {
            $this->addFlash('error', 'Le commentaire ne peut pas être vide.');
            return $this->redirectToRoute('ticket_show', ['id' => $ticket->getId()]);
        }

        $service->addComment($ticket, $currentUser, $content);
        $this->addFlash('success', 'Commentaire ajouté.');

        return $this->redirectToRoute('ticket_show', ['id' => $ticket->getId()]);
    }

    /**
     * Assigne un ticket à un utilisateur.
     * ROLE_TECH : peut assigner à n'importe quel utilisateur (choix via user_id).
     * ROLE_USER : peut uniquement s'auto-assigner, et seulement si le ticket est libre.
     */
    #[Route('/{id}/assign', name: 'ticket_assign', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function assign(Ticket $ticket, Request $request, TicketService $service, UserRepository $userRepository): Response
    {
        if ($ticket->getStatus() === TicketStatus::CLOSED) {
            $this->addFlash('error', 'Impossible d\'assigner un ticket fermé.');
            return $this->redirectToRoute('ticket_show', ['id' => $ticket->getId()]);
        }

        /** @var \App\Entity\User $currentUser */
        $currentUser = $this->getUser();

        if ($this->isGranted('ROLE_TECH')) {
            $userId = $request->request->getInt('user_id');
            $user   = $userRepository->find($userId);

            if (!$user) {
                $this->addFlash('error', 'Utilisateur introuvable.');
                return $this->redirectToRoute('ticket_show', ['id' => $ticket->getId()]);
            }

            $service->assign($ticket, $user);
            $this->addFlash('success', 'Ticket assigné à ' . $user->getUserIdentifier() . '.');
        } else {
            if ($ticket->getAssignedTo() !== null) {
                $this->addFlash('error', 'Ce ticket est déjà assigné.');
                return $this->redirectToRoute('ticket_show', ['id' => $ticket->getId()]);
            }

            $service->assign($ticket, $currentUser);
            $this->addFlash('success', 'Ticket assigné à vous.');
        }

        return $this->redirectToRoute('ticket_show', ['id' => $ticket->getId()]);
    }
}
