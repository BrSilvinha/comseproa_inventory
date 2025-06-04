/* ============================================
   OPTIMIZACIONES PARA PRODUCTOS TABLA
   ============================================ */

// ===== OPTIMIZADOR DE RENDIMIENTO =====
class PerformanceOptimizer {
    constructor() {
        this.init();
    }

    init() {
        this.lazyLoadImages();
        this.optimizeScrolling();
        this.preloadCriticalPages();
        this.optimizeAnimations();
    }

    // Lazy loading para imágenes si las hay
    lazyLoadImages() {
        if ('IntersectionObserver' in window) {
            const imageObserver = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        imageObserver.unobserve(img);
                    }
                });
            });

            document.querySelectorAll('img[data-src]').forEach(img => {
                imageObserver.observe(img);
            });
        }
    }

    // Optimizar scroll en tabla
    optimizeScrolling() {
        let ticking = false;
        const table = document.querySelector('.table-container');
        
        if (table) {
            const handleScroll = () => {
                if (!ticking) {
                    requestAnimationFrame(() => {
                        // Aquí puedes añadir lógica de scroll optimizada
                        ticking = false;
                    });
                    ticking = true;
                }
            };

            table.addEventListener('scroll', handleScroll, { passive: true });
        }
    }

    // Precargar páginas críticas
    preloadCriticalPages() {
        const currentPage = new URLSearchParams(window.location.search).get('pagina') || 1;
        const nextPage = parseInt(currentPage) + 1;
        const prevPage = parseInt(currentPage) - 1;

        // Precargar página siguiente
        if (nextPage) {
            this.preloadPage(nextPage);
        }

        // Precargar página anterior
        if (prevPage > 0) {
            this.preloadPage(prevPage);
        }
    }

    preloadPage(pageNumber) {
        const link = document.createElement('link');
        link.rel = 'prefetch';
        
        const url = new URL(window.location);
        url.searchParams.set('pagina', pageNumber);
        link.href = url.toString();
        
        document.head.appendChild(link);
    }

    // Optimizar animaciones basado en preferencias del usuario
    optimizeAnimations() {
        const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        
        if (prefersReducedMotion.matches) {
            document.body.classList.add('reduced-motion');
        }

        prefersReducedMotion.addListener((e) => {
            if (e.matches) {
                document.body.classList.add('reduced-motion');
            } else {
                document.body.classList.remove('reduced-motion');
            }
        });
    }
}

// ===== MEJORAS EN LA PAGINACIÓN =====
class PaginationEnhancer {
    constructor() {
        this.init();
    }

    init() {
        this.addKeyboardNavigation();
        this.addLoadingStates();
        this.improveUrlHandling();
    }

    addKeyboardNavigation() {
        document.addEventListener('keydown', (e) => {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'ArrowLeft':
                        e.preventDefault();
                        this.goToPreviousPage();
                        break;
                    case 'ArrowRight':
                        e.preventDefault();
                        this.goToNextPage();
                        break;
                }
            }
        });
    }

    goToPreviousPage() {
        const prevBtn = document.querySelector('.pagination-btn.prev');
        if (prevBtn && !prevBtn.classList.contains('disabled')) {
            this.navigateWithLoading(prevBtn.href);
        }
    }

    goToNextPage() {
        const nextBtn = document.querySelector('.pagination-btn.next');
        if (nextBtn && !nextBtn.classList.contains('disabled')) {
            this.navigateWithLoading(nextBtn.href);
        }
    }

    addLoadingStates() {
        const paginationLinks = document.querySelectorAll('.pagination-btn');
        
        paginationLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                if (!link.classList.contains('current')) {
                    this.showLoadingState();
                }
            });
        });

        const pageSelect = document.getElementById('pageSelect');
        if (pageSelect) {
            pageSelect.addEventListener('change', () => {
                this.showLoadingState();
            });
        }
    }

    showLoadingState() {
        const table = document.querySelector('.table-container');
        if (table) {
            table.classList.add('table-loading');
            
            const spinner = document.createElement('div');
            spinner.className = 'loading-spinner';
            table.appendChild(spinner);
        }
    }

    navigateWithLoading(url) {
        this.showLoadingState();
        window.location.href = url;
    }

    improveUrlHandling() {
        // Mejorar el manejo de URLs para SEO y usabilidad
        const form = document.querySelector('.search-form');
        if (form) {
            form.addEventListener('submit', (e) => {
                const formData = new FormData(form);
                const params = new URLSearchParams(formData);
                
                // Limpiar parámetros vacíos
                for (let [key, value] of params.entries()) {
                    if (!value.trim()) {
                        params.delete(key);
                    }
                }
                
                const newUrl = `${window.location.pathname}?${params.toString()}`;
                window.history.pushState({}, '', newUrl);
            });
        }
    }
}

// ===== OPTIMIZADOR DE TABLA =====
class TableOptimizer {
    constructor() {
        this.init();
    }

    init() {
        this.addVirtualScrolling();
        this.optimizeStockButtons();
        this.addBulkActions();
        this.improveAccessibility();
    }

