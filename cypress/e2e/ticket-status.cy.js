/**
 * Tests du contrôleur Stimulus `status`.
 *
 * Ces tests vérifient les comportements JavaScript qui ne peuvent pas
 * être couverts par PHPUnit (mises à jour AJAX, modale de confirmation,
 * visibilité dynamique des boutons).
 *
 * Prérequis : le serveur Symfony doit tourner sur http://localhost:8000
 *   symfony server:start
 */
describe('Contrôleur de statut Stimulus', () => {

    // URL du ticket créé pour les tests, stockée entre les blocs
    let ticketUrl

    // Création d'un ticket via l'interface avant tous les tests
    before(() => {
        cy.login('tech@mini-glpi.fr', 'password')
        cy.visit('/tickets/new')
        cy.get('#ticket_title').type('Ticket Cypress E2E')
        cy.get('#ticket_description').type('Description pour les tests Cypress end-to-end.')
        cy.get('#ticket_priority').select('HIGH')
        cy.get('button[type="submit"]').click()

        // Récupérer l'URL du ticket depuis la liste
        cy.url().should('include', '/tickets')
        cy.contains('Ticket Cypress E2E').click()
        cy.url().then((url) => { ticketUrl = url })
    })

    beforeEach(() => {
        cy.login('tech@mini-glpi.fr', 'password')
    })

    // ----------------------------------------------------------------
    // État initial
    // ----------------------------------------------------------------

    it('le badge affiche "Ouvert" pour un ticket nouvellement créé', () => {
        cy.visit(ticketUrl)
        cy.get('[data-status-target="badge"]').should('contain', 'Ouvert')
        cy.get('[data-status-target="start"]').should('not.have.class', 'hidden')
        cy.get('[data-status-target="close"]').should('not.have.class', 'hidden')
    })

    // ----------------------------------------------------------------
    // Démarrer la prise en charge (OPEN → IN_PROGRESS)
    // ----------------------------------------------------------------

    it('cliquer sur "Démarrer" met à jour le badge sans rechargement de page', () => {
        cy.visit(ticketUrl)
        cy.get('[data-status-target="start"]').click()

        // Le badge change dynamiquement (pas de rechargement)
        cy.get('[data-status-target="badge"]').should('contain', 'En cours')

        // Le bouton "Démarrer" se masque
        cy.get('[data-status-target="start"]').should('have.class', 'hidden')

        // Le bouton "Fermer" reste visible
        cy.get('[data-status-target="close"]').should('not.have.class', 'hidden')
    })

    // ----------------------------------------------------------------
    // Fermer un ticket — modale de confirmation
    // ----------------------------------------------------------------

    it('cliquer sur "Fermer" affiche la modale de confirmation', () => {
        cy.visit(ticketUrl)
        cy.get('[data-status-target="close"]').click()

        cy.get('[data-status-target="modal"]').should('be.visible')
        cy.get('[data-status-target="modalMessage"]')
            .should('contain', 'Êtes-vous sûr de vouloir fermer ce ticket ?')
    })

    it('annuler dans la modale ne modifie pas le statut', () => {
        cy.visit(ticketUrl)
        cy.get('[data-status-target="close"]').click()
        cy.get('[data-status-target="modal"]').should('be.visible')

        cy.get('[data-action="click->status#cancelModal"]').click()

        cy.get('[data-status-target="modal"]').should('not.be.visible')
        // Le statut ne doit pas être passé à "Fermé" (peu importe le statut courant)
        cy.get('[data-status-target="badge"]').should('not.contain', 'Fermé')
    })

    it('confirmer la fermeture met à jour le badge et affiche "Réouvrir"', () => {
        cy.visit(ticketUrl)
        cy.get('[data-status-target="close"]').click()
        cy.get('[data-action="click->status#confirmModal"]').click()

        cy.get('[data-status-target="badge"]').should('contain', 'Fermé')
        cy.get('[data-status-target="close"]').should('have.class', 'hidden')
        cy.get('[data-status-target="reopen"]').should('not.have.class', 'hidden')
    })

    // ----------------------------------------------------------------
    // Réouvrir un ticket — modale de confirmation (ROLE_TECH)
    // ----------------------------------------------------------------

    it('cliquer sur "Réouvrir" affiche la modale de confirmation', () => {
        cy.visit(ticketUrl)
        cy.get('[data-status-target="reopen"]').should('not.have.class', 'hidden')
        cy.get('[data-status-target="reopen"]').click()

        cy.get('[data-status-target="modal"]').should('be.visible')
        cy.get('[data-status-target="modalMessage"]')
            .should('contain', 'Êtes-vous sûr de vouloir réouvrir ce ticket ?')
    })

    it('confirmer la réouverture remet le badge à "Ouvert"', () => {
        cy.visit(ticketUrl)
        cy.get('[data-status-target="reopen"]').click()
        cy.get('[data-action="click->status#confirmModal"]').click()

        cy.get('[data-status-target="badge"]').should('contain', 'Ouvert')
        cy.get('[data-status-target="start"]').should('not.have.class', 'hidden')
        cy.get('[data-status-target="reopen"]').should('have.class', 'hidden')
    })

})
