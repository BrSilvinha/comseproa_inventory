/**
 * Dashboard Charts - Gráficos interactivos para el dashboard
 * Utiliza Chart.js para visualizaciones modernas
 */

class DashboardCharts {
    constructor() {
        this.charts = {};
        this.isDarkMode = document.body.classList.contains('dark-mode');
        this.colors = this.getColorPalette();
        
        this.init();
    }

    init() {
        // Cargar Chart.js si no está disponible
        if (typeof Chart === 'undefined') {
            this.loadChartJS(() => {
                this.loadDashboardData();
            });
        } else {
            this.loadDashboardData();
        }

        // Actualizar cada 5 minutos
        setInterval(() => {
            this.loadDashboardData();
        }, 300000);

        // Escuchar cambios de tema
        document.addEventListener('themeChanged', () => {
            this.isDarkMode = document.body.classList.contains('dark-mode');
            this.colors = this.getColorPalette();
            this.updateChartsTheme();
        });
    }

    loadChartJS(callback) {
        const script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js';
        script.onload = callback;
        document.head.appendChild(script);
    }

    getColorPalette() {
        if (this.isDarkMode) {
            return {
                primary: '#60a5fa',
                secondary: '#a78bfa',
                success: '#34d399',
                warning: '#fbbf24',
                danger: '#f87171',
                info: '#38bdf8',
                background: '#374151',
                text: '#f3f4f6',
                gridLines: '#4b5563'
            };
        } else {
            return {
                primary: '#3b82f6',
                secondary: '#8b5cf6',
                success: '#10b981',
                warning: '#f59e0b',
                danger: '#ef4444',
                info: '#06b6d4',
                background: '#ffffff',
                text: '#374151',
                gridLines: '#e5e7eb'
            };
        }
    }

