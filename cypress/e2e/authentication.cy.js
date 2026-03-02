describe('Authentification', () => {

    it('affiche le formulaire de connexion', () => {
        cy.visit('/login')
        cy.get('h2').should('contain', 'Connexion')
        cy.get('input[name="_username"]').should('exist')
        cy.get('input[name="_password"]').should('exist')
        cy.get('button[type="submit"]').should('contain', 'Se connecter')
    })

    it('redirige vers /login si non authentifié', () => {
        cy.visit('/tickets')
        cy.url().should('include', '/login')
    })

    it('connecte un utilisateur avec des identifiants valides', () => {
        cy.visit('/login')
        cy.get('input[name="_username"]').type('user@mini-glpi.fr')
        cy.get('input[name="_password"]').type('password')
        cy.get('button[type="submit"]').click()
        cy.url().should('include', '/tickets')
    })

    it('affiche une erreur avec un mot de passe incorrect', () => {
        cy.visit('/login')
        cy.get('input[name="_username"]').type('user@mini-glpi.fr')
        cy.get('input[name="_password"]').type('mauvais_mot_de_passe')
        cy.get('button[type="submit"]').click()
        cy.url().should('include', '/login')
        cy.get('.bg-red-50').should('be.visible')
    })

})
