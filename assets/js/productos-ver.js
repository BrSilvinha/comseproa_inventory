/* ============================================
   PRODUCTOS VER - JAVASCRIPT SIMPLIFICADO
   ============================================ */

// Variables globales
let maxStock = 0;

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('Inicializando productos-ver.js');
    
    // Configurar controles de stock
    configurarControlesStock();
    
    // Configurar modal
    configurarModal();
    
    // Auto-cerrar alertas
    configurarAlertas();
    
    console.log('Productos Ver inicializado correctamente');
});

// Configurar controles de stock
function configurarControlesStock() {
    const stockButtons = document.querySelectorAll('.stock-btn');
    console.log('Botones de stock encontrados:', stockButtons.length);
    
    stockButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            const productId = this.dataset.id;
            const accion = this.dataset.accion;
            
            console.log('Click en botón stock:', productId, accion);
            
            if (productId && accion) {
                actualizarStock(productId, accion, this);
            }
        });
    });
}

// Función para actualizar stock
async function actualizarStock(productId, accion, button) {
    console.log('Actualizando stock:', productId, accion);
    
    // Buscar el elemento de cantidad actual
    const stockElement = document.getElementById('cantidad-actual');
    if (!stockElement) {
        console.error('No se encontró el elemento cantidad-actual');
        mostrarNotificacion('Error: No se pudo encontrar el elemento de cantidad', 'error');
        return;
    }
    
    const currentStock = parseInt(stockElement.textContent.replace(/,/g, ''));
    console.log('Stock actual:', currentStock);
    
    // Validar acción
    if (accion === 'restar' && currentStock <= 0) {
        mostrarNotificacion('No se puede reducir más el stock', 'error');
        return;
    }

    // Deshabilitar botón temporalmente
    button.disabled = true;
    button.classList.add('loading');

    try {
        const formData = new FormData();
        formData.append('producto_id', productId);
        formData.append('accion', accion);

        console.log('Enviando petición a actualizar_cantidad.php');
        
        const response = await fetch('actualizar_cantidad.php', {
            method: 'POST',
            body: formData
        });

        console.log('Respuesta recibida:', response.status);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const data = await response.json();
        console.log('Datos recibidos:', data);

        if (data.success) {
            // Actualizar valor en la interfaz
            stockElement.textContent = parseInt(data.nueva_cantidad).toLocaleString();
            
            // Actualizar clases de color según el nuevo stock
            actualizarClasesStock(stockElement, data.nueva_cantidad);
            
            // Actualizar botón de transferencia
            actualizarBotonTransferencia(data.nueva_cantidad);
            
            // Mostrar notificación de éxito
            mostrarNotificacion(`Stock actualizado: ${data.nueva_cantidad} unidades`, 'exito');
            
            // Animar el cambio
            stockElement.style.transform = 'scale(1.2)';
            setTimeout(() => {
                stockElement.style.transform = 'scale(1)';
            }, 200);
            
        } else {
            mostrarNotificacion(data.message || 'Error al actualizar el stock', 'error');
        }
    } catch (error) {
        console.error('Error en actualizarStock:', error);
        mostrarNotificacion('Error de conexión al actualizar el stock', 'error');
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

// Función para actualizar clases de stock
function actualizarClasesStock(element, cantidad) {
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
    }
}

// Función para actualizar botón de transferencia
function actualizarBotonTransferencia(cantidad) {
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

// Configurar modal de transferencia
function configurarModal() {
    const modal = document.getElementById('modalTransferencia');
    const form = document.getElementById('formTransferencia');
    
    if (!modal || !form) return;

    // Configurar botones de cerrar
    const closeButtons = modal.querySelectorAll('.modal-close, .btn-cancel');
    closeButtons.forEach(button => {
        button.addEventListener('click', () => cerrarModal());
    });

    // Cerrar modal al hacer clic fuera
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            cerrarModal();
        }
    });

    // Configurar formulario
    form.addEventListener('submit', (e) => enviarFormulario(e));

    // Configurar controles de cantidad
    configurarControlesCantidad();
}

// Configurar controles de cantidad del modal
function configurarControlesCantidad() {
    const minusBtn = document.querySelector('#modalTransferencia .qty-btn.minus');
    const plusBtn = document.querySelector('#modalTransferencia .qty-btn.plus');
    const quantityInput = document.querySelector('#modalTransferencia .qty-input');

    if (minusBtn && plusBtn && quantityInput) {
        minusBtn.addEventListener('click', () => adjustQuantity(-1));
        plusBtn.addEventListener('click', () => adjustQuantity(1));
        
        quantityInput.addEventListener('change', () => validarCantidad());
        quantityInput.addEventListener('input', () => validarCantidad());
    }
}

