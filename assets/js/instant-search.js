/**
 * Sistema de Búsqueda Instantánea
 * Búsqueda en tiempo real sin recargar página
 */

class InstantSearch {
    constructor(options = {}) {
        this.searchInput = options.searchInput || document.querySelector('[data-instant-search]');
        this.resultsContainer = options.resultsContainer || document.querySelector('[data-search-results]');
        this.endpoint = options.endpoint || 'api/search.php';
        this.minChars = options.minChars || 2;
        this.debounceTime = options.debounceTime || 300;
        this.filters = options.filters || {};
        
        this.debounceTimer = null;
        this.currentQuery = '';
        this.isLoading = false;
        
        this.init();
    }
    
    init() {
        if (!this.searchInput) return;
        
        this.setupEventListeners();
        this.createLoadingIndicator();
        this.createNoResultsMessage();
    }
    
    setupEventListeners() {
        // Búsqueda mientras se escribe
        this.searchInput.addEventListener('input', (e) => {
            this.handleSearch(e.target.value);
        });
        
        // Limpiar resultados al hacer focus
        this.searchInput.addEventListener('focus', () => {
            if (this.currentQuery.length >= this.minChars) {
                this.showResults();
            }
        });
        
        // Ocultar resultados al perder focus (con delay para permitir clicks)
        this.searchInput.addEventListener('blur', () => {
            setTimeout(() => {
                this.hideResults();
            }, 200);
        });
        
        // Navegación con teclado
        this.searchInput.addEventListener('keydown', (e) => {
            this.handleKeyboardNavigation(e);
        });
        
        // Escuchar cambios en filtros
        document.addEventListener('filterChanged', (e) => {
            this.filters = { ...this.filters, ...e.detail };
            if (this.currentQuery.length >= this.minChars) {
                this.performSearch(this.currentQuery);
            }
        });
    }
    
    handleSearch(query) {
        query = query.trim();
        this.currentQuery = query;
        
        // Limpiar timer anterior
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
        
        if (query.length < this.minChars) {
            this.hideResults();
            return;
        }
        
        // Debounce para evitar demasiadas peticiones
        this.debounceTimer = setTimeout(() => {
            this.performSearch(query);
        }, this.debounceTime);
    }
    
    async performSearch(query) {
        if (this.isLoading) return;
        
        this.isLoading = true;
        this.showLoading();
        
        try {
            const searchParams = new URLSearchParams({
                q: query,
                ...this.filters
            });
            
            const response = await fetch(`api/search_debug.php?${searchParams}`, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                this.displayResults(result.results, query);
                if (result.debug) {
                    console.log('🔍 Search Debug:', result.debug);
                }
            } else {
                console.error('❌ Search Error:', result);
                this.showError(result.error || 'Error en la búsqueda');
            }
            
        } catch (error) {
            console.error('Search error:', error);
            this.showError('Error de conexión');
        } finally {
            this.isLoading = false;
            this.hideLoading();
        }
    }
    
    displayResults(data, query) {
        if (!this.resultsContainer) return;
        
        if (data.length === 0) {
            this.showNoResults(query);
            return;
        }
        
        let html = '<div class="search-results-list">';
        
        data.forEach((item, index) => {
            html += this.renderResultItem(item, index);
        });
        
        html += '</div>';
        
        this.resultsContainer.innerHTML = html;
        this.showResults();
        
        // Añadir event listeners a los resultados
        this.addResultListeners();
    }
    
    renderResultItem(item, index) {
        const highlightedName = this.highlightText(item.nombre, this.currentQuery);
        const stockStatus = this.getStockStatus(item.cantidad);
        
        return `
            <div class="search-result-item" data-index="${index}" data-id="${item.id}">
                <div class="result-info">
                    <span class="result-name">${highlightedName}</span>
                    <span class="result-description">${this.escapeHtml(item.descripcion)}</span>
                    <div class="result-meta">
                        <span class="result-quantity">
                            <i class="fas fa-boxes"></i>
                            ${item.cantidad} unidades
                        </span>
                        <span class="result-location">
                            <i class="fas fa-map-marker-alt"></i>
                            ${this.escapeHtml(item.almacen)}
                        </span>
                    </div>
                </div>
                <span class="result-status ${item.estado.toLowerCase()}">${item.estado}</span>
            </div>
        `;
    }
    
    getStockStatus(cantidad) {
        if (cantidad <= 0) {
            return { class: 'stock-empty', text: 'Sin stock' };
        } else if (cantidad <= 5) {
            return { class: 'stock-low', text: 'Stock bajo' };
        } else {
            return { class: 'stock-ok', text: 'Stock normal' };
        }
    }
    
    highlightText(text, query) {
        if (!query) return this.escapeHtml(text);
        
        const regex = new RegExp(`(${this.escapeRegex(query)})`, 'gi');
        return this.escapeHtml(text).replace(regex, '<mark>$1</mark>');
    }
    
