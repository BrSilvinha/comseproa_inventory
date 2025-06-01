/* ============================================
   PRODUCTOS LISTAR - JAVASCRIPT ESPECÍFICO
   ============================================ */

class ProductosListar {
    constructor() {
        this.stockButtonsProcessed = false; // Bandera para evitar doble registro
        this.inicializar();
        this.configurarEventListeners();
        this.configurarModal();
        this.maxStock = 0;
    }

    inicializar() {
        // Configurar sidebar
        this.configurarSidebar();
        
        // Auto-cerrar alertas
        this.configurarAlertas();
        
        // Animar tarjetas de productos
        this.animarTarjetas();
        
        // Configurar controles de stock (SOLO UNA VEZ)
        if (!this.stockButtonsProcessed) {
            this.configurarControlesStock();
            this.stockButtonsProcessed = true;
        }
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

        // Mostrar submenú de productos activo por defecto
        const productosSubmenu = submenuContainers[2]?.querySelector('.submenu');
        const productosChevron = submenuContainers[2]?.querySelector('.fa-chevron-down');
        const productosLink = submenuContainers[2]?.querySelector('a');
        
        if (productosSubmenu) {
            productosSubmenu.classList.add('activo');
            if (productosChevron) {
                productosChevron.style.transform = 'rotate(180deg)';
            }
            if (productosLink) {
                productosLink.setAttribute('aria-expanded', 'true');
            }
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

    animarTarjetas() {
        const tarjetas = document.querySelectorAll('.product-card');
        tarjetas.forEach((tarjeta, index) => {
            tarjeta.style.animationDelay = `${index * 0.1}s`;
            
            tarjeta.addEventListener('mouseenter', () => {
                tarjeta.style.transform = 'translateY(-8px)';
            });
            
            tarjeta.addEventListener('mouseleave', () => {
                tarjeta.style.transform = 'translateY(0)';
            });
        });
    }

    configurarControlesStock() {
        console.log('🔧 Configurando controles de stock...');
        
        // Remover listeners existentes para evitar duplicados
        const existingButtons = document.querySelectorAll('.stock-btn');
        existingButtons.forEach(btn => {
            btn.replaceWith(btn.cloneNode(true));
        });
        
        // Obtener botones frescos sin listeners
        const stockButtons = document.querySelectorAll('.stock-btn');
        console.log(`📊 Encontrados ${stockButtons.length} botones de stock`);
        
        stockButtons.forEach((button, index) => {
            // Agregar event listener solo una vez
            button.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation(); // Evitar propagación
                
                console.log(`🖱️ Click en botón ${index + 1}`);
                
                const productId = button.dataset.id;
                const accion = button.dataset.accion;
                
                if (productId && accion) {
                    await this.actualizarStock(productId, accion, button);
                }
            }, { once: false }); // No usar once:true para permitir múltiples usos
        });
    }

