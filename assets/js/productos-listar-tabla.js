/* ============================================
   PRODUCTOS LISTAR TABLA - JAVASCRIPT ESPECÍFICO
   ============================================ */

class ProductosListarTabla {
    constructor() {
        this.stockButtonsProcessed = false;
        this.inicializar();
        this.configurarEventListeners();
        this.configurarModal();
    }

    inicializar() {
        // Configurar sidebar
        this.configurarSidebar();
        
        // Auto-cerrar alertas
        this.configurarAlertas();
        
        // Configurar controles de stock (SOLO UNA VEZ)
        if (!this.stockButtonsProcessed) {
            this.configurarControlesStock();
            this.stockButtonsProcessed = true;
        }
        
        // Configurar tabla responsiva
        this.configurarTablaResponsiva();
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
        console.log('🔧 Configurando controles de stock para tabla...');
        
        // Remover listeners existentes para evitar duplicados
        const existingButtons = document.querySelectorAll('.stock-btn');
        existingButtons.forEach(btn => {
            btn.replaceWith(btn.cloneNode(true));
        });
        
        // Obtener botones frescos sin listeners
        const stockButtons = document.querySelectorAll('.stock-btn');
        console.log(`📊 Encontrados ${stockButtons.length} botones de stock en tabla`);
        
        stockButtons.forEach((button, index) => {
            button.addEventListener('click', async (e) => {
                e.preventDefault();
                e.stopPropagation();
                
                console.log(`🖱️ Click en botón ${index + 1} de tabla`);
                
                const productId = button.dataset.id;
                const accion = button.dataset.accion;
                
                if (productId && accion) {
                    await this.actualizarStock(productId, accion, button);
                }
            }, { once: false });
        });
    }