    // Virtual scrolling para tablas muy grandes (opcional)
    addVirtualScrolling() {
        const table = document.querySelector('.products-table tbody');
        if (table && table.children.length > 50) {
            this.implementVirtualScrolling(table);
        }
    }

    implementVirtualScrolling(tbody) {
        // Implementación básica de virtual scrolling
        const rows = Array.from(tbody.children);
        const rowHeight = 60; // altura estimada de cada fila
        const visibleRows = Math.ceil(window.innerHeight / rowHeight) + 5;
        
        let startIndex = 0;
        
        const updateVisibleRows = () => {
            const scrollTop = tbody.scrollTop;
            startIndex = Math.floor(scrollTop / rowHeight);
            const endIndex = Math.min(startIndex + visibleRows, rows.length);
            
            // Ocultar todas las filas
            rows.forEach(row => row.style.display = 'none');
            
            // Mostrar solo las filas visibles
            for (let i = startIndex; i < endIndex; i++) {
                if (rows[i]) {
                    rows[i].style.display = '';
                }
            }
        };
        
        tbody.addEventListener('scroll', updateVisibleRows, { passive: true });
        updateVisibleRows(); // Inicializar
    }

    optimizeStockButtons() {
        // Debounce para botones de stock
        let stockUpdateTimeout;
        
        document.addEventListener('click', (e) => {
            if (e.target.closest('.stock-btn')) {
                clearTimeout(stockUpdateTimeout);
                stockUpdateTimeout = setTimeout(() => {
                    // El manejo se hace en el archivo principal
                }, 300);
            }
        });
    }

    addBulkActions() {
        // Mejorar acciones en lote
        const selectAllCheckbox = document.querySelector('.selection-header input[type="checkbox"]');
        const rowCheckboxes = document.querySelectorAll('.selection-checkbox');
        
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                rowCheckboxes.forEach(checkbox => {
                    if (e.target.checked) {
                        checkbox.classList.add('checked');
                    } else {
                        checkbox.classList.remove('checked');
                    }
                });
            });
        }
    }

    improveAccessibility() {
        // Mejorar accesibilidad
        const table = document.querySelector('.products-table');
        if (table) {
            table.setAttribute('role', 'table');
            table.setAttribute('aria-label', 'Tabla de productos');
            
            // Añadir aria-labels a botones
            document.querySelectorAll('.btn-action').forEach(btn => {
                if (!btn.getAttribute('aria-label')) {
                    const title = btn.getAttribute('title');
                    if (title) {
                        btn.setAttribute('aria-label', title);
                    }
                }
            });
            
            // Mejorar navegación por teclado
            document.querySelectorAll('.stock-btn, .btn-action').forEach(btn => {
                btn.setAttribute('tabindex', '0');
                
                btn.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        btn.click();
                    }
                });
            });
        }
    }
}

// ===== OPTIMIZADOR DE BÚSQUEDA =====
class SearchOptimizer {
    constructor() {
        this.init();
    }

    init() {
        this.addInstantSearch();
        this.addSearchHistory();
        this.improveFilters();
    }

    addInstantSearch() {
        const searchInput = document.querySelector('input[name="busqueda"]');
        if (searchInput) {
            let searchTimeout;
            
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                
                if (e.target.value.length >= 3) {
                    searchTimeout = setTimeout(() => {
                        this.performInstantSearch(e.target.value);
                    }, 500);
                }
            });
        }
    }

    performInstantSearch(query) {
        // Implementar búsqueda instantánea vía AJAX
        const formData = new FormData();
        formData.append('busqueda_instantanea', query);
        
        fetch('busqueda_instantanea.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            this.displayInstantResults(data);
        })
        .catch(error => {
            console.log('Búsqueda instantánea no disponible:', error);
        });
    }

    displayInstantResults(results) {
        // Mostrar resultados instantáneos
        let dropdown = document.querySelector('.search-dropdown');
        
        if (!dropdown) {
            dropdown = document.createElement('div');
            dropdown.className = 'search-dropdown';
            document.querySelector('.search-input-group').appendChild(dropdown);
        }
        
        if (results.length > 0) {
            dropdown.innerHTML = results.map(item => `
                <div class="search-result-item" data-id="${item.id}">
                    <strong>${item.nombre}</strong>
                    <small>${item.categoria} - ${item.almacen}</small>
                </div>
            `).join('');
            
            dropdown.style.display = 'block';
        } else {
            dropdown.style.display = 'none';
        }
    }

    addSearchHistory() {
        const searchInput = document.querySelector('input[name="busqueda"]');
        if (searchInput && localStorage) {
            const form = searchInput.closest('form');
            
            form.addEventListener('submit', () => {
                const query = searchInput.value.trim();
                if (query) {
                    let history = JSON.parse(localStorage.getItem('search_history') || '[]');
                    
                    // Añadir al historial sin duplicados
                    history = history.filter(item => item !== query);
                    history.unshift(query);
                    history = history.slice(0, 10); // Mantener solo 10 elementos
                    
                    localStorage.setItem('search_history', JSON.stringify(history));
                }
            });
        }
    }

    improveFilters() {
        // Mejorar la experiencia de filtros
        const filterTags = document.querySelectorAll('.filter-tag .remove-filter');
        
        filterTags.forEach(tag => {
            tag.addEventListener('click', (e) => {
                e.preventDefault();
                
                // Añadir animación de salida
                const filterTag = tag.closest('.filter-tag');
                filterTag.style.animation = 'slideOut 0.3s ease';
                
                setTimeout(() => {
                    window.location.href = tag.href;
                }, 300);
            });
        });
    }
}

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', () => {
    // Inicializar optimizadores
    new PerformanceOptimizer();
    new PaginationEnhancer();
    new TableOptimizer();
    new SearchOptimizer();
    
    // Añadir indicador de carga para navegación
    window.addEventListener('beforeunload', () => {
        document.body.classList.add('page-loading');
    });
    
    // Mejorar experiencia de usuario
    addUserExperienceEnhancements();
});