    async actualizarStock(productId, accion, button) {
        console.log(`🔄 Actualizando stock: Producto ${productId}, Acción: ${accion}`);
        
        // Evitar múltiples clicks mientras se procesa
        if (button.disabled || button.classList.contains('loading')) {
            console.log('⚠️ Botón ya está siendo procesado, ignorando click');
            return;
        }
        
        const stockValueElement = document.getElementById(`cantidad-${productId}`);
        const currentStock = parseInt(stockValueElement.textContent.replace(/,/g, ''));
        
        // Validar acción
        if (accion === 'restar' && currentStock <= 0) {
            this.mostrarNotificacion('No se puede reducir más el stock', 'error');
            return;
        }

        // Deshabilitar botón temporalmente y mostrar loading
        button.disabled = true;
        button.classList.add('loading');
        const originalContent = button.innerHTML;
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        try {
            const formData = new FormData();
            formData.append('producto_id', productId);
            formData.append('accion', accion);

            console.log('📤 Enviando petición a actualizar_cantidad.php');
            const response = await fetch('actualizar_cantidad.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            console.log('📥 Respuesta recibida:', data);

            if (data.success) {
                // Actualizar valor en la interfaz con animación
                stockValueElement.textContent = parseInt(data.nueva_cantidad).toLocaleString();
                
                // Actualizar clases de color según el nuevo stock
                this.actualizarClasesStock(stockValueElement, data.nueva_cantidad);
                
                // Actualizar botones de transferencia si es necesario
                this.actualizarBotonesTransferencia(productId, data.nueva_cantidad);
                
                // Mostrar notificación de éxito
                this.mostrarNotificacion(`Stock actualizado: ${data.nueva_cantidad} unidades`, 'exito');
                
                // Animar el cambio
                stockValueElement.style.transform = 'scale(1.2)';
                stockValueElement.style.transition = 'transform 0.2s ease';
                setTimeout(() => {
                    stockValueElement.style.transform = 'scale(1)';
                }, 200);
                
                // Actualizar estado del botón de restar si la cantidad llega a 0
                if (data.nueva_cantidad <= 0) {
                    const restarBtn = document.querySelector(`[data-id="${productId}"][data-accion="restar"]`);
                    if (restarBtn) {
                        restarBtn.disabled = true;
                    }
                } else {
                    const restarBtn = document.querySelector(`[data-id="${productId}"][data-accion="restar"]`);
                    if (restarBtn) {
                        restarBtn.disabled = false;
                    }
                }
                
            } else {
                this.mostrarNotificacion(data.message || 'Error al actualizar el stock', 'error');
            }
        } catch (error) {
            console.error('❌ Error:', error);
            this.mostrarNotificacion('Error de conexión al actualizar el stock', 'error');
        } finally {
            // Rehabilitar botón y restaurar contenido
            button.disabled = false;
            button.classList.remove('loading');
            button.innerHTML = originalContent;
            
            // Verificar estado final del botón de restar
            if (accion === 'restar') {
                const newStock = parseInt(stockValueElement.textContent.replace(/,/g, ''));
                if (newStock <= 0) {
                    button.disabled = true;
                }
            }
        }
    }

    actualizarClasesStock(element, cantidad) {
        // Remover clases anteriores
        element.classList.remove('stock-critical', 'stock-warning', 'stock-good');
        
        // Agregar clase apropiada según la cantidad
        if (cantidad < 5) {
            element.classList.add('stock-critical');
        } else if (cantidad < 10) {
            element.classList.add('stock-warning');
        } else {
            element.classList.add('stock-good');
        }
        
        // Agregar efecto visual de actualización
        element.classList.add('updating');
        setTimeout(() => {
            element.classList.remove('updating');
        }, 500);
    }

    actualizarBotonesTransferencia(productId, nuevaCantidad) {
        const tarjeta = document.querySelector(`[data-producto-id="${productId}"]`);
        if (tarjeta) {
            const transferButton = tarjeta.querySelector('.btn-transfer');
            if (transferButton) {
                if (nuevaCantidad > 0) {
                    transferButton.disabled = false;
                    transferButton.classList.remove('disabled');
                    transferButton.innerHTML = '<i class="fas fa-paper-plane"></i> Transferir';
                    transferButton.dataset.cantidad = nuevaCantidad;
                    transferButton.style.opacity = '1';
                    transferButton.style.cursor = 'pointer';
                } else {
                    transferButton.disabled = true;
                    transferButton.classList.add('disabled');
                    transferButton.innerHTML = '<i class="fas fa-times"></i> Sin Stock';
                    transferButton.style.opacity = '0.6';
                    transferButton.style.cursor = 'not-allowed';
                }
            }
        }
    }

    configurarModal() {
        this.modal = document.getElementById('modalTransferencia');
        this.form = document.getElementById('formTransferencia');
        
        if (this.modal && this.form) {
            // Configurar botones de cerrar
            const closeButtons = this.modal.querySelectorAll('.modal-close, .btn-cancel');
            closeButtons.forEach(button => {
                button.addEventListener('click', () => this.cerrarModal());
            });

            // Cerrar modal al hacer clic fuera
            this.modal.addEventListener('click', (e) => {
                if (e.target === this.modal) {
                    this.cerrarModal();
                }
            });

            // Configurar formulario de transferencia
            this.form.addEventListener('submit', (e) => this.enviarFormulario(e));

            // Configurar controles de cantidad
            this.configurarControlesCantidad();
        }
    }

    configurarEventListeners() {
        // Configurar eventos globales
        document.addEventListener('keydown', (e) => {
            // Cerrar modal con Escape
            if (e.key === 'Escape' && this.modal && this.modal.style.display === 'block') {
                this.cerrarModal();
            }
        });

        // Manejar cerrar sesión
        const logoutLinks = document.querySelectorAll('a[onclick*="manejarCerrarSesion"]');
        logoutLinks.forEach(link => {
            link.addEventListener('click', (e) => this.manejarCerrarSesion(e));
        });
    }

    configurarControlesCantidad() {
        const minusBtn = this.modal?.querySelector('.qty-btn.minus');
        const plusBtn = this.modal?.querySelector('.qty-btn.plus');
        const quantityInput = this.modal?.querySelector('.qty-input');

        if (minusBtn && plusBtn && quantityInput) {
            minusBtn.addEventListener('click', () => this.adjustQuantity(-1));
            plusBtn.addEventListener('click', () => this.adjustQuantity(1));
            
            quantityInput.addEventListener('change', () => this.validarCantidad());
            quantityInput.addEventListener('input', () => this.validarCantidad());
        }
    }

    abrirModal(datos) {
        if (!this.modal) return;

        // Llenar datos del modal
        document.getElementById('producto_id').value = datos.id;
        document.getElementById('almacen_origen').value = datos.almacen;
        document.getElementById('producto_nombre').textContent = datos.nombre;
        document.getElementById('stock_disponible').textContent = `${datos.cantidad} unidades`;
        
        // Resetear cantidad
        const quantityInput = document.getElementById('cantidad');
        quantityInput.value = 1;
        quantityInput.max = datos.cantidad;
        
        // Resetear select de almacén
        document.getElementById('almacen_destino').value = '';
        
        // Guardar máximo stock
        this.maxStock = parseInt(datos.cantidad);
        
        // Mostrar modal
        this.modal.style.display = 'block';
        this.modal.setAttribute('aria-hidden', 'false');
        
        // Focus en el primer campo
        setTimeout(() => {
            quantityInput.focus();
        }, 100);
        
        // Deshabilitar scroll del body
        document.body.style.overflow = 'hidden';
    }

    cerrarModal() {
        if (!this.modal) return;

        this.modal.style.display = 'none';
        this.modal.setAttribute('aria-hidden', 'true');
        
        // Rehabilitar scroll del body
        document.body.style.overflow = '';
        
        // Resetear formulario
        this.form.reset();
    }

    adjustQuantity(increment) {
        const quantityInput = document.getElementById('cantidad');
        if (!quantityInput) return;

        let currentValue = parseInt(quantityInput.value) || 1;
        let newValue = currentValue + increment;
        
        // Validar límites
        newValue = Math.max(1, Math.min(newValue, this.maxStock));
        
        quantityInput.value = newValue;
        this.validarCantidad();
    }

    validarCantidad() {
        const quantityInput = document.getElementById('cantidad');
        if (!quantityInput) return;

        const value = parseInt(quantityInput.value);
        const submitButton = this.form?.querySelector('.btn-confirm');
        
        if (value < 1 || value > this.maxStock || isNaN(value)) {
            quantityInput.style.borderColor = 'var(--danger-color)';
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.style.opacity = '0.6';
            }
            
            if (value > this.maxStock) {
                this.mostrarNotificacion(`La cantidad no puede ser mayor a ${this.maxStock}`, 'error');
            }
        } else {
            quantityInput.style.borderColor = 'var(--success-color)';
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.style.opacity = '1';
            }
        }
    }

    async enviarFormulario(e) {
        e.preventDefault();
        
        const submitButton = this.form.querySelector('.btn-confirm');
        const originalText = submitButton.innerHTML;
        
        // Validaciones
        const cantidad = parseInt(document.getElementById('cantidad').value);
        const almacenDestino = document.getElementById('almacen_destino').value;
        
        if (!almacenDestino) {
            this.mostrarNotificacion('Debe seleccionar un almacén de destino', 'error');
            return;
        }
        
        if (cantidad < 1 || cantidad > this.maxStock) {
            this.mostrarNotificacion('La cantidad no es válida', 'error');
            return;
        }

        // Mostrar estado de carga
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Transfiriendo...';
        submitButton.disabled = true;

        try {
            const formData = new FormData(this.form);
            
            const response = await fetch('procesar_formulario.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                this.mostrarNotificacion(data.message, 'exito');
                this.cerrarModal();
                
                // Recargar la página después de un breve delay
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            } else {
                this.mostrarNotificacion(data.message || 'Error al transferir el producto', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.mostrarNotificacion('Error de conexión al transferir el producto', 'error');
        } finally {
            // Restaurar botón
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
    }

    mostrarNotificacion(mensaje, tipo = 'info', duracion = 5000) {
        const container = document.getElementById('notificaciones-container');
        if (!container) return;

        const notificacion = document.createElement('div');
        notificacion.className = `notificacion ${tipo}`;
        
        let icono = 'fas fa-info-circle';
        if (tipo === 'exito') icono = 'fas fa-check-circle';
        if (tipo === 'error') icono = 'fas fa-exclamation-circle';
        if (tipo === 'warning') icono = 'fas fa-exclamation-triangle';
        
        notificacion.innerHTML = `
            <i class="${icono}"></i>
            <span>${mensaje}</span>
            <button class="cerrar" onclick="this.parentElement.remove()">×</button>
        `;

        container.appendChild(notificacion);

        // Auto-remover después del tiempo especificado
        setTimeout(() => {
            if (notificacion.parentNode) {
                notificacion.style.animation = 'slideOutRight 0.4s ease';
                setTimeout(() => {
                    notificacion.remove();
                }, 400);
            }
        }, duracion);
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
            // Crear modal de confirmación dinámico
            const modalHtml = `
                <div class="modal" style="display: block; z-index: 3000;">
                    <div class="modal-content" style="max-width: 400px;">
                        <div class="modal-header">
                            <h2>
                                <i class="fas fa-question-circle"></i>
                                ${titulo}
                            </h2>
                        </div>
                        <div class="modal-body">
                            <p style="margin: 0; text-align: center; font-size: 16px;">${mensaje}</p>
                        </div>
                        <div class="modal-footer">
                            <button class="btn-modal btn-cancel" id="btnCancelar">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                            <button class="btn-modal btn-confirm" id="btnConfirmar">
                                <i class="fas fa-check"></i> Confirmar
                            </button>
                        </div>
                    </div>
                </div>
            `;

            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = modalHtml;
            const confirmModal = tempDiv.firstElementChild;
            
            document.body.appendChild(confirmModal);
            document.body.style.overflow = 'hidden';

            const btnConfirmar = confirmModal.querySelector('#btnConfirmar');
            const btnCancelar = confirmModal.querySelector('#btnCancelar');

            const cleanup = () => {
                document.body.removeChild(confirmModal);
                document.body.style.overflow = '';
            };

            btnConfirmar.addEventListener('click', () => {
                cleanup();
                resolve(true);
            });

            btnCancelar.addEventListener('click', () => {
                cleanup();
                resolve(false);
            });

            // Cerrar con Escape
            const handleEscape = (e) => {
                if (e.key === 'Escape') {
                    cleanup();
                    resolve(false);
                    document.removeEventListener('keydown', handleEscape);
                }
            };
            document.addEventListener('keydown', handleEscape);
        });
    }
}