    async loadDashboardData() {
        try {
            // Datos estáticos temporales
            const staticData = {
                success: true,
                data: {
                    total_productos: 156,
                    total_almacenes: 3,
                    total_usuarios: 8,
                    stock_bajo: 12,
                    valor_inventario: 25000,
                    productos_por_categoria: {
                        labels: ['Uniformes', 'Equipos', 'Materiales', 'Otros'],
                        data: [45, 30, 15, 10]
                    },
                    movimientos_semana: {
                        labels: ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'],
                        data: [12, 8, 15, 20, 18, 6, 4]
                    },
                    productos_top: {
                        labels: ['Casco', 'Chaleco', 'Botas', 'Guantes', 'Lentes'],
                        data: [25, 20, 18, 15, 12]
                    },
                    stock_critico: [
                        { producto: 'Casco Seguridad', stock: 5, minimo: 10 },
                        { producto: 'Chaleco Reflectivo', stock: 3, minimo: 15 },
                        { producto: 'Botas Seguridad', stock: 8, minimo: 20 }
                    ]
                }
            };
            
            this.updateStatsCards(staticData.data);
            this.createCharts(staticData.data);
            return;
            
            const response = await fetch('api/dashboard_stats_simple.php', {
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
                this.updateStatsCards(result.data);
                this.createCharts(result.data);
            } else {
                this.showError('Error al cargar estadísticas: ' + result.error);
            }
        } catch (error) {
            console.error('Error loading dashboard data:', error);
            this.showError('Error de conexión al cargar estadísticas');
        }
    }

    updateStatsCards(data) {
        // Actualizar tarjetas de estadísticas
        this.updateStatCard('total-productos', data.total_productos, 'Productos');
        this.updateStatCard('total-almacenes', data.total_almacenes, 'Almacenes');
        this.updateStatCard('total-usuarios', data.total_usuarios, 'Usuarios');
        this.updateStatCard('stock-bajo', data.stock_bajo, 'Stock Bajo', data.stock_bajo > 0 ? 'warning' : 'success');
        this.updateStatCard('valor-inventario', 'S/ ' + this.formatNumber(data.valor_inventario), 'Valor Total');
    }

    updateStatCard(id, value, label, type = 'primary') {
        const card = document.getElementById(id);
        if (card) {
            card.innerHTML = `
                <div class="stat-card ${type}">
                    <div class="stat-value">${value}</div>
                    <div class="stat-label">${label}</div>
                </div>
            `;
        }
    }

    createCharts(data) {
        this.createCategoryChart(data.productos_por_categoria);
        this.createMovementsChart(data.movimientos_semana);
        this.createTopProductsChart(data.productos_top);
        this.createStockAlertTable(data.stock_critico);
    }

    createCategoryChart(data) {
        const ctx = document.getElementById('categoryChart');
        if (!ctx) return;

        if (this.charts.category) {
            this.charts.category.destroy();
        }

        this.charts.category = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.data,
                    backgroundColor: [
                        this.colors.primary,
                        this.colors.secondary,
                        this.colors.success,
                        this.colors.warning,
                        this.colors.danger,
                        this.colors.info
                    ],
                    borderWidth: 2,
                    borderColor: this.colors.background
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            color: this.colors.text,
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    title: {
                        display: true,
                        text: 'Productos por Categoría',
                        color: this.colors.text,
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    }
                }
            }
        });
    }

    createMovementsChart(data) {
        const ctx = document.getElementById('movementsChart');
        if (!ctx) return;

        if (this.charts.movements) {
            this.charts.movements.destroy();
        }

        this.charts.movements = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Movimientos',
                    data: data.data,
                    borderColor: this.colors.primary,
                    backgroundColor: this.colors.primary + '20',
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: this.colors.primary,
                    pointBorderColor: this.colors.background,
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Movimientos - Últimos 7 días',
                        color: this.colors.text,
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: this.colors.gridLines
                        },
                        ticks: {
                            color: this.colors.text
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: this.colors.gridLines
                        },
                        ticks: {
                            color: this.colors.text
                        }
                    }
                }
            }
        });
    }

    createTopProductsChart(data) {
        const ctx = document.getElementById('topProductsChart');
        if (!ctx) return;

        if (this.charts.topProducts) {
            this.charts.topProducts.destroy();
        }

        this.charts.topProducts = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Movimientos',
                    data: data.data,
                    backgroundColor: this.colors.secondary,
                    borderColor: this.colors.secondary,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    title: {
                        display: true,
                        text: 'Top 5 Productos - Últimos 30 días',
                        color: this.colors.text,
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            color: this.colors.text,
                            maxRotation: 45
                        }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: this.colors.gridLines
                        },
                        ticks: {
                            color: this.colors.text
                        }
                    }
                }
            }
        });
    }

    createStockAlertTable(data) {
        const container = document.getElementById('stockAlerts');
        if (!container) return;

        if (data.length === 0) {
            container.innerHTML = '<p class="no-alerts">✅ No hay productos con stock bajo</p>';
            return;
        }

        let html = `
            <div class="stock-alerts">
                <h3>⚠️ Alertas de Stock</h3>
                <div class="alert-list">
        `;

        data.forEach(product => {
            const percentage = Math.round((product.cantidad / product.stock_minimo) * 100);
            const alertLevel = percentage <= 50 ? 'critical' : 'warning';
            
            html += `
                <div class="alert-item ${alertLevel}">
                    <div class="product-info">
                        <span class="product-name">${this.escapeHtml(product.nombre)}</span>
                        <span class="stock-info">Stock: ${product.cantidad} / Min: ${product.stock_minimo}</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: ${Math.min(percentage, 100)}%"></div>
                    </div>
                </div>
            `;
        });

        html += `
                </div>
            </div>
        `;

        container.innerHTML = html;
    }

    updateChartsTheme() {
        // Recrear gráficos con nuevo tema
        if (Object.keys(this.charts).length > 0) {
            this.loadDashboardData();
        }
    }

    formatNumber(number) {
        return new Intl.NumberFormat('es-PE', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(number);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showError(message) {
        // Mostrar notificación de error
        if (window.Toast) {
            Toast.error(message);
        } else {
            console.error(message);
        }
    }
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    new DashboardCharts();
});