// Configurar auto-cerrar alertas
function configurarAlertas() {
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

// Función para abrir modal de transferencia
function abrirModalTransferencia(button) {
    const modal = document.getElementById('modalTransferencia');
    if (!modal) return;

    const datos = {
        id: button.dataset.id,
        nombre: button.dataset.nombre,
        almacen: button.dataset.almacen,
        cantidad: button.dataset.cantidad
    };

    console.log('Abriendo modal con datos:', datos);

    document.getElementById('producto_id_modal').value = datos.id;
    document.getElementById('almacen_origen_modal').value = datos.almacen;
    document.getElementById('producto_nombre_modal').textContent = datos.nombre;
    document.getElementById('stock_disponible_modal').textContent = `${datos.cantidad} unidades`;
    
    const quantityInput = document.getElementById('cantidad_modal');
    quantityInput.value = 1;
    quantityInput.max = datos.cantidad;
    
    document.getElementById('almacen_destino_modal').value = '';
    
    maxStock = parseInt(datos.cantidad);
    
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');
    
    setTimeout(() => {
        quantityInput.focus();
    }, 100);
    
    document.body.style.overflow = 'hidden';
}

// Función para cerrar modal
function cerrarModal() {
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

// Función para ajustar cantidad
function adjustQuantity(increment) {
    const quantityInput = document.getElementById('cantidad_modal');
    if (!quantityInput) return;

    let currentValue = parseInt(quantityInput.value) || 1;
    let newValue = currentValue + increment;
    
    newValue = Math.max(1, Math.min(newValue, maxStock));
    
    quantityInput.value = newValue;
    validarCantidad();
}

// Función para validar cantidad
function validarCantidad() {
    const quantityInput = document.getElementById('cantidad_modal');
    if (!quantityInput) return;

    const value = parseInt(quantityInput.value);
    const submitButton = document.querySelector('#formTransferencia .btn-confirm');
    
    if (value < 1 || value > maxStock || isNaN(value)) {
        quantityInput.style.borderColor = '#dc3545';
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.style.opacity = '0.6';
        }
        
        if (value > maxStock) {
            mostrarNotificacion(`La cantidad no puede ser mayor a ${maxStock}`, 'error');
        }
    } else {
        quantityInput.style.borderColor = '#28a745';
        if (submitButton) {
            submitButton.disabled = false;
            submitButton.style.opacity = '1';
        }
    }
}

// Función para enviar formulario
async function enviarFormulario(e) {
    e.preventDefault();
    
    const submitButton = e.target.querySelector('.btn-confirm');
    const originalText = submitButton.innerHTML;
    
    const cantidad = parseInt(document.getElementById('cantidad_modal').value);
    const almacenDestino = document.getElementById('almacen_destino_modal').value;
    
    if (!almacenDestino) {
        mostrarNotificacion('Debe seleccionar un almacén de destino', 'error');
        return;
    }
    
    if (cantidad < 1 || cantidad > maxStock) {
        mostrarNotificacion('La cantidad no es válida', 'error');
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
            mostrarNotificacion(data.message, 'exito');
            cerrarModal();
            
            setTimeout(() => {
                window.location.reload();
            }, 2000);
        } else {
            mostrarNotificacion(data.message || 'Error al solicitar transferencia', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        mostrarNotificacion('Error de conexión al solicitar transferencia', 'error');
    } finally {
        submitButton.innerHTML = originalText;
        submitButton.disabled = false;
    }
}

// Función para mostrar notificaciones
function mostrarNotificacion(mensaje, tipo = 'info', duracion = 5000) {
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

    // Agregar animaciones CSS si no existen
    if (!document.getElementById('notification-animations')) {
        const animationStyles = document.createElement('style');
        animationStyles.id = 'notification-animations';
        animationStyles.textContent = `
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
        `;
        document.head.appendChild(animationStyles);
    }
}

// Funciones adicionales para compatibilidad
function editarProducto(id) {
    window.location.href = `editar.php?id=${id}`;
}

async function eliminarProducto(id, nombre) {
    const confirmado = confirm(`¿Estás seguro que deseas eliminar el producto "${nombre}"? Esta acción no se puede deshacer.`);
    
    if (confirmado) {
        mostrarNotificacion('Eliminando producto...', 'info');
        
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
                mostrarNotificacion('Producto eliminado correctamente', 'exito');
                
                setTimeout(() => {
                    window.location.href = 'listar.php';
                }, 2000);
            } else {
                mostrarNotificacion(data.message || 'Error al eliminar el producto', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            mostrarNotificacion('Error de conexión al eliminar el producto', 'error');
        }
    }
}

async function manejarCerrarSesion(event) {
    event.preventDefault();
    
    const confirmado = confirm('¿Estás seguro que deseas cerrar sesión?');
    
    if (confirmado) {
        mostrarNotificacion('Cerrando sesión...', 'info', 2000);
        setTimeout(() => {
            window.location.href = '../logout.php';
        }, 1000);
    }
}