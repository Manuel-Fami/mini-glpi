import { Controller } from '@hotwired/stimulus';

/*
 * Contrôleur Stimulus pour le changement de statut d'un ticket sans rechargement.
 *
 * Usage HTML :
 *   data-controller="status"
 *   data-status-url-value="..."
 *   data-status-token-value="..."
 *
 * Targets :
 *   badge   → le badge de statut affiché dans la sidebar
 *   start   → bouton "Démarrer" (OPEN → IN_PROGRESS)
 *   close   → bouton "Fermer"   (→ CLOSED)
 *   modify  → lien "Modifier"   (masqué quand CLOSED)
 *   error   → zone d'affichage des erreurs
 */
export default class extends Controller {
    static targets = ['badge', 'start', 'close', 'modify', 'error'];
    static values  = { url: String, token: String };

    async start() {
        await this.changeStatus('in_progress');
    }

    async close() {
        await this.changeStatus('closed');
    }

    async changeStatus(status) {
        this.errorTarget.classList.add('hidden');

        let data;

        try {
            const response = await fetch(this.urlValue, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body:    `status=${status}&_token=${encodeURIComponent(this.tokenValue)}`,
            });

            data = await response.json();

            if (!response.ok) {
                this.errorTarget.textContent = data.error ?? 'Une erreur est survenue.';
                this.errorTarget.classList.remove('hidden');
                return;
            }
        } catch {
            this.errorTarget.textContent = 'Impossible de joindre le serveur.';
            this.errorTarget.classList.remove('hidden');
            return;
        }

        this.updateBadge(data.status, data.label);
        this.updateButtons(data.status);
    }

    updateBadge(status, label) {
        const badge = this.badgeTarget;
        badge.textContent = label;

        const classes = {
            open:        'bg-yellow-100 text-yellow-800',
            in_progress: 'bg-blue-100 text-blue-800',
            closed:      'bg-gray-100 text-gray-600',
        };

        badge.className = `inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium ${classes[status] ?? classes.closed}`;
    }

    updateButtons(status) {
        if (this.hasStartTarget) {
            this.startTarget.classList.toggle('hidden', status !== 'open');
        }
        if (this.hasCloseTarget) {
            this.closeTarget.classList.toggle('hidden', status === 'closed');
        }
        if (this.hasModifyTarget) {
            this.modifyTarget.classList.toggle('hidden', status === 'closed');
        }
    }
}