    addResultListeners() {
        const resultItems = this.resultsContainer.querySelectorAll('.search-result-item');
        
        resultItems.forEach(item => {
            // Click en el item
            item.addEventListener('click', (e) => {
                if (!e.target.closest('.result-actions')) {
                    const id = item.dataset.id;
                    this.selectResult(id);
                }
            });
            
            // Hover effect
            item.addEventListener('mouseenter', () => {
                this.clearActiveResult();
                item.classList.add('active');
            });
        });
        
        // Event listeners para botones de acción
        const actionButtons = this.resultsContainer.querySelectorAll('[data-action]');
        actionButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.stopPropagation();
                const action = button.dataset.action;
                const id = button.dataset.id;
                this.handleAction(action, id);
            });
        });
    }
    
    handleAction(action, id) {
        switch (action) {
            case 'view':
                window.location.href = `productos/ver-producto.php?id=${id}`;
                break;
            case 'edit':
                window.location.href = `productos/editar.php?id=${id}`;
                break;
            case 'select':
                this.selectResult(id);
                break;
        }
    }
    
    selectResult(id) {
        // Emitir evento personalizado
        const event = new CustomEvent('resultSelected', {
            detail: { id: id }
        });
        document.dispatchEvent(event);
        
        this.hideResults();
    }
    
    handleKeyboardNavigation(e) {
        const items = this.resultsContainer.querySelectorAll('.search-result-item');
        const activeItem = this.resultsContainer.querySelector('.search-result-item.active');
        
        let currentIndex = -1;
        if (activeItem) {
            currentIndex = parseInt(activeItem.dataset.index);
        }
        
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.navigateResults(items, currentIndex + 1);
                break;
            case 'ArrowUp':
                e.preventDefault();
                this.navigateResults(items, currentIndex - 1);
                break;
            case 'Enter':
                e.preventDefault();
                if (activeItem) {
                    const id = activeItem.dataset.id;
                    this.selectResult(id);
                }
                break;
            case 'Escape':
                e.preventDefault();
                this.hideResults();
                this.searchInput.blur();
                break;
        }
    }
    
    navigateResults(items, newIndex) {
        if (items.length === 0) return;
        
        // Normalizar índice
        if (newIndex < 0) newIndex = items.length - 1;
        if (newIndex >= items.length) newIndex = 0;
        
        this.clearActiveResult();
        items[newIndex].classList.add('active');
        
        // Scroll si es necesario
        items[newIndex].scrollIntoView({
            block: 'nearest',
            behavior: 'smooth'
        });
    }
    
    clearActiveResult() {
        const activeItem = this.resultsContainer.querySelector('.search-result-item.active');
        if (activeItem) {
            activeItem.classList.remove('active');
        }
    }
    
    createLoadingIndicator() {
        this.loadingIndicator = document.createElement('div');
        this.loadingIndicator.className = 'search-loading';
        this.loadingIndicator.innerHTML = `
            <div class="loading-spinner"></div>
            <span>Buscando...</span>
        `;
        this.loadingIndicator.style.display = 'none';
    }
    
    createNoResultsMessage() {
        this.noResultsMessage = document.createElement('div');
        this.noResultsMessage.className = 'search-no-results';
        this.noResultsMessage.style.display = 'none';
    }
    
    showLoading() {
        if (this.resultsContainer && this.loadingIndicator) {
            this.resultsContainer.innerHTML = '';
            this.resultsContainer.appendChild(this.loadingIndicator);
            this.loadingIndicator.style.display = 'flex';
            this.showResults();
        }
    }
    
    hideLoading() {
        if (this.loadingIndicator) {
            this.loadingIndicator.style.display = 'none';
        }
    }
    
    showNoResults(query) {
        if (this.resultsContainer && this.noResultsMessage) {
            this.noResultsMessage.innerHTML = `
                <div class="no-results-content">
                    <i class="fas fa-search"></i>
                    <h4>No se encontraron resultados</h4>
                    <p>No hay productos que coincidan con "<strong>${this.escapeHtml(query)}</strong>"</p>
                    <small>Intenta con otros términos de búsqueda</small>
                </div>
            `;
            this.resultsContainer.innerHTML = '';
            this.resultsContainer.appendChild(this.noResultsMessage);
            this.noResultsMessage.style.display = 'block';
            this.showResults();
        }
    }
    
    showError(message) {
        if (this.resultsContainer) {
            this.resultsContainer.innerHTML = `
                <div class="search-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span>${this.escapeHtml(message)}</span>
                </div>
            `;
            this.showResults();
        }
    }
    
    showResults() {
        if (this.resultsContainer) {
            this.resultsContainer.style.display = 'block';
            this.resultsContainer.classList.add('visible');
        }
    }
    
    hideResults() {
        if (this.resultsContainer) {
            this.resultsContainer.classList.remove('visible');
            setTimeout(() => {
                this.resultsContainer.style.display = 'none';
            }, 200);
        }
    }
    
    // Utilidades
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }
    
    formatPrice(price) {
        return parseFloat(price).toFixed(2);
    }
}

// Auto-inicializar búsquedas instantáneas
document.addEventListener('DOMContentLoaded', () => {
    const searchInputs = document.querySelectorAll('[data-instant-search]');
    
    searchInputs.forEach(input => {
        const endpoint = input.dataset.endpoint || 'api/search.php';
        const resultsContainer = document.querySelector(input.dataset.results || '[data-search-results]');
        
        new InstantSearch({
            searchInput: input,
            resultsContainer: resultsContainer,
            endpoint: endpoint
        });
    });
});