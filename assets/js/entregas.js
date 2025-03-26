document.addEventListener("DOMContentLoaded", function () {
    const btnEntregar = document.querySelector('.btn.entregar');
    const tableContainer = document.querySelector('.table-container');
    const zonaSeleccionados = document.getElementById('zona-seleccionados');
    const btnLimpiarSeleccion = document.getElementById('btn-limpiar-seleccion');
    const contadorSeleccionados = document.getElementById('contador-seleccionados');
    const listaSeleccionados = document.getElementById('lista-seleccionados');
    const btnContinuarEntrega = document.getElementById('btn-continuar-entrega');
    
    // Modal de lista de uniformes
    const modalListaUniformes = document.getElementById('modalListaUniformes');
    const btnSeguirAgregando = document.getElementById('btn-seguir-agregando');
    const btnCompletarEntrega = document.getElementById('btn-completar-entrega');
    const btnLimpiarUniformes = document.getElementById('btn-limpiar-uniformes');
    const listaUniformes = document.getElementById('lista-uniformes');
    const contadorUniformes = document.getElementById('contador-uniformes');

    // Crear el carrito con contador (inicialmente oculto)
    const carritoContainer = document.createElement('div');
    carritoContainer.classList.add('carrito-container');
    carritoContainer.style.display = 'none'; // Inicialmente oculto
    carritoContainer.innerHTML = `
        <div class="carrito-icon">
            <i class="fas fa-shopping-cart"></i>
            <span class="carrito-contador">0</span>
        </div>
    `;

    // Insertar el carrito junto al botón de entregar
    const busquedaForm = document.querySelector('.busqueda form');
    busquedaForm.appendChild(carritoContainer);
    
    const carritoIcon = carritoContainer.querySelector('.carrito-icon');
    const carritoContador = carritoContainer.querySelector('.carrito-contador');
    
    let enModoSeleccion = false;
    let productosSeleccionados = [];
    let uniformesParaEntregar = [];
    let botonesOriginales = [];  

    // Función para activar el modo de selección
    function activarModoSeleccion() {
        if (enModoSeleccion) return;
        
        enModoSeleccion = true;
        btnEntregar.textContent = 'Cancelar Selección';
        btnEntregar.classList.add('cancelar');
        
        // Mostrar carrito cuando se activa el modo de selección
        carritoContainer.style.display = 'block';
        
        // Modificar las filas de la tabla
        const filas = document.querySelectorAll('.table-container table tbody tr');
        filas.forEach(fila => {
            const celdaAcciones = fila.querySelector('td:last-child');
            
            // Guardar y ocultar botones originales
            const botonesOriginalesCelda = Array.from(celdaAcciones.querySelectorAll('.btn'));
            botonesOriginales.push({
                celda: celdaAcciones,
                botones: botonesOriginalesCelda
            });
            
            botonesOriginalesCelda.forEach(boton => {
                boton.style.display = 'none';
            });
            
            const btnEnviar = celdaAcciones.querySelector('.btn.enviar');
            
            if (btnEnviar) {
                const productoId = btnEnviar.getAttribute('data-id');
                const productoNombre = btnEnviar.getAttribute('data-nombre');
                const productoMaxCantidad = parseInt(btnEnviar.getAttribute('data-cantidad'));
                
                // Agregar checkbox para selección
                const checkboxContainer = document.createElement('div');
                checkboxContainer.classList.add('checkbox-container');
                
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.classList.add('seleccion-producto');
                checkbox.setAttribute('data-id', productoId);
                checkbox.setAttribute('data-nombre', productoNombre);
                checkbox.setAttribute('data-max-cantidad', productoMaxCantidad);
                checkbox.setAttribute('data-cantidad', 1); // Siempre 1 al inicio
                
                checkbox.addEventListener('change', manejarSeleccionProducto);
                
                checkboxContainer.appendChild(checkbox);
                celdaAcciones.appendChild(checkboxContainer);
            }
        });
    }

    // Función para desactivar el modo de selección
    function desactivarModoSeleccion() {
        enModoSeleccion = false;
        btnEntregar.textContent = 'Entregar';
        btnEntregar.classList.remove('cancelar');
        
        // Ocultar carrito y zona de seleccionados
        carritoContainer.style.display = 'none';
        zonaSeleccionados.style.display = 'none';
        
        // Limpiar productos seleccionados
        productosSeleccionados = [];
        actualizarContadorSeleccionados();
        
        // Restaurar botones originales
        botonesOriginales.forEach(item => {
            item.botones.forEach(boton => {
                boton.style.display = '';
            });
            
            // Eliminar checkboxes
            const checkboxContainer = item.celda.querySelector('.checkbox-container');
            if (checkboxContainer) {
                checkboxContainer.remove();
            }
        });
        
        // Limpiar el array de botones originales
        botonesOriginales = [];
    }

    // Función para manejar la selección de productos
    function manejarSeleccionProducto(event) {
        const checkbox = event.target;
        const productoId = checkbox.getAttribute('data-id');
        const productoNombre = checkbox.getAttribute('data-nombre');
        const maxCantidad = parseInt(checkbox.getAttribute('data-max-cantidad'));
        
        if (checkbox.checked) {
            // Verificar si el producto ya está seleccionado
            const productoExistente = productosSeleccionados.find(p => p.id === productoId);
            
            if (!productoExistente) {
                // Añadir producto (siempre con cantidad 1)
                const producto = {
                    id: productoId,
                    nombre: productoNombre,
                    cantidad: 1,
                    maxCantidad: maxCantidad
                };
                productosSeleccionados.push(producto);
            }
        } else {
            // Eliminar producto
            const index = productosSeleccionados.findIndex(p => p.id === productoId);
            if (index !== -1) {
                productosSeleccionados.splice(index, 1);
            }
        }
        
        actualizarContadorSeleccionados();
    }

    // Función para actualizar el contador de productos seleccionados
    function actualizarContadorSeleccionados() {
        const contador = productosSeleccionados.length;
        carritoContador.textContent = contador;
        contadorSeleccionados.textContent = contador;
        
        // Si no hay productos, ocultar la zona de seleccionados
        if (contador === 0) {
            zonaSeleccionados.style.display = 'none';
        }
    }

    // Función para actualizar la lista de productos seleccionados
    function actualizarListaSeleccionados() {
        listaSeleccionados.innerHTML = productosSeleccionados.map(producto => `
            <div class="producto-seleccionado" data-id="${producto.id}">
                <div class="producto-info">
                    ${producto.nombre}
                </div>
                <div class="cantidad-control">
                    <button class="btn-cantidad restar" data-id="${producto.id}">-</button>
                    <span class="cantidad">${producto.cantidad}</span>
                    <button class="btn-cantidad sumar" data-id="${producto.id}">+</button>
                </div>
            </div>
        `).join('');

        // Agregar eventos a los botones de cantidad
        document.querySelectorAll('.btn-cantidad').forEach(btn => {
            btn.addEventListener('click', manejarCambiarCantidad);
        });
    }

    // Función para manejar cambios de cantidad
    function manejarCambiarCantidad(event) {
        const btn = event.target;
        const productoId = btn.getAttribute('data-id');
        const producto = productosSeleccionados.find(p => p.id === productoId);
        
        if (btn.classList.contains('sumar')) {
            // Incrementar cantidad si no supera el máximo
            if (producto.cantidad < producto.maxCantidad) {
                producto.cantidad++;
            }
        } else if (btn.classList.contains('restar')) {
            // Decrementar cantidad si es mayor a 1
            if (producto.cantidad > 1) {
                producto.cantidad--;
            }
        }

        // Actualizar visualización
        actualizarListaSeleccionados();
    }

    // Nueva función para agregar uniformes
    function agregarUniformeALista(producto) {
        const uniformeExistente = uniformesParaEntregar.find(u => u.id === producto.id);
        
        if (!uniformeExistente) {
            uniformesParaEntregar.push({
                id: producto.id,
                nombre: producto.nombre,
                cantidad: producto.cantidad
            });
        }
        
        actualizarListaUniformes();
    }

    // Función para actualizar la lista de uniformes
    function actualizarListaUniformes() {
        listaUniformes.innerHTML = uniformesParaEntregar.map(uniforme => `
            <div class="producto-seleccionado" data-id="${uniforme.id}">
                <div class="producto-info">${uniforme.nombre}</div>
                <div class="cantidad-control">
                    <button class="btn-cantidad restar-uniforme" data-id="${uniforme.id}">-</button>
                    <span class="cantidad">${uniforme.cantidad}</span>
                    <button class="btn-cantidad sumar-uniforme" data-id="${uniforme.id}">+</button>
                </div>
            </div>
        `).join('');

        contadorUniformes.textContent = uniformesParaEntregar.length;

        // Agregar eventos a botones de cantidad
        document.querySelectorAll('.restar-uniforme, .sumar-uniforme').forEach(btn => {
            btn.addEventListener('click', manejarCantidadUniforme);
        });
    }

    // Función para manejar cambios de cantidad en uniformes
    function manejarCantidadUniforme(event) {
        const btn = event.target;
        const uniformeId = btn.getAttribute('data-id');
        const uniforme = uniformesParaEntregar.find(u => u.id === uniformeId);
        
        if (btn.classList.contains('sumar-uniforme')) {
            uniforme.cantidad++;
        } else if (btn.classList.contains('restar-uniforme')) {
            uniforme.cantidad = Math.max(1, uniforme.cantidad - 1);
        }

        actualizarListaUniformes();
    }

    // Eventos para el botón de entregar y carrito
    btnEntregar.addEventListener('click', function() {
        if (!enModoSeleccion) {
            activarModoSeleccion();
        } else {
            desactivarModoSeleccion();
        }
    });

    carritoIcon.addEventListener('click', function() {
        if (productosSeleccionados.length > 0) {
            actualizarListaSeleccionados();
            zonaSeleccionados.style.display = 'flex';
        } else {
            alert('No hay productos seleccionados');
        }
    });

    // Evento para limpiar selección
    btnLimpiarSeleccion.addEventListener('click', function() {
        // Desmarcar todos los checkboxes
        const checkboxes = document.querySelectorAll('.seleccion-producto');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        
        productosSeleccionados = [];
        actualizarContadorSeleccionados();
        zonaSeleccionados.style.display = 'none';
    });

    // Evento para continuar entrega
    btnContinuarEntrega.addEventListener('click', function() {
        if (productosSeleccionados.length === 0) {
            alert('Selecciona al menos un producto');
            return;
        }
        
        // Agregar productos seleccionados a uniformes
        productosSeleccionados.forEach(agregarUniformeALista);
        
        // Mostrar modal de uniformes
        modalListaUniformes.style.display = 'flex';
        zonaSeleccionados.style.display = 'none';
    });

    // Eventos para modal de uniformes
    btnSeguirAgregando.addEventListener('click', function() {
        modalListaUniformes.style.display = 'none';
        // Volver al modo de selección
        activarModoSeleccion();
    });

    btnCompletarEntrega.addEventListener('click', function() {
        // Aquí iría la lógica para procesar la entrega de uniformes
        console.log('Uniformes a entregar:', uniformesParaEntregar);
        
        // Enviar datos al servidor (código pendiente)
        // fetch('/procesar_entrega_uniformes.php', {
        //     method: 'POST',
        //     body: JSON.stringify(uniformesParaEntregar)
        // });

        // Resetear todo
        modalListaUniformes.style.display = 'none';
        uniformesParaEntregar = [];
        productosSeleccionados = [];
        desactivarModoSeleccion();
    });

    btnLimpiarUniformes.addEventListener('click', function() {
        uniformesParaEntregar = [];
        actualizarListaUniformes();
    });
});