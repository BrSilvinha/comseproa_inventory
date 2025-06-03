/* ============================================
   PRODUCTOS VER - JAVASCRIPT COMPLETO
   ============================================ */

class ProductosVer {
    constructor() {
        this.maxStock = 0;
        this.inicializar();
        this.configurarEventListeners();
    }

    inicializar() {
        // Configurar sidebar - ESTO ES LO QUE FALTABA
        this.configurarSidebar();
        
        // Configurar controles de stock
        this.configurarControlesStock();
        
        // Configurar modal
        this.configurarModal();
        
        // Auto-cerrar alertas
        this.configurarAlertas();
    }

    configurarSidebar() {
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('main-content');
        const submenuContainers = document.querySelectorAll('.submenu-container');

        // Toggle del menú móvil
        if (menuToggle) {
            menuToggle.addEventListener('click', () => {
                sidebar.classList.toggle('active');
                if (mainContent) {
                    mainContent.classList.toggle('with-sidebar');
                }
                
                const icon = menuToggle.querySelector('i');
                if (sidebar.classList.contains('active')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                    menuToggle.setAttribute('aria-label', 'Cerrar menú de navegación');
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                    menuToggle.setAttribute('aria-label', 'Abrir menú de navegación');
                }
            });
        }

        // Funcionalidad de submenús
        submenuContainers.forEach(container => {
            const link = container.querySelector('a');
            const submenu = container.querySelector('.submenu');
            const chevron = link?.querySelector('.fa-chevron-down');
            
            if (link && submenu) {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    
                    // Cerrar otros submenús
                    submenuContainers.forEach(otherContainer => {
                        if (otherContainer !== container) {
                            const otherSubmenu = otherContainer.querySelector('.submenu');
                            const otherChevron = otherContainer.querySelector('.fa-chevron-down');
                            const otherLink = otherContainer.querySelector('a');
                            
                            if (otherSubmenu && otherSubmenu.classList.contains('activo')) {
                                otherSubmenu.classList.remove('activo');
                                if (otherChevron) {
                                    otherChevron.style.transform = 'rotate(0deg)';
                                }
                                if (otherLink) {
                                    otherLink.setAttribute('aria-expanded', 'false');
                                }
                            }
                        }
                    });
                    
                    // Toggle submenu actual
                    submenu.classList.toggle('activo');
                    const isExpanded = submenu.classList.contains('activo');
                    
                    if (chevron) {
                        chevron.style.transform = isExpanded ? 'rotate(180deg)' : 'rotate(0deg)';
                    }
                    
                    link.setAttribute('aria-expanded', isExpanded.toString());
                });
            }
        });

        // Cerrar menú móvil al hacer clic fuera
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                    sidebar.classList.remove('active');
                    if (mainContent) {
                        mainContent.classList.remove('with-sidebar');
                    }
                    
                    const icon = menuToggle.querySelector('i');
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                    menuToggle.setAttribute('aria-label', 'Abrir menú de navegación');
                }
            }
        });

        // Navegación por teclado
        document.addEventListener('keydown', (e) => {
            // Cerrar menú móvil con Escape
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                if (mainContent) {
                    mainContent.classList.remove('with-sidebar');
                }
                menuToggle.focus();
            }
            
            // Indicador visual para navegación por teclado
            if (e.key === 'Tab') {
                document.body.classList.add('keyboard-navigation');
            }
        });
        
        document.addEventListener('mousedown', () => {
            document.body.classList.remove('keyboard-navigation');
        });
    }

    configurarControlesStock() {
        const stockButtons = document.querySelectorAll('.stock-btn');
        console.log('🔧 Configurando controles de stock - Botones encontrados:', stockButtons.length);
        
        stockButtons.forEach(button => {
            button.addEventListener('click', async (e) => {
                e.preventDefault();
                const productId = button.dataset.id;
                const accion = button.dataset.accion;
                
                console.log('🖱️ Click en botón stock:', productId, accion);
                
                if (productId && accion) {
                    await this.actualizarStock(productId, accion, button);
                }
            });
        });
    }

    async actualizarStock(productId, accion, button) {
        console.log('🔄 Actualizando stock:', productId, accion);
        
        // Buscar el elemento de cantidad actual
        const stockElement = document.getElementById('cantidad-actual');
        if (!stockElement) {
            console.error('No se encontró el elemento cantidad-actual');
            this.mostrarNotificacion('Error: No se pudo encontrar el elemento de cantidad', 'error');
            return;
        }
        
        const currentStock = parseInt(stockElement.textContent.replace(/,/g, ''));
        console.log('Stock actual:', currentStock);
        
        // Validar acción
        if (accion === 'restar' && currentStock <= 0) {
            this.mostrarNotificacion('No se puede reducir más el stock', 'error');
            return;
        }

        // Deshabilitar botón temporalmente
        button.disabled = true;
        button.classList.add('loading');

        try {
            const formData = new FormData();
            formData.append('producto_id', productId);
            formData.append('accion', accion);

            console.log('📤 Enviando petición a actualizar_cantidad.php');
            
            const response = await fetch('actualizar_cantidad.php', {
                method: 'POST',
                body: formData
            });

            console.log('📥 Respuesta recibida:', response.status);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log('Datos recibidos:', data);

            if (data.success) {
                // Actualizar valor en la interfaz
                stockElement.textContent = parseInt(data.nueva_cantidad).toLocaleString();
                
                // Actualizar clases de color según el nuevo stock
                this.actualizarClasesStock(stockElement, data.nueva_cantidad);
                
                // Actualizar botón de transferencia
                this.actualizarBotonTransferencia(data.nueva_cantidad);
                
                // Mostrar notificación de éxito
                this.mostrarNotificacion(`Stock actualizado: ${data.nueva_cantidad} unidades`, 'exito');
                
                // Animar el cambio
                stockElement.style.transform = 'scale(1.2)';
                stockElement.style.transition = 'transform 0.2s ease';
                setTimeout(() => {
                    stockElement.style.transform = 'scale(1)';
                }, 200);
                
            } else {
                this.mostrarNotificacion(data.message || 'Error al actualizar el stock', 'error');
            }
        } catch (error) {
            console.error('❌ Error:', error);
            this.mostrarNotificacion('Error de conexión al actualizar el stock', 'error');
        } finally {
            // Rehabilitar botón
            button.disabled = false;
            button.classList.remove('loading');
            
            // Actualizar estado del botón de restar
            if (accion === 'restar') {
                const newStock = parseInt(stockElement.textContent.replace(/,/g, ''));
                const decreaseBtn = document.querySelector('.stock-btn[data-accion="restar"]');
                if (decreaseBtn) {
                    decreaseBtn.disabled = newStock <= 0;
                }
            }
        }
    }

    actualizarClasesStock(element, cantidad) {
        const stockValue = element.closest('.stock-value');
        if (stockValue) {
            stockValue.classList.remove('stock-critical', 'stock-warning', 'stock-good');
            
            if (cantidad < 5) {
                stockValue.classList.add('stock-critical');
            } else if (cantidad < 10) {
                stockValue.classList.add('stock-warning');
            } else {
                stockValue.classList.add('stock-good');
            }
            
            // Agregar efecto visual de actualización
            stockValue.classList.add('updating');
            setTimeout(() => {
                stockValue.classList.remove('updating');
            }, 500);
        }
    }

    actualizarBotonTransferencia(cantidad) {
        const transferButton = document.querySelector('.btn-transfer');
        if (!transferButton) return;

        if (cantidad > 0) {
            transferButton.disabled = false;
            transferButton.style.opacity = '1';
            transferButton.style.cursor = 'pointer';
            transferButton.dataset.cantidad = cantidad;
        } else {
            transferButton.disabled = true;
            transferButton.style.opacity = '0.6';
            transferButton.style.cursor = 'not-allowed';
        }
    }

    configurarModal() {
        const modal = document.getElementById('modalTransferencia');
        const form = document.getElementById('formTransferencia');
        
        if (!modal || !form) return;

        // Configurar botones de cerrar
        const closeButtons = modal.querySelectorAll('.modal-close, .btn-cancel');
        closeButtons.forEach(button => {
            button.addEventListener('click', () => this.cerrarModal());
        });

        // Cerrar modal al hacer clic fuera
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                this.cerrarModal();
            }
        });

        // Configurar formulario
        form.addEventListener('submit', (e) => this.enviarFormulario(e));

        // Configurar controles de cantidad
        this.configurarControlesCantidad();
    }

    configurarControlesCantidad() {
        const minusBtn = document.querySelector('#modalTransferencia .qty-btn.minus');
        const plusBtn = document.querySelector('#modalTransferencia .qty-btn.plus');
        const quantityInput = document.querySelector('#modalTransferencia .qty-input');

        if (minusBtn && plusBtn && quantityInput) {
            minusBtn.addEventListener('click', () => this.adjustQuantity(-1));
            plusBtn.addEventListener('click', () => this.adjustQuantity(1));
            
            quantityInput.addEventListener('change', () => this.validarCantidad());
            quantityInput.addEventListener('input', () => this.validarCantidad());
        }
    }

    configurarAlertas() {
        const alertas = document.querySelectorAll('.alert');
        alertas.forEach(alerta => {
            setTimeout(() => {
                alerta.style.animation = 'slideOutUp 0.5s ease-in-out';
                setTimeout(() => {
                    alerta.remove();
                }, 500);
            }, 5000);
        });
    }

    configurarEventListeners() {
        // Configurar eventos globales
        document.addEventListener('keydown', (e) => {
            // Cerrar modal con Escape
            if (e.key === 'Escape') {
                const modal = document.getElementById('modalTransferencia');
                if (modal && modal.style.display === 'block') {
                    this.cerrarModal();
                }
            }
        });

        // Manejar cerrar sesión
        const logoutLinks = document.querySelectorAll('a[onclick*="manejarCerrarSesion"]');
        logoutLinks.forEach(link => {
            link.addEventListener('click', (e) => this.manejarCerrarSesion(e));
        });
    }

    abrirModal(datos) {
        const modal = document.getElementById('modalTransferencia');
        if (!modal) return;

        console.log('Abriendo modal con datos:', datos);

        document.getElementById('producto_id_modal').value = datos.id;
        document.getElementById('almacen_origen_modal').value = datos.almacen;
        document.getElementById('producto_nombre_modal').textContent = datos.nombre;
        document.getElementById('stock_disponible_modal').textContent = `${datos.cantidad} unidades`;
        
        const quantityInput = document.getElementById('cantidad_modal');
        quantityInput.value = 1;
        quantityInput.max = datos.cantidad;
        
        document.getElementById('almacen_destino_modal').value = '';
        
        this.maxStock = parseInt(datos.cantidad);
        
        modal.style.display = 'flex';
        modal.setAttribute('aria-hidden', 'false');
        
        setTimeout(() => {
            quantityInput.focus();
        }, 100);
        
        document.body.style.overflow = 'hidden';
    }

    cerrarModal() {
        const modal = document.getElementById('modalTransferencia');
        if (!modal) return;

        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        
        document.body.style.overflow = '';
        
        const form = document.getElementById('formTransferencia');
        if (form) {
            form.reset();
        }
    }

    adjustQuantity(increment) {
        const quantityInput = document.getElementById('cantidad_modal');
        if (!quantityInput) return;

        let currentValue = parseInt(quantityInput.value) || 1;
        let newValue = currentValue + increment;
        
        newValue = Math.max(1, Math.min(newValue, this.maxStock));
        
        quantityInput.value = newValue;
        this.validarCantidad();
    }

    validarCantidad() {
        const quantityInput = document.getElementById('cantidad_modal');
        if (!quantityInput) return;

        const value = parseInt(quantityInput.value);
        const submitButton = document.querySelector('#formTransferencia .btn-confirm');
        
        if (value < 1 || value > this.maxStock || isNaN(value)) {
            quantityInput.style.borderColor = '#dc3545';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.style.opacity = '0.6';
            }
            
            if (value > this.maxStock) {
                this.mostrarNotificacion(`La cantidad no puede ser mayor a ${this.maxStock}`, 'error');
            }
        } else {
            quantityInput.style.borderColor = '#28a745';
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.style.opacity = '1';
            }
        }
    }

    async enviarFormulario(e) {
        e.preventDefault();
        
        const submitButton = e.target.querySelector('.btn-confirm');
        const originalText = submitButton.innerHTML;
        
        const cantidad = parseInt(document.getElementById('cantidad_modal').value);
        const almacenDestino = document.getElementById('almacen_destino_modal').value;
        
        if (!almacenDestino) {
            this.mostrarNotificacion('Debe seleccionar un almacén de destino', 'error');
            return;
        }
        
        if (cantidad < 1 || cantidad > this.maxStock) {
            this.mostrarNotificacion('La cantidad no es válida', 'error');
            return;
        }

        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Transfiriendo...';
        submitButton.disabled = true;

        try {
            const formData = new FormData(e.target);
            
            const response = await fetch('procesar_formulario.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                this.mostrarNotificacion(data.message, 'exito');
                this.cerrarModal();
                
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                this.mostrarNotificacion(data.message || 'Error al solicitar transferencia', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.mostrarNotificacion('Error de conexión al solicitar transferencia', 'error');
        } finally {
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
    }

    mostrarNotificacion(mensaje, tipo = 'info', duracion = 5000) {
        let container = document.getElementById('notificaciones-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'notificaciones-container';
            container.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10001;
                max-width: 400px;
            `;
            document.body.appendChild(container);
        }

        const iconos = {
            exito: 'fa-check-circle',
            error: 'fa-exclamation-triangle', 
            warning: 'fa-exclamation-circle',
            info: 'fa-info-circle'
        };

        const colores = {
            exito: '#28a745',
            error: '#dc3545',
            warning: '#ffc107', 
            info: '#0a253c'
        };

        const notificacion = document.createElement('div');
        notificacion.className = `notificacion ${tipo}`;
        notificacion.style.cssText = `
            background: white;
            border-left: 5px solid ${colores[tipo] || colores.info};
            padding: 15px 20px;
            margin-bottom: 10px;
            border-radius: 0 8px 8px 0;
            box-shadow: 0 4px 12px rgba(10, 37, 60, 0.15);
            position: relative;
            animation: slideInRight 0.4s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        `;

        notificacion.innerHTML = `
            <i class="fas ${iconos[tipo] || iconos.info}" style="font-size: 20px; color: ${colores[tipo] || colores.info};"></i>
            <span style="flex: 1; color: #0a253c; font-weight: 500;">${mensaje}</span>
            <button class="cerrar" aria-label="Cerrar notificación" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #666; padding: 0;">&times;</button>
        `;

        container.appendChild(notificacion);

        const cerrarBtn = notificacion.querySelector('.cerrar');
        cerrarBtn.addEventListener('click', () => {
            notificacion.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notificacion.remove(), 300);
        });

        if (duracion > 0) {
            setTimeout(() => {
                if (notificacion.parentNode) {
                    notificacion.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => notificacion.remove(), 300);
                }
            }, duracion);
        }
    }

    async manejarCerrarSesion(event) {
        event.preventDefault();
        
        const confirmado = await this.confirmarAccion(
            '¿Estás seguro que deseas cerrar sesión?',
            'Cerrar Sesión',
            'warning'
        );
        
        if (confirmado) {
            this.mostrarNotificacion('Cerrando sesión...', 'info', 2000);
            setTimeout(() => {
                window.location.href = '../logout.php';
            }, 1000);
        }
    }

    async confirmarAccion(mensaje, titulo = 'Confirmar', tipo = 'info') {
        return new Promise((resolve) => {
            // Usar el sistema de confirmaciones universal si está disponible
            if (typeof confirmarAccion === 'function') {
                resolve(confirmarAccion(mensaje, titulo, tipo));
            } else {
                // Fallback a confirm nativo
                resolve(confirm(mensaje));
            }
        });
    }
}

// Funciones globales para compatibilidad
function abrirModalTransferencia(button) {
    const datos = {
        id: button.dataset.id,
        nombre: button.dataset.nombre,
        almacen: button.dataset.almacen,
        cantidad: button.dataset.cantidad
    };
    
    window.productosVer.abrirModal(datos);
}

function cerrarModal() {
    window.productosVer.cerrarModal();
}

function adjustQuantity(increment) {
    window.productosVer.adjustQuantity(increment);
}

function editarProducto(id) {
    window.location.href = `editar.php?id=${id}`;
}

async function eliminarProducto(id, nombre) {
    const confirmado = await window.productosVer.confirmarAccion(
        `¿Estás seguro que deseas eliminar el producto "${nombre}"? Esta acción no se puede deshacer.`,
        'Eliminar Producto',
        'danger'
    );
    
    if (confirmado) {
        window.productosVer.mostrarNotificacion('Eliminando producto...', 'info');
        
        try {
            const response = await fetch('eliminar_producto.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: id })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                window.productosVer.mostrarNotificacion('Producto eliminado correctamente', 'exito');
                
                setTimeout(() => {
                    window.location.href = 'listar.php';
                }, 2000);
            } else {
                window.productosVer.mostrarNotificacion(data.message || 'Error al eliminar el producto', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            window.productosVer.mostrarNotificacion('Error de conexión al eliminar el producto', 'error');
        }
    }
}

function manejarCerrarSesion(event) {
    window.productosVer.manejarCerrarSesion(event);
}

// Agregar estilos CSS adicionales
const additionalStyles = `
    @keyframes slideInRight {
        from { opacity: 0; transform: translateX(30px); }
        to { opacity: 1; transform: translateX(0); }
    }
    @keyframes slideOutRight {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(30px); }
    }
    @keyframes slideOutUp {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-20px); }
    }
    
    /* Animación de actualización de stock */
    .stock-value.updating {
        animation: pulse 0.5s ease-in-out;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.1); background: var(--accent-color); color: white; }
        100% { transform: scale(1); }
    }
`;

// Inyectar estilos adicionales si no existen
if (!document.getElementById('productos-ver-animations')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'productos-ver-animations';
    styleSheet.textContent = additionalStyles;
    document.head.appendChild(styleSheet);
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Inicializando ProductosVer...');
    window.productosVer = new ProductosVer();
    console.log('✅ ProductosVer inicializado correctamente');
});