// Funciones globales para compatibilidad
function abrirModalEnvio(button) {
    const datos = {
        id: button.dataset.id,
        nombre: button.dataset.nombre,
        almacen: button.dataset.almacen,
        cantidad: button.dataset.cantidad
    };
    
    window.productosListar.abrirModal(datos);
}

function cerrarModal() {
    window.productosListar.cerrarModal();
}

function adjustQuantity(increment) {
    window.productosListar.adjustQuantity(increment);
}

function verProducto(id) {
    window.location.href = `ver-producto.php?id=${id}`;
}

function editarProducto(id) {
    window.location.href = `editar.php?id=${id}`;
}

async function eliminarProducto(id, nombre) {
    const confirmado = await window.productosListar.confirmarAccion(
        `¿Estás seguro que deseas eliminar el producto "${nombre}"? Esta acción no se puede deshacer.`,
        'Eliminar Producto',
        'danger'
    );
    
    if (confirmado) {
        window.productosListar.mostrarNotificacion('Eliminando producto...', 'info');
        
        try {
            const response = await fetch('eliminar_producto.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: id })
            });

            const data = await response.json();

            if (data.success) {
                window.productosListar.mostrarNotificacion('Producto eliminado correctamente', 'exito');
                
                // Remover la tarjeta del DOM con animación
                const card = document.querySelector(`[data-producto-id="${id}"]`);
                if (card) {
                    card.style.animation = 'fadeOut 0.5s ease';
                    setTimeout(() => {
                        card.remove();
                        
                        // Verificar si quedan productos
                        const remainingCards = document.querySelectorAll('.product-card');
                        if (remainingCards.length === 0) {
                            location.reload(); // Recargar para mostrar estado vacío
                        }
                    }, 500);
                }
            } else {
                window.productosListar.mostrarNotificacion(data.message || 'Error al eliminar el producto', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            window.productosListar.mostrarNotificacion('Error de conexión al eliminar el producto', 'error');
        }
    }
}

function manejarCerrarSesion(event) {
    window.productosListar.manejarCerrarSesion(event);
}

// Agregar estilos de animación adicionales
const additionalStyles = `
    @keyframes fadeOut {
        from { opacity: 1; transform: scale(1); }
        to { opacity: 0; transform: scale(0.9); }
    }
    
    @keyframes slideOutUp {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-20px); }
    }
    
    @keyframes slideOutRight {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(100%); }
    }
    
    /* Estilos para controles de stock */
    .stock-display {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 5px;
    }
    
    .stock-btn {
        width: 28px;
        height: 28px;
        border: none;
        border-radius: 50%;
        background: var(--primary-color);
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        font-size: 12px;
        position: relative;
    }
    
    .stock-btn:hover:not(:disabled) {
        background: var(--accent-color);
        transform: scale(1.1);
        box-shadow: 0 2px 8px rgba(10, 37, 60, 0.3);
    }
    
    .stock-btn:disabled {
        background: #ccc;
        cursor: not-allowed;
        opacity: 0.6;
    }
    
    .stock-btn.loading {
        pointer-events: none;
    }
    
    .stock-btn.loading i {
        animation: spin 1s linear infinite;
    }
    
    .stock-value {
        font-weight: 600;
        font-size: 16px;
        min-width: 40px;
        text-align: center;
        padding: 4px 8px;
        border-radius: 4px;
        background: rgba(255, 255, 255, 0.8);
        transition: all 0.3s ease;
    }
    
    .stock-critical {
        color: #dc3545;
        background: rgba(220, 53, 69, 0.1);
        border: 1px solid rgba(220, 53, 69, 0.3);
    }
    
    .stock-warning {
        color: #ffc107;
        background: rgba(255, 193, 7, 0.1);
        border: 1px solid rgba(255, 193, 7, 0.3);
    }
    
    .stock-good {
        color: #28a745;
        background: rgba(40, 167, 69, 0.1);
        border: 1px solid rgba(40, 167, 69, 0.3);
    }
    
    .stock-hint {
        display: flex;
        align-items: center;
        gap: 4px;
        margin-top: 5px;
        opacity: 0.7;
        font-size: 11px;
        color: var(--text-muted);
    }
    
    .stock-hint i {
        font-size: 10px;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
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

// Inyectar estilos adicionales
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Inicializando ProductosListar...');
    window.productosListar = new ProductosListar();
    console.log('✅ ProductosListar inicializado correctamente');
});