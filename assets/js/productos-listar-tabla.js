/* ============================================
   PRODUCTOS LISTAR - JAVASCRIPT LIMPIO
   Sin conflictos, separado y optimizado
   ============================================ */

// ===== VARIABLES GLOBALES =====
let modoSeleccion = false;
let productosSeleccionados = new Set();
let carritoEntrega = [];

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', function() {
    inicializarComponentes();
    configurarEventListeners();
    inicializarSidebar();
    configurarTeclasRapidas();
});

// ===== INICIALIZACIÓN DE COMPONENTES =====
function inicializarComponentes() {
    // Precargar página siguiente si es posible
    precargarPaginaSiguiente();
    
    // Configurar tooltips si los hay
    configurarTooltips();
    
    // Inicializar efectos visuales
    inicializarEfectosVisuales();
    
    console.log('Componentes inicializados correctamente');
}

// ===== CONFIGURACIÓN DE EVENT LISTENERS =====
function configurarEventListeners() {
    // Botón de entrega a personal
    const btnEntregarPersonal = document.getElementById('btnEntregarPersonal');
    if (btnEntregarPersonal) {
        btnEntregarPersonal.addEventListener('click', toggleModoSeleccion);
    }
    
    // Botones de stock (solo para admin)
    document.addEventListener('click', function(e) {
        if (e.target.closest('.stock-btn')) {
            manejarCambioStock(e.target.closest('.stock-btn'));
        }
    });
    
    // Checkboxes de selección
    document.addEventListener('click', function(e) {
        if (e.target.closest('.selection-checkbox')) {
            manejarSeleccionProducto(e.target.closest('.selection-checkbox'));
        }
    });
    
    // Formulario de búsqueda
    const searchForm = document.querySelector('.search-form');
    if (searchForm) {
        searchForm.addEventListener('submit', function() {
            mostrarIndicadorCarga();
        });
    }
    
    // Enlaces de paginación
    document.querySelectorAll('.pagination-btn:not(.current)').forEach(btn => {
        btn.addEventListener('click', function() {
            mostrarIndicadorCarga();
        });
    });
    
    // Validación en tiempo real del DNI
    const dniInput = document.getElementById('dniDestinatario');
    if (dniInput) {
        dniInput.addEventListener('input', validarDNI);
    }
    
    // Validación del nombre
    const nombreInput = document.getElementById('nombreDestinatario');
    if (nombreInput) {
        nombreInput.addEventListener('input', validarFormularioEntrega);
    }
}

// ===== SIDEBAR =====
function inicializarSidebar() {
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('main-content');
    const submenuContainers = document.querySelectorAll('.submenu-container');
    
    // Toggle del menú móvil
    if (menuToggle) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            if (mainContent) {
                mainContent.classList.toggle('with-sidebar');
            }
            
            // Cambiar icono
            const icon = this.querySelector('i');
            if (sidebar.classList.contains('active')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    }
    
    // Submenús
    submenuContainers.forEach(container => {
        const link = container.querySelector('a');
        const submenu = container.querySelector('.submenu');
        const chevron = link.querySelector('.fa-chevron-down');
        
        if (link && submenu) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Cerrar otros submenús
                submenuContainers.forEach(otherContainer => {
                    if (otherContainer !== container) {
                        const otherSubmenu = otherContainer.querySelector('.submenu');
                        const otherChevron = otherContainer.querySelector('.fa-chevron-down');
                        
                        if (otherSubmenu && otherSubmenu.classList.contains('activo')) {
                            otherSubmenu.classList.remove('activo');
                            if (otherChevron) {
                                otherChevron.style.transform = 'rotate(0deg)';
                            }
                        }
                    }
                });
                
                // Toggle del submenú actual
                submenu.classList.toggle('activo');
                const isActive = submenu.classList.contains('activo');
                
                if (chevron) {
                    chevron.style.transform = isActive ? 'rotate(180deg)' : 'rotate(0deg)';
                }
            });
        }
    });
    
    // Cerrar menú móvil al hacer clic fuera
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                sidebar.classList.remove('active');
                if (mainContent) {
                    mainContent.classList.remove('with-sidebar');
                }
                
                const icon = menuToggle.querySelector('i');
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        }
    });
}

