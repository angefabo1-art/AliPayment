/**
 * Script pour la mise à jour en temps réel du taux de change
 * S'exécute toutes les 30 secondes et met à jour automatiquement l'affichage
 * 
 * Éléments requis dans le HTML:
 * - id="exchange-rate-value" (pour afficher le taux)
 * - id="exchange-rate-container" (conteneur principal)
 * - id="rate-last-update" (pour afficher la date de mise à jour)
 */

class ExchangeRateUpdater {
    constructor(options = {}) {
        this.apiEndpoint = options.apiEndpoint || '/api/fetch_exchange_rate.php';
        this.refreshInterval = options.refreshInterval || 30000; // 30 secondes par défaut
        this.retryAttempts = options.retryAttempts || 3;
        this.retryDelay = options.retryDelay || 5000; // 5 secondes
        
        this.exchangeRateElement = document.getElementById('exchange-rate-value');
        this.containerElement = document.getElementById('exchange-rate-container');
        this.updateTimestampElement = document.getElementById('rate-last-update');
        
        this.currentRate = null;
        this.lastUpdate = null;
        this.retryCount = 0;
        this.intervalId = null;
    }
    
    /**
     * Démarrer la mise à jour automatique
     */
    start() {
        console.log('[ExchangeRate] Démarrage de la mise à jour automatique du taux');
        
        // Première mise à jour immédiate
        this.updateExchangeRate();
        
        // Puis mise à jour régulière
        this.intervalId = setInterval(() => {
            this.updateExchangeRate();
        }, this.refreshInterval);
    }
    
    /**
     * Arrêter la mise à jour automatique
     */
    stop() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
            console.log('[ExchangeRate] Mise à jour automatique arrêtée');
        }
    }
    
    /**
     * Récupérer le taux actuel depuis l'API
     */
    async updateExchangeRate() {
        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`Erreur API: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success) {
                this.updateDisplay(data);
                this.retryCount = 0; // Réinitialiser le compteur de tentatives en cas de succès
            } else {
                throw new Error(data.error || 'Erreur inconnue');
            }
        } catch (error) {
            console.error('[ExchangeRate] Erreur lors de la mise à jour:', error);
            this.handleError(error);
        }
    }
    
    /**
     * Mettre à jour l'affichage du taux de change
     */
    updateDisplay(data) {
        const newRate = parseFloat(data.rate);
        const formattedRate = data.formatted_rate || new Intl.NumberFormat('fr-FR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(newRate).replace(/\s/g, ',');
        
        // Mettre à jour le taux affiché
        if (this.exchangeRateElement) {
            const oldRate = this.currentRate;
            this.exchangeRateElement.textContent = formattedRate;
            this.currentRate = newRate;
            
            // Ajouter une animation si le taux a changé
            if (oldRate !== null && oldRate !== newRate) {
                this.animateRateChange(oldRate, newRate);
            }
        }
        
        // Mettre à jour le timestamp
        if (this.updateTimestampElement && data.updated_at) {
            const updateDate = new Date(data.updated_at);
            const formattedDate = this.formatDate(updateDate);
            
            // Seulement mettre à jour si la date a changé
            if (this.lastUpdate !== data.updated_at) {
                this.updateTimestampElement.innerHTML = `
                    <i class="fas fa-sync-alt me-1"></i>Mis à jour le ${formattedDate}
                `;
                this.lastUpdate = data.updated_at;
                this.showUpdateNotification(formattedRate);
            }
        } else if (this.updateTimestampElement && data.is_default) {
            this.updateTimestampElement.innerHTML = `
                <i class="fas fa-info-circle me-1"></i>Taux par défaut
            `;
        }
        
        // Ajouter une classe pour indiquer une mise à jour réussie
        if (this.containerElement) {
            this.containerElement.classList.add('updated');
            setTimeout(() => {
                this.containerElement.classList.remove('updated');
            }, 1000);
        }
    }
    
    /**
     * Animer le changement du taux
     */
    animateRateChange(oldRate, newRate) {
        if (!this.exchangeRateElement) return;
        
        const direction = newRate > oldRate ? 'up' : 'down';
        const color = direction === 'up' ? '#28A745' : '#DC3545';
        
        // Ajouter une animation visuelle
        this.exchangeRateElement.style.transition = 'color 0.3s ease';
        this.exchangeRateElement.style.color = color;
        
        setTimeout(() => {
            this.exchangeRateElement.style.color = '#0052CC';
        }, 300);
        
        console.log(`[ExchangeRate] Taux changé: ${oldRate} → ${newRate} (${direction})`);
    }
    
    /**
     * Afficher une notification de mise à jour
     */
    showUpdateNotification(newRate) {
        if (!this.containerElement) return;
        
        // Créer une notification temporaire
        const notification = document.createElement('div');
        notification.className = 'alert alert-info alert-dismissible fade show';
        notification.innerHTML = `
            <i class="fas fa-bell me-2"></i>
            <strong>Taux de change mis à jour!</strong> Le nouveau taux est: 1 RMB = ${newRate} FCFA
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insérer la notification avant le conteneur du taux
        this.containerElement.parentElement.insertBefore(notification, this.containerElement);
        
        // Auto-fermer après 5 secondes
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }
    
    /**
     * Gérer les erreurs
     */
    handleError(error) {
        if (this.retryCount < this.retryAttempts) {
            this.retryCount++;
            console.warn(`[ExchangeRate] Tentative ${this.retryCount}/${this.retryAttempts} après ${this.retryDelay}ms`);
            
            setTimeout(() => {
                this.updateExchangeRate();
            }, this.retryDelay);
        } else {
            console.error('[ExchangeRate] Échec après plusieurs tentatives');
            this.showErrorNotification(error.message);
        }
    }
    
    /**
     * Afficher une notification d'erreur
     */
    showErrorNotification(errorMessage) {
        if (!this.containerElement) return;
        
        const notification = document.createElement('div');
        notification.className = 'alert alert-warning alert-dismissible fade show';
        notification.innerHTML = `
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Avertissement:</strong> Impossible de mettre à jour le taux. Dernier taux connu affiché.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        this.containerElement.parentElement.insertBefore(notification, this.containerElement);
        
        setTimeout(() => {
            notification.remove();
        }, 8000);
    }
    
    /**
     * Formater la date en français
     */
    formatDate(date) {
        const options = {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        };
        
        const formatter = new Intl.DateTimeFormat('fr-FR', options);
        const parts = formatter.formatToParts(date);
        
        let day, month, year, hour, minute;
        for (let part of parts) {
            if (part.type === 'day') day = part.value;
            if (part.type === 'month') month = part.value;
            if (part.type === 'year') year = part.value;
            if (part.type === 'hour') hour = part.value;
            if (part.type === 'minute') minute = part.value;
        }
        
        return `${day}/${month}/${year} à ${hour}:${minute}`;
    }
    
    /**
     * Obtenir le taux actuel
     */
    getRate() {
        return this.currentRate;
    }
}

// Initialiser automatiquement au chargement de la page
document.addEventListener('DOMContentLoaded', () => {
    // Vérifier si le conteneur existe
    if (document.getElementById('exchange-rate-container')) {
        window.exchangeRateUpdater = new ExchangeRateUpdater({
            refreshInterval: 30000, // Mettre à jour toutes les 30 secondes
            retryAttempts: 3,
            retryDelay: 5000
        });
        
        // Démarrer la mise à jour
        window.exchangeRateUpdater.start();
        
        // Arrêter la mise à jour avant de quitter la page
        window.addEventListener('beforeunload', () => {
            window.exchangeRateUpdater.stop();
        });
    }
});
