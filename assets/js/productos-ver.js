/* ============================================
   PRODUCTOS VER - JAVASCRIPT ESPECÍFICO
   ============================================ */

class ProductoVer {
    constructor() {
        this.productoId = document.body.getAttribute('data-producto-id');
        this.maxStock = 0;
        this.inicializar();
    }

    inicializar() {
        // Configurar sidebar
        this.configurarSidebar();
        
        // Auto-cerrar alertas
        this.configurarAlertas();
        
        // Configurar controles de stock
        this.configurarControlesStock();
        
        // Configurar modal de transferencia
        this.configurarModal();
        
        // Configurar eventos globales
        this.configurarEventosGlobales();
        
        // Animar elementos
        this.animarElementos();
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

    configurarControlesStock() {
        const stockButtons = document.querySelectorAll('.stock-btn');
        
        stockButtons.forEach(button => {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                const productId = button.dataset.id;
                const accion = button.dataset.accion;
                
                if (productId && accion) {
                    this.actualizarStock(productId, accion, button);
                }
            });
        });
    }

    async actualizarStock(productId, accion, button) {
        const stockValueElement = document.getElementById(`cantidad-${productId}`);
        const currentStock = parseInt(stockValueElement.textContent.replace(/,/g, ''));
        
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

            const response = await fetch('actualizar_cantidad.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.success) {
                // Actualizar valor en la interfaz
                stockValueElement.textContent = parseInt(data.nueva_cantidad).toLocaleString();
                
                // Actualizar clases de color según el nuevo stock
                this.actualizarClasesStock(stockValueElement, data.nueva_cantidad);
                
                // Actualizar indicador de estado
                this.actualizarIndicadorEstado(data.nueva_cantidad);
                
                // Actualizar botón de transferencia
                this.actualizarBotonTransferencia(data.nueva_cantidad);
                
                // Mostrar notificación de éxito
                this.mostrarNotificacion(`Stock actualizado: ${data.nueva_cantidad} unidades`, 'exito');
                
                // Animar el cambio
                stockValueElement.style.transform = 'scale(1.2)';
                setTimeout(() => {
                    stockValueElement.style.transform = 'scale(1)';
                }, 200);
                
            } else {
                this.mostrarNotificacion(data.message || 'Error al actualizar el stock', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            this.mostrarNotificacion('Error de conexión al actualizar el stock', 'error');
        } finally {
            // Rehabilitar botón
            button.disabled = false;
            button.classList.remove('loading');
            
            // Actualizar estado del botón de restar
            if (accion === 'restar') {
                const newStock = parseInt(stockValueElement.textContent.replace(/,/g, ''));
                button.disabled = newStock <= 0;
            }
        }
    }

    actualizarClasesStock(element, cantidad) {
        element.classList.remove('stock-critical', 'stock-warning', 'stock-good');
        
        if (cantidad < 5) {
            element.classList.add('stock-critical');
        } else if (cantidad < 10) {
            element.classList.add('stock-warning');
        } else {
            element.classList.add('stock-good');
        }
    }

    actualizarIndicadorEstado(cantidad) {
        const statusIndicator = document.querySelector('.status-indicator');
        if (!statusIndicator) return;

        statusIndicator.classList.remove('critical', 'warning', 'good');
        
        const icon = statusIndicator.querySelector('i');
        const text = statusIndicator.querySelector('span');
        
        if (cantidad < 5) {
            statusIndicator.classList.add('critical');
            icon.className = 'fas fa-exclamation-triangle';
            text.textContent = 'Stock Crítico';
        } else if (cantidad < 10) {
            statusIndicator.classList.add('warning');
            icon.className = 'fas fa-exclamation-circle';
            text.textContent = 'Stock Bajo';
        } else {
            statusIndicator.classList.add('good');
            icon.className = 'fas fa-check-circle';
            text.textContent = 'Stock Saludable';
        }
    }

    actualizarBotonTransferencia(cantidad) {
        const transferButton = document.querySelector('.btn-transfer');
        if (!transferButton) return;

        if (cantidad > 0) {
            transferButton.disabled = false;
            transferButton.style.opacity = '1';
            transferButton.style.cursor = 'pointer';
            
            // Actualizar el dataset para el modal
            transferButton.dataset.cantidad = cantidad;
        } else {
            transferButton.disabled = true;
            transferButton.style.opacity = '0.6';
            transferButton.style.cursor = 'not-allowed';
        }
    }

    configurarModal() {
        this.modal = document.getElementById('modalTransferencia');
        this.form = document.getElementById('formTransferencia');
        
        if (!this.modal || !this.form) return;

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

        // Configurar formulario
        this.form.addEventListener('submit', (e) => this.enviarFormulario(e));

        // Configurar controles de cantidad
        this.configurarControlesCantidad();
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
        document.getElementById('producto_id_modal').value = datos.id;
        document.getElementById('almacen_origen_modal').value = datos.almacen;
        document.getElementById('producto_nombre_modal').textContent = datos.nombre;
        document.getElementById('stock_disponible_modal').textContent = `${datos.cantidad} unidades`;
        
        // Resetear cantidad
        const quantityInput = document.getElementById('cantidad_modal');
        quantityInput.value = 1;
        quantityInput.max = datos.cantidad;
        
        // Resetear select de almacén
        document.getElementById('almacen_destino_modal').value = '';
        
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
        const quantityInput = document.getElementById('cantidad_modal');
        if (!quantityInput) return;

        let currentValue = parseInt(quantityInput.value) || 1;
        let newValue = currentValue + increment;
        
        // Validar límites
        newValue = Math.max(1, Math.min(newValue, this.maxStock));
        
        quantityInput.value = newValue;
        this.validarCantidad();
    }

    validarCantidad() {
        const quantityInput = document.getElementById('cantidad_modal');
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

        // Mostrar estado de carga
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Solicitando...';
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
                
                // Recargar la página después de un breve delay para mostrar la nueva solicitud
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
            // Restaurar botón
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
    }

    configurarEventosGlobales() {
        // Cerrar modal con Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal && this.modal.style.display === 'block') {
                this.cerrarModal();
            }
        });

        // Atajos de teclado
        document.addEventListener('keydown', (e) => {
            // Ctrl + E para editar (solo admin)
            if (e.ctrlKey && e.key === 'e') {
                const editButton = document.querySelector('.btn-edit');
                if (editButton) {
                    e.preventDefault();
                    editButton.click();
                }
            }
            
            // Ctrl + T para transferir
            if (e.ctrlKey && e.key === 't') {
                const transferButton = document.querySelector('.btn-transfer');
                if (transferButton && !transferButton.disabled) {
                    e.preventDefault();
                    transferButton.click();
                }
            }
        });
    }

    animarElementos() {
        // Animar tarjetas con delay
        const cards = document.querySelectorAll('.details-card, .stock-card, .movements-section, .requests-section');
        cards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
        });

        // Animar filas de la tabla
        const tableRows = document.querySelectorAll('.movements-table tbody tr');
        tableRows.forEach((row, index) => {
            row.style.animationDelay = `${index * 0.05}s`;
            row.style.animation = 'slideInUp 0.4s ease both';
        });

        // Animar tarjetas de solicitudes
        const requestCards = document.querySelectorAll('.request-card');
        requestCards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
            card.style.animation = 'slideInUp 0.4s ease both';
        });
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
}