// ===== MODO SELECCIÓN MÚLTIPLE =====
function toggleModoSeleccion() {
    modoSeleccion = !modoSeleccion;
    const tabla = document.getElementById('productosTabla');
    const btnEntregarPersonal = document.getElementById('btnEntregarPersonal');
    const carritoEntrega = document.getElementById('carritoEntrega');
    
    if (modoSeleccion) {
        // Activar modo selección
        tabla.classList.add('modo-seleccion');
        btnEntregarPersonal.classList.add('active');
        btnEntregarPersonal.innerHTML = '<i class="fas fa-times"></i><span>Cancelar Selección</span>';
        carritoEntrega.classList.add('show');
        
        // Mostrar columnas de selección
        document.querySelectorAll('.selection-column, .selection-cell').forEach(el => {
            el.style.display = 'table-cell';
        });
        
        mostrarNotificacion('Modo de selección activado. Selecciona los productos para entregar.', 'info');
    } else {
        // Desactivar modo selección
        tabla.classList.remove('modo-seleccion');
        btnEntregarPersonal.classList.remove('active');
        btnEntregarPersonal.innerHTML = '<i class="fas fa-hand-holding"></i><span>Entregar a Personal</span>';
        carritoEntrega.classList.remove('show');
        
        // Ocultar columnas de selección
        document.querySelectorAll('.selection-column, .selection-cell').forEach(el => {
            el.style.display = 'none';
        });
        
        // Limpiar selecciones
        limpiarCarrito();
    }
}

function manejarSeleccionProducto(checkbox) {
    const productoId = checkbox.dataset.id;
    const isChecked = checkbox.classList.contains('checked');
    
    if (isChecked) {
        // Deseleccionar
        checkbox.classList.remove('checked');
        productosSeleccionados.delete(productoId);
        eliminarDelCarrito(productoId);
    } else {
        // Seleccionar
        checkbox.classList.add('checked');
        productosSeleccionados.add(productoId);
        agregarAlCarrito(productoId);
    }
    
    actualizarCarrito();
}

function agregarAlCarrito(productoId) {
    const row = document.querySelector(`[data-producto-id="${productoId}"]`);
    const productData = JSON.parse(row.querySelector('.product-data').textContent);
    
    const itemCarrito = {
        id: productData.id,
        nombre: productData.nombre,
        modelo: productData.modelo,
        color: productData.color,
        talla: productData.talla,
        cantidad: 1, // Por defecto 1, se puede ajustar
        maxCantidad: productData.cantidad,
        almacen: productData.almacen,
        almacenNombre: productData.almacen_nombre
    };
    
    carritoEntrega.push(itemCarrito);
}

function eliminarDelCarrito(productoId) {
    carritoEntrega = carritoEntrega.filter(item => item.id != productoId);
}