// ===== MEJORAS ADICIONALES DE UX =====
function addUserExperienceEnhancements() {
    // Añadir tooltips mejorados
    addEnhancedTooltips();
    
    // Mejorar feedback visual
    enhanceVisualFeedback();
    
    // Añadir shortcuts de teclado
    addKeyboardShortcuts();
}

function addEnhancedTooltips() {
    const elements = document.querySelectorAll('[title]');
    
    elements.forEach(element => {
        element.addEventListener('mouseenter', (e) => {
            const tooltip = createTooltip(e.target.getAttribute('title'));
            document.body.appendChild(tooltip);
            
            const rect = e.target.getBoundingClientRect();
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            tooltip.style.top = rect.bottom + 10 + 'px';
        });
        
        element.addEventListener('mouseleave', () => {
            document.querySelectorAll('.enhanced-tooltip').forEach(tooltip => {
                tooltip.remove();
            });
        });
    });
}

function createTooltip(text) {
    const tooltip = document.createElement('div');
    tooltip.className = 'enhanced-tooltip';
    tooltip.textContent = text;
    tooltip.style.cssText = `
        position: absolute;
        background: #1a1a1a;
        color: white;
        padding: 8px 12px;
        border-radius: 6px;
        font-size: 12px;
        z-index: 10000;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        animation: tooltipFadeIn 0.2s ease;
    `;
    
    return tooltip;
}

function enhanceVisualFeedback() {
    // Mejorar feedback en clics
    document.addEventListener('click', (e) => {
        if (e.target.closest('.btn-action, .stock-btn, .pagination-btn')) {
            const button = e.target.closest('.btn-action, .stock-btn, .pagination-btn');
            
            // Crear efecto ripple
            const ripple = document.createElement('span');
            ripple.className = 'ripple-effect';
            
            const rect = button.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            const x = e.clientX - rect.left - size / 2;
            const y = e.clientY - rect.top - size / 2;
            
            ripple.style.cssText = `
                position: absolute;
                width: ${size}px;
                height: ${size}px;
                left: ${x}px;
                top: ${y}px;
                background: rgba(255,255,255,0.3);
                border-radius: 50%;
                transform: scale(0);
                animation: ripple 0.6s ease-out;
                pointer-events: none;
            `;
            
            button.style.position = 'relative';
            button.style.overflow = 'hidden';
            button.appendChild(ripple);
            
            setTimeout(() => ripple.remove(), 600);
        }
    });
}

function addKeyboardShortcuts() {
    document.addEventListener('keydown', (e) => {
        // Solo actuar si no estamos en un input
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
            return;
        }
        
        switch(e.key) {
            case 'f':
            case 'F':
                if (e.ctrlKey || e.metaKey) {
                    e.preventDefault();
                    const searchInput = document.querySelector('input[name="busqueda"]');
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                }
                break;
                
            case 'Escape':
                // Limpiar búsqueda
                const searchInput = document.querySelector('input[name="busqueda"]');
                if (searchInput && searchInput.value) {
                    searchInput.value = '';
                    searchInput.form.submit();
                }
                break;
        }
    });
}

// ===== ESTILOS CSS ADICIONALES =====
const additionalCSS = `
    .enhanced-tooltip {
        animation: tooltipFadeIn 0.2s ease;
    }
    
    @keyframes tooltipFadeIn {
        from { opacity: 0; transform: translateY(-5px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    @keyframes ripple {
        to { transform: scale(4); opacity: 0; }
    }
    
    @keyframes slideOut {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(-20px); }
    }
    
    .page-loading {
        cursor: wait;
    }
    
    .page-loading * {
        pointer-events: none;
    }
    
    .search-dropdown {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: white;
        border: 1px solid #ddd;
        border-radius: 0 0 8px 8px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        z-index: 1000;
        max-height: 300px;
        overflow-y: auto;
    }
    
    .search-result-item {
        padding: 12px 16px;
        border-bottom: 1px solid #eee;
        cursor: pointer;
        transition: background 0.2s ease;
    }
    
    .search-result-item:hover {
        background: #f8f9fa;
    }
    
    .search-result-item:last-child {
        border-bottom: none;
    }
    
    .reduced-motion * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
`;

// Inyectar CSS adicional
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalCSS;
document.head.appendChild(styleSheet);