// Funciones globales para compatibilidad
function abrirModalTransferencia(button) {
    const datos = {
        id: button.dataset.id,
        nombre: button.dataset.nombre,
        almacen: button.dataset.almacen,
        cantidad: button.dataset.cantidad
    };
    
    window.productoVer.abrirModal(datos);
}

function cerrarModal() {
    window.productoVer.cerrarModal();
}

function adjustQuantity(increment) {
    window.productoVer.adjustQuantity(increment);
}

function editarProducto(id) {
    window.location.href = `editar.php?id=${id}`;
}

async function eliminarProducto(id, nombre) {
    const confirmado = await window.productoVer.confirmarAccion(
        `¿Estás seguro que deseas eliminar el producto "${nombre}"? Esta acción no se puede deshacer.`,
        'Eliminar Producto',
        'danger'
    );
    
    if (confirmado) {
        window.productoVer.mostrarNotificacion('Eliminando producto...', 'info');
        
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
                window.productoVer.mostrarNotificacion('Producto eliminado correctamente', 'exito');
                
                // Redirigir a la lista después de un breve delay
                setTimeout(() => {
                    window.location.href = 'listar.php';
                }, 2000);
            } else {
                window.productoVer.mostrarNotificacion(data.message || 'Error al eliminar el producto', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            window.productoVer.mostrarNotificacion('Error de conexión al eliminar el producto', 'error');
        }
    }
}

function manejarCerrarSesion(event) {
    window.productoVer.manejarCerrarSesion(event);
}

// Agregar estilos de animación adicionales
const additionalStyles = `
    @keyframes slideOutUp {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-20px); }
    }
    
    @keyframes slideOutRight {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(100%); }
    }
    
    .loading {
        opacity: 0.6;
        pointer-events: none;
    }
    
    .loading::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 16px;
        height: 16px;
        margin: -8px 0 0 -8px;
        border: 2px solid transparent;
        border-top: 2px solid var(--accent-color);
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
`;

// Inyectar estilos adicionales
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.productoVer = new ProductoVer();
    console.log('Producto Ver inicializado correctamente');
});