function actualizarCarrito() {
    const carritoLista = document.getElementById('carritoLista');
    const carritoContador = document.querySelector('.carrito-contador');
    const totalUnidades = document.getElementById('totalUnidades');
    const btnProceder = document.querySelector('.btn-proceder');
    
    if (carritoEntrega.length === 0) {
        carritoLista.innerHTML = `
            <div class="carrito-vacio">
                <i class="fas fa-hand-holding"></i>
                <p>Selecciona productos para entregar</p>
            </div>
        `;
        carritoContador.textContent = '0';
        totalUnidades.textContent = '0';
        btnProceder.disabled = true;
        return;
    }
    
    let html = '';
    let totalUnidadesCount = 0;
    
    carritoEntrega.forEach(item => {
        totalUnidadesCount += item.cantidad;
        html += `
            <div class="carrito-item" data-id="${item.id}">
                <div class="item-info">
                    <div class="item-nombre">${item.nombre}</div>
                    <div class="item-detalles">
                        ${item.modelo ? `Modelo: ${item.modelo}` : ''}
                        ${item.color ? ` | Color: ${item.color}` : ''}
                        ${item.talla ? ` | Talla: ${item.talla}` : ''}
                    </div>
                </div>
                <div class="item-cantidad">
                    <button class="qty-btn-small minus" onclick="ajustarCantidadCarrito(${item.id}, -1)">
                        <i class="fas fa-minus"></i>
                    </button>
                    <span class="qty-display">${item.cantidad}</span>
                    <button class="qty-btn-small plus" onclick="ajustarCantidadCarrito(${item.id}, 1)" 
                            ${item.cantidad >= item.maxCantidad ? 'disabled' : ''}>
                        <i class="fas fa-plus"></i>
                    </button>
                </div>
                <button class="item-remove" onclick="removerDelCarrito(${item.id})">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
    });
    
    carritoLista.innerHTML = html;
    carritoContador.textContent = carritoEntrega.length;
    totalUnidades.textContent = totalUnidadesCount;
    btnProceder.disabled = false;
}

function ajustarCantidadCarrito(productoId, cambio) {
    const item = carritoEntrega.find(item => item.id == productoId);
    if (item) {
        const nuevaCantidad = item.cantidad + cambio;
        if (nuevaCantidad >= 1 && nuevaCantidad <= item.maxCantidad) {
            item.cantidad = nuevaCantidad;
            actualizarCarrito();
        }
    }
}

function removerDelCarrito(productoId) {
    // Deseleccionar checkbox
    const checkbox = document.querySelector(`[data-id="${productoId}"]`);
    if (checkbox) {
        checkbox.classList.remove('checked');
    }
    
    productosSeleccionados.delete(productoId.toString());
    eliminarDelCarrito(productoId);
    actualizarCarrito();
}

function limpiarCarrito() {
    carritoEntrega = [];
    productosSeleccionados.clear();
    
    // Deseleccionar todos los checkboxes
    document.querySelectorAll('.selection-checkbox.checked').forEach(checkbox => {
        checkbox.classList.remove('checked');
    });
    
    actualizarCarrito();
}

function procederEntrega() {
    if (carritoEntrega.length === 0) {
        mostrarNotificacion('No hay productos seleccionados para entregar.', 'warning');
        return;
    }
    
    mostrarModalEntrega();
}

// ===== MODAL DE ENTREGA =====
function mostrarModalEntrega() {
    const modal = document.getElementById('modalEntrega');
    const productosResumen = document.getElementById('productosResumen');
    const totalUnidadesModal = document.getElementById('totalUnidadesModal');
    const totalTiposModal = document.getElementById('totalTiposModal');
    
    // Generar resumen
    let html = '';
    let totalUnidades = 0;
    
    carritoEntrega.forEach(item => {
        totalUnidades += item.cantidad;
        html += `
            <div class="producto-resumen-item">
                <div class="producto-resumen-info">
                    <strong>${item.nombre}</strong>
                    <div class="producto-resumen-detalles">
                        ${item.modelo ? `Modelo: ${item.modelo}` : ''}
                        ${item.color ? ` | Color: ${item.color}` : ''}
                        ${item.talla ? ` | Talla: ${item.talla}` : ''}
                    </div>
                </div>
                <div class="producto-resumen-cantidad">
                    <span class="cantidad-badge">${item.cantidad}</span>
                </div>
            </div>
        `;
    });
    
    productosResumen.innerHTML = html;
    totalUnidadesModal.textContent = totalUnidades;
    totalTiposModal.textContent = carritoEntrega.length;
    
    modal.classList.add('show');
    modal.style.display = 'flex';
    
    // Focus en el primer input
    setTimeout(() => {
        document.getElementById('nombreDestinatario').focus();
    }, 300);
}

function cerrarModalEntrega() {
    const modal = document.getElementById('modalEntrega');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
    
    // Limpiar formulario
    document.getElementById('formEntregaPersonal').reset();
    document.querySelector('.btn-confirm').disabled = true;
}

function validarFormularioEntrega() {
    const nombre = document.getElementById('nombreDestinatario').value.trim();
    const dni = document.getElementById('dniDestinatario').value.trim();
    const btnConfirmar = document.querySelector('.btn-confirm');
    
    const nombreValido = nombre.length >= 3;
    const dniValido = /^[0-9]{8}$/.test(dni);
    
    btnConfirmar.disabled = !(nombreValido && dniValido);
}

function validarDNI(e) {
    const input = e.target;
    let value = input.value.replace(/[^0-9]/g, '');
    
    if (value.length > 8) {
        value = value.substring(0, 8);
    }
    
    input.value = value;
    validarFormularioEntrega();
}

function confirmarEntrega() {
    const nombre = document.getElementById('nombreDestinatario').value.trim();
    const dni = document.getElementById('dniDestinatario').value.trim();
    
    if (!nombre || !dni || dni.length !== 8) {
        mostrarNotificacion('Por favor, complete todos los campos correctamente.', 'error');
        return;
    }
    
    // Preparar datos para envío
    const datosEntrega = {
        destinatario_nombre: nombre,
        destinatario_dni: dni,
        productos: carritoEntrega,
        fecha_entrega: new Date().toISOString().split('T')[0],
        entregado_por: document.body.dataset.userId || 'usuario'
    };
    
    // Simular envío (aquí iría la llamada al servidor)
    enviarEntrega(datosEntrega);
}

function enviarEntrega(datosEntrega) {
    mostrarNotificacion('Procesando entrega...', 'info');
    
    // Simular delay de procesamiento
    setTimeout(() => {
        // Aquí iría la llamada real al servidor
        console.log('Datos de entrega:', datosEntrega);
        
        mostrarNotificacion('Entrega registrada exitosamente.', 'success');
        
        // Cerrar modal y limpiar
        cerrarModalEntrega();
        toggleModoSeleccion(); // Salir del modo selección
        
        // Recargar página para actualizar stock
        setTimeout(() => {
            window.location.reload();
        }, 2000);
    }, 1500);
}

// ===== MODAL DE TRANSFERENCIA =====
function abrirModalEnvio(button) {
    const modal = document.getElementById('modalTransferencia');
    const productoId = button.dataset.id;
    const productoNombre = button.dataset.nombre;
    const almacenOrigen = button.dataset.almacen;
    const stockDisponible = button.dataset.cantidad;
    
    // Llenar datos del modal
    document.getElementById('producto_id').value = productoId;
    document.getElementById('almacen_origen').value = almacenOrigen;
    document.getElementById('producto_nombre').textContent = productoNombre;
    document.getElementById('stock_disponible').textContent = stockDisponible;
    document.getElementById('cantidad').max = stockDisponible;
    document.getElementById('cantidad').value = 1;
    
    modal.classList.add('show');
    modal.style.display = 'flex';
}

function cerrarModal() {
    const modal = document.getElementById('modalTransferencia');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

function adjustQuantity(change) {
    const cantidadInput = document.getElementById('cantidad');
    const currentValue = parseInt(cantidadInput.value) || 1;
    const maxValue = parseInt(cantidadInput.max);
    const newValue = currentValue + change;
    
    if (newValue >= 1 && newValue <= maxValue) {
        cantidadInput.value = newValue;
    }
}

// ===== MANEJO DE STOCK =====
function manejarCambioStock(button) {
    const productoId = button.dataset.id;
    const accion = button.dataset.accion;
    const stockElement = document.getElementById(`cantidad-${productoId}`);
    const currentStock = parseInt(stockElement.textContent);
    
    // Deshabilitar botón temporalmente
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    
    // Simular llamada al servidor
    setTimeout(() => {
        let newStock = currentStock;
        
        if (accion === 'sumar') {
            newStock += 1;
        } else if (accion === 'restar' && currentStock > 0) {
            newStock -= 1;
        }
        
        // Actualizar visualmente
        stockElement.textContent = newStock;
        actualizarClaseStock(stockElement, newStock);
        
        // Restaurar botón
        button.disabled = false;
        button.innerHTML = accion === 'sumar' ? '<i class="fas fa-plus"></i>' : '<i class="fas fa-minus"></i>';
        
        // Deshabilitar botón restar si llegó a 0
        if (accion === 'restar' && newStock === 0) {
            button.disabled = true;
        }
        
        mostrarNotificacion(`Stock ${accion === 'sumar' ? 'aumentado' : 'reducido'} correctamente.`, 'success');
    }, 800);
}

function actualizarClaseStock(element, cantidad) {
    element.classList.remove('stock-critical', 'stock-warning', 'stock-good');
    
    if (cantidad < 5) {
        element.classList.add('stock-critical');
    } else if (cantidad < 10) {
        element.classList.add('stock-warning');
    } else {
        element.classList.add('stock-good');
    }
}

// ===== FUNCIONES AUXILIARES =====
function verProducto(id) {
    mostrarNotificacion('Función ver producto en desarrollo.', 'info');
    console.log('Ver producto ID:', id);
}

function editarProducto(id) {
    mostrarNotificacion('Función editar producto en desarrollo.', 'info');
    console.log('Editar producto ID:', id);
}

function eliminarProducto(id, nombre) {
    if (confirm(`¿Estás seguro de que deseas eliminar el producto "${nombre}"?`)) {
        mostrarNotificacion('Función eliminar producto en desarrollo.', 'info');
        console.log('Eliminar producto ID:', id);
    }
}

function manejarCerrarSesion(event) {
    event.preventDefault();
    
    if (confirm('¿Estás seguro de que deseas cerrar sesión?')) {
        mostrarNotificacion('Cerrando sesión...', 'info');
        setTimeout(() => {
            window.location.href = '../logout.php';
        }, 1000);
    }
}

function mostrarIndicadorCarga() {
    const indicator = document.getElementById('loading-indicator');
    if (indicator) {
        indicator.style.display = 'flex';
    }
}

function precargarPaginaSiguiente() {
    const currentPage = parseInt(document.body.dataset.page);
    const totalPages = parseInt(document.body.dataset.totalPages);
    
    if (currentPage < totalPages) {
        const link = document.createElement('link');
        link.rel = 'prefetch';
        // Aquí construirías la URL de la siguiente página
        document.head.appendChild(link);
    }
}

function configurarTooltips() {
    // Configuración básica de tooltips si es necesaria
    document.querySelectorAll('[title]').forEach(element => {
        element.addEventListener('mouseenter', function(e) {
            // Lógica para mostrar tooltip personalizado si se desea
        });
    });
}

function inicializarEfectosVisuales() {
    // Efectos de entrada para las filas de la tabla
    const rows = document.querySelectorAll('.product-row');
    
    rows.forEach((row, index) => {
        row.style.opacity = '0';
        row.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            row.style.transition = 'all 0.3s ease';
            row.style.opacity = '1';
            row.style.transform = 'translateY(0)';
        }, index * 50);
    });
}

function configurarTeclasRapidas() {
    document.addEventListener('keydown', function(e) {
        // Solo actuar si no estamos en un input
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
            return;
        }
        
        switch(e.key) {
            case 'Escape':
                // Cerrar modales o salir del modo selección
                if (modoSeleccion) {
                    toggleModoSeleccion();
                } else {
                    cerrarModal();
                    cerrarModalEntrega();
                }
                break;
                
            case 'e':
            case 'E':
                if (!modoSeleccion) {
                    toggleModoSeleccion();
                }
                break;
        }
        
        if (e.ctrlKey || e.metaKey) {
            switch(e.key) {
                case 'f':
                case 'F':
                    e.preventDefault();
                    const searchInput = document.querySelector('input[name="busqueda"]');
                    if (searchInput) {
                        searchInput.focus();
                        searchInput.select();
                    }
                    break;
            }
        }
    });
}

// ===== SISTEMA DE NOTIFICACIONES =====
function mostrarNotificacion(mensaje, tipo = 'info', duracion = 4000) {
    const container = document.getElementById('notificaciones-container') || document.body;
    
    const notificacion = document.createElement('div');
    notificacion.className = `notificacion notificacion-${tipo}`;
    
    const iconos = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    notificacion.innerHTML = `
        <i class="fas ${iconos[tipo]}"></i>
        <span>${mensaje}</span>
        <button class="notificacion-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    // Estilos inline para la notificación
    notificacion.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        padding: 15px 20px;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        border-left: 4px solid var(--${tipo === 'success' ? 'success' : tipo === 'error' ? 'danger' : tipo === 'warning' ? 'warning' : 'info'}-color);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 10px;
        max-width: 400px;
        animation: slideInRight 0.3s ease;
    `;
    
    container.appendChild(notificacion);
    
    // Auto-remover después de la duración especificada
    if (duracion > 0) {
        setTimeout(() => {
            if (notificacion.parentElement) {
                notificacion.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => notificacion.remove(), 300);
            }
        }, duracion);
    }
}

// ===== ESTILOS CSS ADICIONALES INYECTADOS =====
const estilosAdicionales = `
    @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
    }
    
    @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
    }
    
    .carrito-item {
        display: flex;
        align-items: center;
        padding: 12px;
        border-bottom: 1px solid #eee;
        gap: 10px;
    }
    
    .item-info {
        flex: 1;
    }
    
    .item-nombre {
        font-weight: 600;
        color: #333;
        font-size: 14px;
    }
    
    .item-detalles {
        font-size: 12px;
        color: #666;
        margin-top: 2px;
    }
    
    .item-cantidad {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .qty-btn-small {
        width: 24px;
        height: 24px;
        border: none;
        border-radius: 4px;
        background: #f8f9fa;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        transition: all 0.2s ease;
    }
    
    .qty-btn-small:hover {
        background: #e9ecef;
    }
    
    .qty-btn-small.minus {
        color: #dc3545;
    }
    
    .qty-btn-small.plus {
        color: #28a745;
    }
    
    .qty-display {
        min-width: 20px;
        text-align: center;
        font-weight: 600;
    }
    
    .item-remove {
        width: 24px;
        height: 24px;
        border: none;
        border-radius: 4px;
        background: #dc3545;
        color: white;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 10px;
        transition: all 0.2s ease;
    }
    
    .item-remove:hover {
        background: #c82333;
    }
    
    .producto-resumen-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }
    
    .producto-resumen-detalles {
        font-size: 12px;
        color: #666;
        margin-top: 2px;
    }
    
    .cantidad-badge {
        background: #007bff;
        color: white;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .resumen-titulo {
        font-weight: 600;
        color: #333;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .total-unidades {
        padding: 15px 0;
        border-top: 2px solid #eee;
        font-weight: 600;
        color: #333;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .transfer-info {
        margin-bottom: 20px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        border: 1px solid #e9ecef;
    }
    
    .product-summary {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .product-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #007bff, #0056b3);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
    }
    
    .product-details-modal h3 {
        margin: 0 0 5px 0;
        color: #333;
        font-size: 16px;
    }
    
    .product-details-modal p {
        margin: 0;
        color: #666;
        font-size: 14px;
    }
    
    .stock-highlight {
        font-weight: 700;
        color: #007bff;
    }
    
    .quantity-input {
        display: flex;
        align-items: center;
        gap: 10px;
        justify-content: center;
        margin: 10px 0;
    }
    
    .qty-btn {
        width: 40px;
        height: 40px;
        border: 2px solid #007bff;
        border-radius: 8px;
        background: white;
        color: #007bff;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        transition: all 0.2s ease;
    }
    
    .qty-btn:hover {
        background: #007bff;
        color: white;
    }
    
    .qty-input {
        width: 80px;
        height: 40px;
        text-align: center;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 600;
    }
    
    .btn-modal {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-cancel {
        background: #6c757d;
        color: white;
    }
    
    .btn-cancel:hover {
        background: #5a6268;
    }
    
    .btn-confirm {
        background: #28a745;
        color: white;
    }
    
    .btn-confirm:hover:not(:disabled) {
        background: #218838;
    }
    
    .btn-confirm:disabled {
        background: #ccc;
        cursor: not-allowed;
    }
`;

// Inyectar estilos
const styleSheet = document.createElement('style');
styleSheet.textContent = estilosAdicionales;
document.head.appendChild(styleSheet);

// ===== FUNCIONES EXPUESTAS GLOBALMENTE =====
window.toggleModoSeleccion = toggleModoSeleccion;
window.limpiarCarrito = limpiarCarrito;
window.procederEntrega = procederEntrega;
window.cerrarModalEntrega = cerrarModalEntrega;
window.confirmarEntrega = confirmarEntrega;
window.abrirModalEnvio = abrirModalEnvio;
window.cerrarModal = cerrarModal;
window.adjustQuantity = adjustQuantity;
window.verProducto = verProducto;
window.editarProducto = editarProducto;
window.eliminarProducto = eliminarProducto;
window.manejarCerrarSesion = manejarCerrarSesion;
window.ajustarCantidadCarrito = ajustarCantidadCarrito;
window.removerDelCarrito = removerDelCarrito;