    async actualizarStock(productId, accion, button) {
        console.log(`🔄 Actualizando stock en tabla: Producto ${productId}, Acción: ${accion}`);
        
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

            console.log('📤 Enviando petición a actualizar_cantidad.php desde tabla');
            const response = await fetch('actualizar_cantidad.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const data = await response.json();
            console.log('📥 Respuesta recibida en tabla:', data);

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
            console.error('❌ Error en tabla:', error);
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
        // Buscar el botón de transferencia en la fila correspondiente
        const fila = document.querySelector(`[data-producto-id="${productId}"]`);
        if (fila) {
            const transferButton = fila.querySelector('.btn-transfer');
            if (transferButton) {
                if (nuevaCantidad > 0) {
                    transferButton.disabled = false;
                    transferButton.classList.remove('disabled');
                    transferButton.innerHTML = '<i class="fas fa-paper-plane"></i>';
                    transferButton.dataset.cantidad = nuevaCantidad;
                    transferButton.style.opacity = '1';
                    transferButton.style.cursor = 'pointer';
                    transferButton.title = 'Transferir producto';
                } else {
                    transferButton.disabled = true;
                    transferButton.classList.add('disabled');
                    transferButton.innerHTML = '<i class="fas fa-times"></i>';
                    transferButton.style.opacity = '0.6';
                    transferButton.style.cursor = 'not-allowed';
                    transferButton.title = 'Sin stock disponible';
                }
            }
        }
    }

    configurarTablaResponsiva() {
        // Hacer la tabla scrolleable horizontalmente en dispositivos móviles
        const tableContainer = document.querySelector('.table-container');
        if (tableContainer) {
            let isDown = false;
            let startX;
            let scrollLeft;

            tableContainer.addEventListener('mousedown', (e) => {
                isDown = true;
                startX = e.pageX - tableContainer.offsetLeft;
                scrollLeft = tableContainer.scrollLeft;
                tableContainer.style.cursor = 'grabbing';
            });

            tableContainer.addEventListener('mouseleave', () => {
                isDown = false;
                tableContainer.style.cursor = 'grab';
            });

            tableContainer.addEventListener('mouseup', () => {
                isDown = false;
                tableContainer.style.cursor = 'grab';
            });

            tableContainer.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                e.preventDefault();
                const x = e.pageX - tableContainer.offsetLeft;
                const walk = (x - startX) * 2;
                tableContainer.scrollLeft = scrollLeft - walk;
            });
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

        console.log('Abriendo modal desde tabla con datos:', datos);

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

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

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
            <button class="cerrar" onclick="this.parentElement.remove()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #666; padding: 0;">&times;</button>
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

// ===== SISTEMA DE ENTREGA MÚLTIPLE ADAPTADO PARA TABLA =====

class EntregaMultipleTabla {
    constructor() {
        this.modoSeleccion = false;
        this.productosSeleccionados = new Map();
        this.inicializar();
    }

    inicializar() {
        this.btnEntregarPersonal = document.getElementById('btnEntregarPersonal');
        this.carritoEntrega = document.getElementById('carritoEntrega');
        this.modalEntrega = document.getElementById('modalEntrega');
        this.tabla = document.getElementById('productosTabla');
        
        this.btnEntregarPersonal.addEventListener('click', () => this.toggleModoSeleccion());
        
        // Configurar validación de DNI
        const dniInput = document.getElementById('dniDestinatario');
        if (dniInput) {
            dniInput.addEventListener('input', this.validarDNI.bind(this));
        }
    }

    toggleModoSeleccion() {
        this.modoSeleccion = !this.modoSeleccion;
        
        if (this.modoSeleccion) {
            this.activarModoSeleccion();
        } else {
            this.desactivarModoSeleccion();
        }
    }

    activarModoSeleccion() {
        const productsSection = document.getElementById('productsSection');
        productsSection.classList.add('modo-seleccion');
        
        // Cambiar texto del botón
        this.btnEntregarPersonal.innerHTML = '<i class="fas fa-times"></i><span>Cancelar Selección</span>';
        this.btnEntregarPersonal.classList.add('active');
        
        // Mostrar columnas de selección
        document.querySelectorAll('.selection-column, .selection-cell').forEach(el => {
            el.style.display = '';
        });
        
        // Configurar click handlers para filas
        document.querySelectorAll('.product-row').forEach(row => {
            row.addEventListener('click', this.handleRowClick.bind(this));
        });
        
        // Configurar checkboxes
        document.querySelectorAll('.selection-checkbox').forEach(checkbox => {
            checkbox.addEventListener('click', this.handleCheckboxClick.bind(this));
        });
        
        // Mostrar carrito si hay productos seleccionados
        if (this.productosSeleccionados.size > 0) {
            this.mostrarCarrito();
        }
    }

    desactivarModoSeleccion() {
        const productsSection = document.getElementById('productsSection');
        productsSection.classList.remove('modo-seleccion');
        
        // Restaurar texto del botón
        this.btnEntregarPersonal.innerHTML = '<i class="fas fa-hand-holding"></i><span>Entregar a Personal</span>';
        this.btnEntregarPersonal.classList.remove('active');
        
        // Ocultar columnas de selección
        document.querySelectorAll('.selection-column, .selection-cell').forEach(el => {
            el.style.display = 'none';
        });
        
        // Remover click handlers
        document.querySelectorAll('.product-row').forEach(row => {
            row.removeEventListener('click', this.handleRowClick);
            row.classList.remove('selected');
        });
        
        // Ocultar carrito
        this.ocultarCarrito();
        
        // Limpiar selecciones
        this.productosSeleccionados.clear();
        document.querySelectorAll('.selection-checkbox').forEach(checkbox => {
            checkbox.classList.remove('checked');
        });
    }

    handleRowClick(e) {
        if (!this.modoSeleccion) return;
        
        // Evitar que se active si se clickea en botones o controles
        if (e.target.closest('.btn-action, .stock-btn, .selection-checkbox')) {
            return;
        }
        
        e.preventDefault();
        e.stopPropagation();
        
        const row = e.currentTarget;
        const productId = row.dataset.productoId;
        const checkbox = row.querySelector('.selection-checkbox');
        
        this.toggleProducto(productId, row, checkbox);
    }

    handleCheckboxClick(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const checkbox = e.currentTarget;
        const productId = checkbox.dataset.id;
        const row = document.querySelector(`[data-producto-id="${productId}"]`);
        
        this.toggleProducto(productId, row, checkbox);
    }

    toggleProducto(productId, row, checkbox) {
        const stockValue = row.querySelector('.stock-value');
        const stock = parseInt(stockValue.textContent.replace(/,/g, ''));
        
        // Verificar si tiene stock
        if (stock <= 0) {
            this.mostrarNotificacion('Este producto no tiene stock disponible', 'warning');
            return;
        }
        
        if (this.productosSeleccionados.has(productId)) {
            // Deseleccionar
            this.productosSeleccionados.delete(productId);
            row.classList.remove('selected');
            checkbox.classList.remove('checked');
        } else {
            // Seleccionar
            const productData = this.extraerDatosProducto(row);
            this.productosSeleccionados.set(productId, {
                ...productData,
                cantidadSeleccionada: 1
            });
            row.classList.add('selected');
            checkbox.classList.add('checked');
        }
        
        this.actualizarCarrito();
    }

    extraerDatosProducto(row) {
        const scriptTag = row.querySelector('.product-data');
        if (scriptTag) {
            try {
                return JSON.parse(scriptTag.textContent);
            } catch (e) {
                console.error('Error parsing product data:', e);
            }
        }
        
        // Fallback manual
        return {
            id: row.dataset.productoId,
            nombre: row.querySelector('.product-name').textContent,
            cantidad: parseInt(row.querySelector('.stock-value').textContent.replace(/,/g, '')),
            almacen: document.body.dataset.almacenId
        };
    }

    actualizarCarrito() {
        if (this.productosSeleccionados.size > 0) {
            this.mostrarCarrito();
            this.renderizarCarrito();
        } else {
            this.ocultarCarrito();
        }
    }

    mostrarCarrito() {
        this.carritoEntrega.classList.add('visible');
    }

    ocultarCarrito() {
        this.carritoEntrega.classList.remove('visible');
    }

    renderizarCarrito() {
        const contador = document.querySelector('.carrito-contador');
        const lista = document.getElementById('carritoLista');
        const totalUnidades = document.getElementById('totalUnidades');
        const btnProceder = document.querySelector('.btn-proceder');
        
        contador.textContent = this.productosSeleccionados.size;
        
        if (this.productosSeleccionados.size === 0) {
            lista.innerHTML = `
                <div class="carrito-vacio">
                    <i class="fas fa-hand-holding"></i>
                    <p>Selecciona productos para entregar</p>
                </div>
            `;
            btnProceder.disabled = true;
            totalUnidades.textContent = '0';
            return;
        }
        
        let html = '';
        let total = 0;
        
        this.productosSeleccionados.forEach((producto, id) => {
            total += producto.cantidadSeleccionada;
            
            html += `
                <div class="carrito-item" data-id="${id}">
                    <div class="carrito-item-info">
                        <div class="carrito-item-nombre">${producto.nombre}</div>
                        <div class="carrito-item-detalles">
                            Stock: ${producto.cantidad.toLocaleString()} | Almacén: ${producto.almacen_nombre || 'N/A'}
                        </div>
                    </div>
                    <div class="carrito-item-cantidad">
                        <div class="cantidad-control">
                            <button class="cantidad-btn" onclick="entregaMultipleTabla.ajustarCantidad('${id}', -1)">
                                <i class="fas fa-minus"></i>
                            </button>
                            <input type="number" class="cantidad-input" value="${producto.cantidadSeleccionada}" 
                                   min="1" max="${producto.cantidad}"
                                   onchange="entregaMultipleTabla.cambiarCantidad('${id}', this.value)">
                            <button class="cantidad-btn" onclick="entregaMultipleTabla.ajustarCantidad('${id}', 1)">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                        <button class="btn-remover" onclick="entregaMultipleTabla.removerProducto('${id}')" title="Remover producto">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        
        lista.innerHTML = html;
        totalUnidades.textContent = total.toLocaleString();
        btnProceder.disabled = false;
    }

    ajustarCantidad(productId, delta) {
        const producto = this.productosSeleccionados.get(productId);
        if (!producto) return;
        
        const nuevaCantidad = producto.cantidadSeleccionada + delta;
        
        if (nuevaCantidad < 1 || nuevaCantidad > producto.cantidad) {
            if (nuevaCantidad > producto.cantidad) {
                this.mostrarNotificacion('No puedes seleccionar más del stock disponible', 'warning');
            }
            return;
        }
        
        producto.cantidadSeleccionada = nuevaCantidad;
        this.renderizarCarrito();
    }

    cambiarCantidad(productId, nuevaCantidad) {
        const producto = this.productosSeleccionados.get(productId);
        if (!producto) return;
        
        nuevaCantidad = parseInt(nuevaCantidad);
        
        if (isNaN(nuevaCantidad) || nuevaCantidad < 1 || nuevaCantidad > producto.cantidad) {
            this.renderizarCarrito(); // Restaurar valor anterior
            return;
        }
        
        producto.cantidadSeleccionada = nuevaCantidad;
        this.renderizarCarrito();
    }

    removerProducto(productId) {
        this.productosSeleccionados.delete(productId);
        
        // Actualizar UI
        const row = document.querySelector(`[data-producto-id="${productId}"]`);
        const checkbox = row?.querySelector('.selection-checkbox');
        
        if (row) row.classList.remove('selected');
        if (checkbox) checkbox.classList.remove('checked');
        
        this.actualizarCarrito();
    }

    limpiarCarrito() {
        // Limpiar selecciones visuales
        document.querySelectorAll('.product-row.selected').forEach(row => {
            row.classList.remove('selected');
        });
        
        document.querySelectorAll('.selection-checkbox.checked').forEach(checkbox => {
            checkbox.classList.remove('checked');
        });
        
        // Limpiar datos
        this.productosSeleccionados.clear();
        this.actualizarCarrito();
    }

    procederEntrega() {
        if (this.productosSeleccionados.size === 0) {
            this.mostrarNotificacion('No hay productos seleccionados', 'warning');
            return;
        }
        
        this.mostrarModalEntrega();
    }

    mostrarModalEntrega() {
        // Preparar resumen
        const productosResumen = document.getElementById('productosResumen');
        const totalUnidadesModal = document.getElementById('totalUnidadesModal');
        const totalTiposModal = document.getElementById('totalTiposModal');
        
        let html = '';
        let totalUnidades = 0;
        
        this.productosSeleccionados.forEach(producto => {
            totalUnidades += producto.cantidadSeleccionada;
            
            html += `
                <div class="producto-resumen-item">
                    <div>
                        <strong>${producto.nombre}</strong>
                        ${producto.modelo ? `<br><small>Modelo: ${producto.modelo}</small>` : ''}
                        ${producto.color ? `<br><small>Color: ${producto.color}</small>` : ''}
                        ${producto.talla ? `<br><small>Talla: ${producto.talla}</small>` : ''}
                    </div>
                    <div>
                        <strong>${producto.cantidadSeleccionada} unidad${producto.cantidadSeleccionada !== 1 ? 'es' : ''}</strong>
                    </div>
                </div>
            `;
        });
        
        productosResumen.innerHTML = html;
        totalUnidadesModal.textContent = totalUnidades.toLocaleString();
        totalTiposModal.textContent = this.productosSeleccionados.size;
        
        // Limpiar formulario
        document.getElementById('formEntregaPersonal').reset();
        
        // Mostrar modal
        this.modalEntrega.classList.add('visible');
        document.body.style.overflow = 'hidden';
        
        // Focus en primer campo
        setTimeout(() => {
            document.getElementById('nombreDestinatario').focus();
        }, 300);
    }

    cerrarModalEntrega() {
        this.modalEntrega.classList.remove('visible');
        document.body.style.overflow = '';
    }

    validarDNI(e) {
        const valor = e.target.value;
        // Solo permitir números
        e.target.value = valor.replace(/[^0-9]/g, '');
        
        const btnConfirmar = document.querySelector('.modal-entrega .btn-confirm');
        if (btnConfirmar) {
            btnConfirmar.disabled = e.target.value.length !== 8;
        }
    }

    async confirmarEntrega() {
        const form = document.getElementById('formEntregaPersonal');
        const formData = new FormData(form);
        
        // Validaciones
        const nombre = formData.get('nombre_destinatario').trim();
        const dni = formData.get('dni_destinatario').trim();
        
        if (!nombre || nombre.length < 3) {
            this.mostrarNotificacion('El nombre debe tener al menos 3 caracteres', 'error');
            return;
        }
        
        if (!dni || dni.length !== 8 || !/^\d{8}$/.test(dni)) {
            this.mostrarNotificacion('El DNI debe tener exactamente 8 dígitos', 'error');
            return;
        }
        
        // Preparar datos de productos
        const productos = Array.from(this.productosSeleccionados.values()).map(p => ({
            id: p.id,
            cantidad: p.cantidadSeleccionada,
            almacen: p.almacen
        }));
        
        formData.append('productos', JSON.stringify(productos));
        
        // Mostrar loading
        const btnConfirmar = document.querySelector('.modal-entrega .btn-confirm');
        const textoOriginal = btnConfirmar.innerHTML;
        btnConfirmar.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
        btnConfirmar.disabled = true;
        
        try {
            const response = await fetch('../entregas/Procesar_entrega.php', {
                method: 'POST',
                body: formData
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.mostrarNotificacion(data.message, 'exito', 8000);
                this.cerrarModalEntrega();
                this.desactivarModoSeleccion();
                
                // Actualizar stock en la interfaz
                if (data.productos_actualizados) {
                    data.productos_actualizados.forEach(prod => {
                        const stockElement = document.getElementById(`cantidad-${prod.id}`);
                        if (stockElement) {
                            stockElement.textContent = prod.nuevo_stock.toLocaleString();
                            
                            // Actualizar clases de color
                            stockElement.classList.remove('stock-critical', 'stock-warning', 'stock-good');
                            
                            if (prod.nuevo_stock < 5) {
                                stockElement.classList.add('stock-critical');
                            } else if (prod.nuevo_stock < 10) {
                                stockElement.classList.add('stock-warning');
                            } else {
                                stockElement.classList.add('stock-good');
                            }
                        }
                    });
                }
                
                // Recargar página después de un momento
                setTimeout(() => {
                    window.location.reload();
                }, 3000);
                
            } else {
                this.mostrarNotificacion(data.message || 'Error al procesar la entrega', 'error');
            }
            
        } catch (error) {
            console.error('Error:', error);
            this.mostrarNotificacion('Error de conexión al procesar la entrega', 'error');
        } finally {
            btnConfirmar.innerHTML = textoOriginal;
            btnConfirmar.disabled = false;
        }
    }

    mostrarNotificacion(mensaje, tipo = 'info', duracion = 5000) {
        // Usar el sistema existente de notificaciones
        if (window.productosListarTabla && window.productosListarTabla.mostrarNotificacion) {
            window.productosListarTabla.mostrarNotificacion(mensaje, tipo, duracion);
        } else {
            // Fallback
            alert(mensaje);
        }
    }
}

// ===== FUNCIONES GLOBALES PARA COMPATIBILIDAD =====

function abrirModalEnvio(button) {
    const datos = {
        id: button.dataset.id,
        nombre: button.dataset.nombre,
        almacen: button.dataset.almacen,
        cantidad: button.dataset.cantidad
    };
    
    window.productosListarTabla.abrirModal(datos);
}

function cerrarModal() {
    window.productosListarTabla.cerrarModal();
}

function adjustQuantity(increment) {
    window.productosListarTabla.adjustQuantity(increment);
}

function verProducto(id) {
    window.location.href = `ver-producto.php?id=${id}`;
}

function editarProducto(id) {
    window.location.href = `editar.php?id=${id}`;
}

async function eliminarProducto(id, nombre) {
    const confirmado = await window.productosListarTabla.confirmarAccion(
        `¿Estás seguro que deseas eliminar el producto "${nombre}"? Esta acción no se puede deshacer.`,
        'Eliminar Producto',
        'danger'
    );
    
    if (confirmado) {
        window.productosListarTabla.mostrarNotificacion('Eliminando producto...', 'info');
        
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
                window.productosListarTabla.mostrarNotificacion('Producto eliminado correctamente', 'exito');
                
                // Remover la fila de la tabla con animación
                const row = document.querySelector(`[data-producto-id="${id}"]`);
                if (row) {
                    row.style.animation = 'fadeOut 0.5s ease';
                    setTimeout(() => {
                        row.remove();
                        
                        // Verificar si quedan productos
                        const remainingRows = document.querySelectorAll('.product-row');
                        if (remainingRows.length === 0) {
                            location.reload(); // Recargar para mostrar estado vacío
                        }
                    }, 500);
                }
            } else {
                window.productosListarTabla.mostrarNotificacion(data.message || 'Error al eliminar el producto', 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            window.productosListarTabla.mostrarNotificacion('Error de conexión al eliminar el producto', 'error');
        }
    }
}

function manejarCerrarSesion(event) {
    window.productosListarTabla.manejarCerrarSesion(event);
}

// ===== FUNCIONES PARA ENTREGA MÚLTIPLE =====

function limpiarCarrito() {
    window.entregaMultipleTabla.limpiarCarrito();
}

function procederEntrega() {
    window.entregaMultipleTabla.procederEntrega();
}

function cerrarModalEntrega() {
    window.entregaMultipleTabla.cerrarModalEntrega();
}

function confirmarEntrega() {
    window.entregaMultipleTabla.confirmarEntrega();
}

// ===== AGREGAR ESTILOS DE ANIMACIÓN ADICIONALES =====
const additionalStyles = `
    @keyframes fadeOut {
        from { opacity: 1; transform: scale(1); }
        to { opacity: 0; transform: scale(0.95); }
    }
    
    @keyframes slideOutUp {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-20px); }
    }
    
    @keyframes slideInRight {
        from { opacity: 0; transform: translateX(30px); }
        to { opacity: 1; transform: translateX(0); }
    }
    
    @keyframes slideOutRight {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(30px); }
    }
`;

// Inyectar estilos adicionales si no existen
if (!document.getElementById('productos-tabla-animations')) {
    const styleSheet = document.createElement('style');
    styleSheet.id = 'productos-tabla-animations';
    styleSheet.textContent = additionalStyles;
    document.head.appendChild(styleSheet);
}

// ===== INICIALIZACIÓN =====
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Inicializando ProductosListarTabla...');
    window.productosListarTabla = new ProductosListarTabla();
    
    console.log('🚀 Inicializando EntregaMultipleTabla...');
    window.entregaMultipleTabla = new EntregaMultipleTabla();
    
    console.log('✅ Sistemas de tabla inicializados correctamente');
    
    // Cerrar modal con Escape
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const modalEntrega = document.getElementById('modalEntrega');
            if (modalEntrega && modalEntrega.classList.contains('visible')) {
                window.entregaMultipleTabla.cerrarModalEntrega();
            }
        }
    });
});