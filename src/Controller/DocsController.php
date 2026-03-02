<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/docs')]
class DocsController extends AbstractController
{
    #[Route('', name: 'app_docs', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('docs/index.html.twig');
    }

    #[Route('/architecture', name: 'app_docs_architecture', methods: ['GET'])]
    public function architecture(): Response
    {
        return $this->render('docs/architecture.html.twig');
    }

    #[Route('/authentification', name: 'app_docs_authentification', methods: ['GET'])]
    public function authentification(): Response
    {
        return $this->render('docs/authentification.html.twig');
    }

    #[Route('/tickets', name: 'app_docs_tickets', methods: ['GET'])]
    public function tickets(): Response
    {
        return $this->render('docs/tickets.html.twig');
    }

    #[Route('/tests', name: 'app_docs_tests', methods: ['GET'])]
    public function tests(): Response
    {
        return $this->render('docs/tests.html.twig');
    }

    #[Route('/cypress', name: 'app_docs_cypress', methods: ['GET'])]
    public function cypress(): Response
    {
        return $this->render('docs/cypress.html.twig');
    }

    #[Route('/docker', name: 'app_docs_docker', methods: ['GET'])]
    public function docker(): Response
    {
        return $this->render('docs/docker.html.twig');
    }
}
