/**
 * Commande de connexion réutilisable.
 *
 * cy.session() met en cache les cookies de session entre les tests :
 * la vraie requête de login n'est effectuée qu'une seule fois par email.
 *
 * Usage : cy.login('user@mini-glpi.fr', 'password')
 */
Cypress.Commands.add('login', (email, password) => {
    cy.session(
        email,
        () => {
            cy.visit('/login')
            cy.get('input[name="_username"]').type(email)
            cy.get('input[name="_password"]').type(password)
            cy.get('button[type="submit"]').click()
            cy.url().should('include', '/tickets')
        },
        { cacheAcrossSpecs: true }
    )
})
