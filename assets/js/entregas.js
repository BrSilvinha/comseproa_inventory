document.addEventListener('DOMContentLoaded', function() {
    // Check if the user is a storekeeper
    const userRole = document.body.getAttribute('data-user-role');
    if (userRole !== 'almacenero') {
        console.log('Access restricted: Only storekeepers can use this functionality');
        return;
    }

    // Select all required elements with error handling
    const productTable = document.querySelector('.table-container table tbody');
    const zonaSeleccionados = document.getElementById('zona-seleccionados');
    const contadorSeleccionados = document.getElementById('contador-seleccionados');
    const listaSeleccionados = document.getElementById('lista-seleccionados');
    const btnLimpiarSeleccion = document.getElementById('btn-limpiar-seleccion');
    const btnContinuarEntrega = document.getElementById('btn-continuar-entrega');
    const btnEntregarUniforme = document.querySelector('.entregar-uniforme');
    const modalEntregaUniforme = document.getElementById('modalEntregaUniforme');
    const listaUniformesEntrega = document.getElementById('lista-uniformes-entrega');
    const formEntregaUniforme = document.getElementById('formEntregaUniforme');

    // Verificar que todos los elementos necesarios existan
    const requiredElements = [
        productTable, zonaSeleccionados, listaSeleccionados, 
        btnLimpiarSeleccion, btnContinuarEntrega, btnEntregarUniforme, 
        modalEntregaUniforme, listaUniformesEntrega, formEntregaUniforme
    ];

    if (requiredElements.some(el => !el)) {
        console.error('One or more required elements are missing');
        return;
    }

    // Crear botón de toggle para seleccionados
    const btnToggleSeleccionados = document.createElement('button');
    btnToggleSeleccionados.id = 'btn-toggle-seleccionados';
    btnToggleSeleccionados.innerHTML = `Productos Seleccionados <span class="contador">0</span>`;
    
    // Añadir el botón al documento
    document.body.appendChild(btnToggleSeleccionados);

    // Almacena los productos seleccionados globalmente
    let productosSeleccionados = JSON.parse(localStorage.getItem('productosSeleccionados') || '[]');
    let selectedProductIds = new Set(productosSeleccionados.map(p => p.id));

    // Función para guardar productos seleccionados en localStorage
    function saveSelectedProducts() {
        localStorage.setItem('productosSeleccionados', JSON.stringify(productosSeleccionados));
    }

    // Validar cantidad disponible al seleccionar
    function validarCantidadDisponible(productoId, cantidadSeleccionada) {
        const producto = document.querySelector(`.producto-checkbox[data-id="${productoId}"]`);
        const cantidadDisponible = parseInt(producto.getAttribute('data-cantidad'));
        return cantidadSeleccionada <= cantidadDisponible;
    }

    // Al cargar la página, restaurar estado de selección
    function restoreSelectionState() {
        const rows = productTable.querySelectorAll('tr');
        rows.forEach(row => {
            const accionesCell = row.querySelector('td:last-child');
            if (accionesCell) {
                const botonEnviar = accionesCell.querySelector('.btn.enviar');
                if (botonEnviar) {
                    const productoId = botonEnviar.getAttribute('data-id');
                    const productoNombre = botonEnviar.getAttribute('data-nombre');
                    const productoCantidad = botonEnviar.getAttribute('data-cantidad');
                    const productoAlmacen = botonEnviar.getAttribute('data-almacen');

                    const btnEntregarUniformeClicked = localStorage.getItem('entregaUniformeActive') === 'true';
                    
                    if (btnEntregarUniformeClicked) {
                        // Crear checkbox
                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.classList.add('producto-checkbox');
                        checkbox.setAttribute('data-id', productoId);
                        checkbox.setAttribute('data-nombre', productoNombre);
                        checkbox.setAttribute('data-cantidad', productoCantidad);
                        checkbox.setAttribute('data-almacen', productoAlmacen);

                        // Restaurar estado de selección
                        if (selectedProductIds.has(productoId)) {
                            checkbox.checked = true;
                        }

                        // Evento para seleccionar/deseleccionar productos
                        checkbox.addEventListener('change', function() {
                            const producto = {
                                id: productoId,
                                nombre: productoNombre,
                                cantidad: productoCantidad,
                                almacen: productoAlmacen,
                                cantidadSeleccionada: 1 // Por defecto 1
                            };

                            if (this.checked) {
                                // Evitar duplicados
                                if (!selectedProductIds.has(productoId)) {
                                    selectedProductIds.add(productoId);
                                    productosSeleccionados.push(producto);
                                }
                            } else {
                                selectedProductIds.delete(productoId);
                                productosSeleccionados = productosSeleccionados.filter(
                                    p => p.id !== productoId
                                );
                            }

                            saveSelectedProducts();
                            actualizarSeleccionados();
                        });

                        // Reemplazar botón con checkbox
                        accionesCell.innerHTML = '';
                        accionesCell.appendChild(checkbox);
                    }
                }
            }
        });

        // Restaurar zona de seleccionados si hay productos
        actualizarSeleccionados();
    }

    // Transformar tabla a estado original
    function transformTableToSelectable() {
        const rows = productTable.querySelectorAll('tr');
        rows.forEach(row => {
            const accionesCell = row.querySelector('td:last-child');
            if (accionesCell) {
                const checkbox = accionesCell.querySelector('.producto-checkbox');
                if (checkbox) {
                    const productoId = checkbox.getAttribute('data-id');
                    const productoNombre = checkbox.getAttribute('data-nombre');
                    const productoAlmacen = checkbox.getAttribute('data-almacen');
                    const productoCantidad = checkbox.getAttribute('data-cantidad');

                    // Crear botón de enviar
                    const botonEnviar = document.createElement('button');
                    botonEnviar.classList.add('btn', 'enviar');
                    botonEnviar.setAttribute('data-id', productoId);
                    botonEnviar.setAttribute('data-nombre', productoNombre);
                    botonEnviar.setAttribute('data-almacen', productoAlmacen);
                    botonEnviar.setAttribute('data-cantidad', productoCantidad);
                    botonEnviar.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar';
                    
                    // Solo agregar botón para productos con cantidad > 0
                    if (parseInt(productoCantidad) > 0) {
                        accionesCell.innerHTML = '';
                        accionesCell.appendChild(botonEnviar);
                    } else {
                        accionesCell.innerHTML = '';
                    }
                }
            }
        });
    }

    // Añadir checkboxes cuando se active la entrega de uniformes
    function addCheckboxesToTable() {
        const rows = productTable.querySelectorAll('tr');
        rows.forEach(row => {
            const accionesCell = row.querySelector('td:last-child');
            if (accionesCell) {
                const botonEnviar = accionesCell.querySelector('.btn.enviar');
                if (botonEnviar) {
                    // Crear checkbox
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.classList.add('producto-checkbox');
                    
                    // Obtener datos del producto
                    const productoId = botonEnviar.getAttribute('data-id');
                    const productoNombre = botonEnviar.getAttribute('data-nombre');
                    const productoCantidad = botonEnviar.getAttribute('data-cantidad');
                    const productoAlmacen = botonEnviar.getAttribute('data-almacen');
                    
                    checkbox.setAttribute('data-id', productoId);
                    checkbox.setAttribute('data-nombre', productoNombre);
                    checkbox.setAttribute('data-cantidad', productoCantidad);
                    checkbox.setAttribute('data-almacen', productoAlmacen);

                    // Restaurar estado de selección
                    if (selectedProductIds.has(productoId)) {
                        checkbox.checked = true;
                    }

                    // Evento para seleccionar/deseleccionar productos
                    checkbox.addEventListener('change', function() {
                        const producto = {
                            id: productoId,
                            nombre: productoNombre,
                            cantidad: productoCantidad,
                            almacen: productoAlmacen,
                            cantidadSeleccionada: 1 // Por defecto 1
                        };

                        if (this.checked) {
                            // Evitar duplicados
                            if (!selectedProductIds.has(productoId)) {
                                selectedProductIds.add(productoId);
                                productosSeleccionados.push(producto);
                            }
                        } else {
                            selectedProductIds.delete(productoId);
                            productosSeleccionados = productosSeleccionados.filter(
                                p => p.id !== productoId
                            );
                        }

                        saveSelectedProducts();
                        actualizarSeleccionados();
                    });

                    // Reemplazar botón con checkbox
                    accionesCell.innerHTML = '';
                    accionesCell.appendChild(checkbox);
                }
            }
        });

        // Añadir botón de cancelar entrega
        const cancelarEntregaButton = document.createElement('button');
        cancelarEntregaButton.textContent = 'Cancelar Entrega';
        cancelarEntregaButton.classList.add('btn', 'btn-secundario', 'cancelar-entrega');
        
        // Añadir el botón de cancelar entrega después de la zona de seleccionados
        zonaSeleccionados.insertAdjacentElement('afterend', cancelarEntregaButton);

        // Evento para cancelar entrega
        cancelarEntregaButton.addEventListener('click', function() {
            limpiarEstadoEntrega();
        });
    }

    // Actualizar zona de productos seleccionados
    function actualizarSeleccionados() {
        // Actualizar contador en el botón
        const contadorBoton = btnToggleSeleccionados.querySelector('.contador');
        contadorBoton.textContent = productosSeleccionados.length;

        // Mostrar/ocultar zona de seleccionados
        if (productosSeleccionados.length > 0) {
            btnToggleSeleccionados.style.display = 'block';
        } else {
            btnToggleSeleccionados.style.display = 'none';
            zonaSeleccionados.classList.remove('activo');
        }

        // Actualizar lista de productos seleccionados
        listaSeleccionados.innerHTML = productosSeleccionados.map(producto => `
            <div class="producto-seleccionado" data-id="${producto.id}">
                <div>
                    <span>${producto.nombre}</span>
                    <div class="cantidad-producto">
                        <button class="cantidad-btn btn-menos">-</button>
                        <input type="number" value="${producto.cantidadSeleccionada || 1}" min="1" max="${producto.cantidad}">
                        <button class="cantidad-btn btn-mas">+</button>
                    </div>
                </div>
                <button class="btn-quitar-producto" data-id="${producto.id}">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `).join('');

        // Añadir eventos para quitar productos y controlar cantidad
        document.querySelectorAll('.btn-quitar-producto').forEach(btn => {
            btn.addEventListener('click', function() {
                const productoId = this.getAttribute('data-id');
                
                // Desmarcar checkbox
                const checkbox = document.querySelector(`.producto-checkbox[data-id="${productoId}"]`);
                if (checkbox) {
                    checkbox.checked = false;
                }

                // Remover del arreglo
                productosSeleccionados = productosSeleccionados.filter(
                    p => p.id !== productoId
                );

                // Remover del Set de IDs seleccionados
                selectedProductIds.delete(productoId);

                // Guardar estado
                saveSelectedProducts();

                // Actualizar vista
                actualizarSeleccionados();
            });
        });

        // Agregar eventos de control de cantidad
        document.querySelectorAll('.cantidad-producto').forEach((contenedor, index) => {
            const btnMenos = contenedor.querySelector('.btn-menos');
            const btnMas = contenedor.querySelector('.btn-mas');
            const inputCantidad = contenedor.querySelector('input');
            const producto = productosSeleccionados[index];

            btnMenos.addEventListener('click', () => {
                const valorActual = parseInt(inputCantidad.value);
                if (valorActual > 1) {
                    inputCantidad.value = valorActual - 1;
                    actualizarCantidadProducto(producto.id, valorActual - 1);
                }
            });

            btnMas.addEventListener('click', () => {
                const valorActual = parseInt(inputCantidad.value);
                const maxCantidad = parseInt(producto.cantidad);
                if (valorActual < maxCantidad) {
                    inputCantidad.value = valorActual + 1;
                    actualizarCantidadProducto(producto.id, valorActual + 1);
                }
            });

            inputCantidad.addEventListener('change', () => {
                const valorActual = parseInt(inputCantidad.value);
                const maxCantidad = parseInt(producto.cantidad);
                
                // Validar que no supere la cantidad máxima
                if (valorActual > maxCantidad) {
                    inputCantidad.value = maxCantidad;
                } else if (valorActual < 1) {
                    inputCantidad.value = 1;
                }
                
                actualizarCantidadProducto(producto.id, parseInt(inputCantidad.value));
            });
        });
    }

    // Función para actualizar cantidad de producto
    function actualizarCantidadProducto(productoId, nuevaCantidad) {
        const index = productosSeleccionados.findIndex(p => p.id === productoId);
        if (index !== -1) {
            productosSeleccionados[index].cantidadSeleccionada = nuevaCantidad;
            saveSelectedProducts();
            actualizarSeleccionados();
        }
    }

    // Limpiar selección
    btnLimpiarSeleccion.addEventListener('click', function() {
        // Desmarcar todos los checkboxes
        document.querySelectorAll('.producto-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });

        // Limpiar arreglo de productos
        productosSeleccionados = [];
        selectedProductIds.clear();

        // Guardar estado
        saveSelectedProducts();

        // Actualizar vista
        actualizarSeleccionados();
    });

    // Continuar entrega - abrir modal de entrega de uniformes
    btnContinuarEntrega.addEventListener('click', function() {
        // Mostrar productos seleccionados en el modal
        listaUniformesEntrega.innerHTML = productosSeleccionados.map(producto => `
            <div class="uniforme-entrega" data-id="${producto.id}">
                <input type="hidden" name="producto_id[]" value="${producto.id}">
                <input type="hidden" name="producto_cantidad[]" value="${producto.cantidadSeleccionada || 1}">
                <input type="hidden" name="producto_almacen[]" value="${producto.almacen}">
                <span>${producto.nombre}</span>
                <span>Cantidad: ${producto.cantidadSeleccionada || 1}</span>
            </div>
        `).join('');

        // Mostrar modal
        modalEntregaUniforme.style.display = 'block';
    });

    // Manejar envío del formulario de entrega de uniformes
    formEntregaUniforme.addEventListener('submit', function(e) {
        e.preventDefault(); // Prevenir envío tradicional del formulario
        
        // Obtener los datos del formulario
        const nombreDestinatario = document.getElementById('nombre_destinatario').value.trim();
        const dniDestinatario = document.getElementById('dni_destinatario').value.trim();
        
        // Validaciones básicas
        if (!nombreDestinatario || !dniDestinatario) {
            alert('Por favor complete todos los campos');
            return;
        }
        
        // Preparar los datos para enviar
        const formData = new FormData(this);
        
        fetch('/uniformes/procesar_entrega_uniforme.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mostrar mensaje de éxito
                alert(data.message);
                
                // Cerrar modal
                modalEntregaUniforme.style.display = 'none';
                
                // Limpiar estado de entrega
                limpiarEstadoEntrega();
                
                // Opcional: Actualizar vista o recargar página
                location.reload();
            } else {
                // Mostrar mensaje de error
                alert(data.error || 'Ocurrió un error al procesar la entrega');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Ocurrió un error al conectar con el servidor');
        });
    });

    // Botón de entregar uniforme en el formulario de búsqueda
    btnEntregarUniforme.addEventListener('click', function() {
        // Marcar sesión de entrega activa
        localStorage.setItem('entregaUniformeActive', 'true');
        
        // Primero restaurar la tabla a su estado original
        transformTableToSelectable();
        
        // Luego añadir checkboxes
        addCheckboxesToTable();
        
        // Mostrar zona de seleccionados
        zonaSeleccionados.style.display = 'block';
    });

    // Limpiar estado de entrega
    function limpiarEstadoEntrega() {
        localStorage.removeItem('entregaUniformeActive');
        localStorage.removeItem('productosSeleccionados');
        selectedProductIds.clear();
        productosSeleccionados = [];
        
        // Restaurar tabla a su estado original
        transformTableToSelectable();
        
        // Actualizar vista de seleccionados
        actualizarSeleccionados();

        // Eliminar botón de cancelar entrega si existe
        const cancelarEntregaButton = document.querySelector('.cancelar-entrega');
        if (cancelarEntregaButton) {
            cancelarEntregaButton.remove();
        }
    }

    // Cerrar modal de entrega de uniformes
    document.querySelectorAll('#modalEntregaUniforme .cerrar').forEach(btn => {
        btn.addEventListener('click', function() {
            modalEntregaUniforme.style.display = 'none';

            limpiarEstadoEntrega();
        });
    });

    // Evento para mostrar/ocultar zona de seleccionados
    btnToggleSeleccionados.addEventListener('click', function() {
        zonaSeleccionados.classList.toggle('activo');
    });

    // Llamar a restaurar estado al cargar
    if (btnEntregarUniforme) {
        restoreSelectionState();
    }

    // Ocultar zona de seleccionados por defecto
    zonaSeleccionados.style.display = 'block';
    btnToggleSeleccionados.style.display = 'none';
});