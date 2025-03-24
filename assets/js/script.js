document.addEventListener("DOMContentLoaded", function () {
    const submenus = document.querySelectorAll(".submenu-container .submenu");
    const submenuLinks = document.querySelectorAll(".submenu-container > a");
    const modal = document.getElementById("modalFormulario");
    const cerrarModalBtn = document.querySelector(".modal-contenido .cerrar");
    const botonesEnviar = document.querySelectorAll(".btn.enviar");
    const botonesEliminar = document.querySelectorAll(".btn-eliminar");
    const formEnviar = document.getElementById("formEnviar");
    const btnConfirmar = document.querySelector('.modal-footer .btn.enviar');
    const selectAlmacen = document.getElementById('almacen_destino');
    // Control del menú hamburguesa para móviles
    const menuToggle = document.getElementById("menuToggle");
    const sidebar = document.getElementById("sidebar");
    const mainContent = document.getElementById("main-content");
    
    if (menuToggle && sidebar && mainContent) {
        menuToggle.addEventListener("click", function() {
            sidebar.classList.toggle("active");
            mainContent.classList.toggle("with-sidebar");
            
            // Cambiar el icono del botón según el estado del menú
            if (sidebar.classList.contains("active")) {
                menuToggle.innerHTML = '<i class="fas fa-times"></i>'; // Icono de X para cerrar
            } else {
                menuToggle.innerHTML = '<i class="fas fa-bars"></i>'; // Icono de hamburguesa
            }
        });
    }
    // Desactivar el botón inicialmente
    if (btnConfirmar) {
        btnConfirmar.disabled = true;
        btnConfirmar.style.opacity = '0.6';
        btnConfirmar.style.cursor = 'not-allowed';
    }

    // Añadir un evento para cuando cambie la selección
    if (selectAlmacen) {
        selectAlmacen.addEventListener('change', function() {
            if (this.value) {
                btnConfirmar.disabled = false;
                btnConfirmar.style.opacity = '1';
                btnConfirmar.style.cursor = 'pointer';
            } else {
                btnConfirmar.disabled = true;
                btnConfirmar.style.opacity = '0.6';
                btnConfirmar.style.cursor = 'not-allowed';
            }
        });
    }
    
    // Configurar cierre automático de notificaciones
    const botonesCerrarNotificacion = document.querySelectorAll(".notificacion .cerrar");
    
    botonesCerrarNotificacion.forEach(boton => {
        boton.addEventListener("click", function() {
            const notificacion = this.parentElement;
            notificacion.classList.add("fade-out");
            setTimeout(() => {
                notificacion.remove();
            }, 500);
        });
    });
    
    // Auto-cerrar notificaciones después de 8 segundos
    document.querySelectorAll(".notificacion").forEach(notificacion => {
        setTimeout(() => {
            notificacion.classList.add("fade-out");
            setTimeout(() => {
                notificacion.remove();
            }, 500);
        }, 8000);
    });
    
    // Manejador de submenús
    submenuLinks.forEach(menu => {
        menu.addEventListener("click", function (event) {
            event.preventDefault();
            let submenu = this.nextElementSibling;
            if (submenu) {
                const isActive = submenu.classList.contains("activo");
                submenus.forEach(sub => sub.classList.remove("activo"));
                if (!isActive) submenu.classList.add("activo");
            }
        });
    });

    document.addEventListener("click", function (event) {
        if (!event.target.closest(".submenu-container")) {
            submenus.forEach(sub => sub.classList.remove("activo"));
        }
    });

    // Función para abrir el modal con datos de producto correctos
    function abrirModal(event) {
        const boton = event.currentTarget;
        const productoId = boton.getAttribute("data-id");
        const productoNombre = boton.getAttribute("data-nombre");
        const almacenId = boton.getAttribute("data-almacen");
        const stockDisponible = boton.getAttribute("data-cantidad");
        
        // Verificar que los datos existen antes de continuar
        if (!productoId || !productoNombre || !almacenId || !stockDisponible) {
            console.error("Datos faltantes para el producto:", { 
                id: productoId, 
                nombre: productoNombre, 
                almacen: almacenId, 
                stock: stockDisponible 
            });
            mostrarNotificacion("Error: Datos de producto incompletos", "error");
            return;
        }

        // Establecer los valores en el formulario
        document.getElementById("producto_id").value = productoId;
        document.getElementById("almacen_origen").value = almacenId;
        document.getElementById("producto_nombre").textContent = `Producto: ${productoNombre}`;
        document.getElementById("stock_disponible").textContent = stockDisponible;
        
        // Establecer el máximo para el campo de cantidad
        const inputCantidad = document.getElementById("cantidad");
        inputCantidad.setAttribute("max", stockDisponible);
        inputCantidad.value = "1";
        
        // Reiniciar el estado del botón y el select
        document.getElementById("almacen_destino").value = "";
        if (btnConfirmar) {
            btnConfirmar.disabled = true;
            btnConfirmar.style.opacity = '0.6';
            btnConfirmar.style.cursor = 'not-allowed';
        }

        // Mostrar el modal
        modal.style.display = "flex";
    }

    // Evento para cerrar el modal
    function cerrarModal() {
        modal.style.display = "none";
    }

    // Función para adjuntar evento a botones de enviar
    function adjuntarEventosEnviar() {
        document.querySelectorAll(".btn.enviar").forEach(boton => {
            // Solo los botones en la tabla de productos, no el del modal
            if (!boton.closest('.modal-footer') && !boton.hasAttribute('data-event-attached')) {
                boton.addEventListener("click", abrirModal);
                boton.setAttribute('data-event-attached', 'true');
            }
        });
    }

    // Inicialmente adjuntar eventos a los botones existentes
    adjuntarEventosEnviar();

    if (cerrarModalBtn) {
        cerrarModalBtn.addEventListener("click", cerrarModal);
    }

    window.addEventListener("click", function(event) {
        if (event.target === modal) cerrarModal();
    });

    // Validación de formulario antes de enviar
    if (formEnviar) {
        formEnviar.addEventListener("submit", function(event) {
            event.preventDefault(); // Evitar el envío tradicional del formulario
            
            const cantidad = parseInt(document.getElementById("cantidad").value);
            const stockDisponible = parseInt(document.getElementById("stock_disponible").textContent);
            const almacenDestino = document.getElementById("almacen_destino").value;
            const productoId = document.getElementById("producto_id").value;
            const almacenOrigen = document.getElementById("almacen_origen").value;
            
            // Verificar que todos los datos necesarios están presentes
            if (!productoId || !almacenOrigen) {
                mostrarNotificacion("Error: Datos del producto incompletos", "error");
                return;
            }

            if (cantidad <= 0) {
                mostrarNotificacion("La cantidad debe ser mayor a 0", "error");
            } else if (cantidad > stockDisponible) {
                mostrarNotificacion("No hay suficiente stock disponible", "error");
            } else if (!almacenDestino) {
                mostrarNotificacion("Debe seleccionar un almacén de destino", "error");
            } else {
                // Enviar mediante AJAX
                const formData = new FormData(formEnviar);
                
                // Mostrar indicador de carga o desactivar el botón
                const btnSubmit = formEnviar.querySelector('button[type="submit"]');
                const textoOriginal = btnSubmit.innerHTML;
                btnSubmit.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando...';
                btnSubmit.disabled = true;
                
                fetch("procesar_formulario.php", {
                    method: "POST",
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Cerrar el modal
                        modal.style.display = "none";
                        
                        // Mostrar notificación de éxito
                        mostrarNotificacion(data.message || "Producto enviado correctamente", "exito");
                        
                        // Actualizar la cantidad en la interfaz
                        const nuevaCantidad = stockDisponible - cantidad;
                        const spanCantidad = document.getElementById(`cantidad-${productoId}`);
                        if (spanCantidad) {
                            spanCantidad.textContent = nuevaCantidad;
                        }
                        
                        // Actualizar los data-cantidad en los botones
                        const botonesProducto = document.querySelectorAll(`.btn.enviar[data-id="${productoId}"]`);
                        botonesProducto.forEach(btn => {
                            btn.setAttribute('data-cantidad', nuevaCantidad);
                        });
                        
                        // Verificar si los botones deben ocultarse
                        actualizarBotonesProducto(productoId, nuevaCantidad);
                    } else {
                        mostrarNotificacion(data.message || "Error al procesar la solicitud", "error");
                    }
                })
                .catch(error => {
                    console.error("Error en la petición:", error);
                    mostrarNotificacion("Error de conexión", "error");
                })
                .finally(() => {
                    // Restaurar el botón
                    btnSubmit.innerHTML = textoOriginal;
                    btnSubmit.disabled = false;
                });
            }
        });
    }
    
    // Función para mostrar notificaciones dinámicas
    function mostrarNotificacion(mensaje, tipo = "info") {
        const contenedor = document.getElementById("notificaciones-container");
        if (!contenedor) {
            console.error("Contenedor de notificaciones no encontrado");
            return;
        }
        
        const notificacion = document.createElement("div");
        notificacion.className = `notificacion ${tipo}`;
        notificacion.innerHTML = mensaje + '<span class="cerrar">&times;</span>';
        
        contenedor.appendChild(notificacion);
        
        const botonCerrar = notificacion.querySelector(".cerrar");
        botonCerrar.addEventListener("click", function() {
            notificacion.classList.add("fade-out");
            setTimeout(() => {
                notificacion.remove();
            }, 500);
        });
        
        // Auto-cerrar después de 8 segundos
        setTimeout(() => {
            notificacion.classList.add("fade-out");
            setTimeout(() => {
                notificacion.remove();
            }, 500);
        }, 8000);
    }

    // Manejo de eliminación de usuarios
    if (botonesEliminar.length > 0) {
        botonesEliminar.forEach(button => {
            button.addEventListener("click", function () {
                const id = this.getAttribute("data-id");
                if (!id) {
                    console.error("ID no encontrado");
                    return;
                }
                if (!confirm("¿Seguro que deseas eliminar este usuario?")) return;

                fetch("listar.php", {
                    method: "POST",
                    headers: { "Content-Type": "application/x-www-form-urlencoded" },
                    body: new URLSearchParams({ id: id })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        mostrarNotificacion("✅ Usuario eliminado correctamente", "exito");
                        location.reload();
                    } else {
                        mostrarNotificacion("❌ Error: " + data.message, "error");
                    }
                })
                .catch(error => {
                    console.error("❌ Error en fetch:", error);
                    mostrarNotificacion("❌ Hubo un problema con la solicitud", "error");
                });
            });
        });
    }

    // Función para actualizar la visualización de los botones según la cantidad
    function actualizarBotonesProducto(productoId, cantidad) {
        const filaProd = document.querySelector(`span#cantidad-${productoId}`).closest('tr');
        const celdaAcciones = filaProd.querySelector('td:last-child');
        
        // Si la cantidad cambia de 0 a positivo, mostrar los botones
        if (cantidad > 0) {
            // Verificar si los botones ya existen
            if (!celdaAcciones.querySelector('.btn.enviar')) {
                // Crear botón Enviar si no existe
                const btnEnviar = document.createElement('button');
                btnEnviar.className = 'btn enviar';
                btnEnviar.setAttribute('data-id', productoId);
                btnEnviar.setAttribute('data-nombre', filaProd.querySelector('td:first-child').textContent);
                btnEnviar.setAttribute('data-almacen', document.body.getAttribute('data-almacen-id'));
                btnEnviar.setAttribute('data-cantidad', cantidad);
                btnEnviar.innerHTML = '<i class="fas fa-paper-plane"></i> Enviar';
                
                // Crear botón Entregar si no existe
                const btnEntregar = document.createElement('button');
                btnEntregar.className = 'btn entregar';
                btnEntregar.innerHTML = '<i class="fas fa-truck"></i> Entregar';
                
                // Insertar al inicio de la celda
                celdaAcciones.insertBefore(btnEntregar, celdaAcciones.firstChild);
                celdaAcciones.insertBefore(btnEnviar, celdaAcciones.firstChild);
                
                // Adjuntar eventos a los nuevos botones
                adjuntarEventosEnviar();
            } else {
                // Actualizar atributo de cantidad
                celdaAcciones.querySelector('.btn.enviar').setAttribute('data-cantidad', cantidad);
            }
        } else if (cantidad <= 0) {
            // Si la cantidad es 0 o menos, quitar los botones de Enviar y Entregar
            const btnEnviar = celdaAcciones.querySelector('.btn.enviar');
            const btnEntregar = celdaAcciones.querySelector('.btn.entregar');
            
            if (btnEnviar) btnEnviar.remove();
            if (btnEntregar) btnEntregar.remove();
        }
    }

    // Manejo de actualización de stock
    const botonesStock = document.querySelectorAll('.btn.stock');
    
    if (botonesStock.length > 0) {
        botonesStock.forEach(boton => {
            boton.addEventListener('click', function() {
                const productoId = this.getAttribute('data-id');
                const accion = this.getAttribute('data-accion');
                const almacenId = document.body.getAttribute('data-almacen-id');
                
                if (!productoId || !accion || !almacenId) {
                    console.error("Datos faltantes para actualizar stock:", { productoId, accion, almacenId });
                    mostrarNotificacion("Error al actualizar stock: datos incompletos", "error");
                    return;
                }
                
                const spanCantidad = document.getElementById(`cantidad-${productoId}`);
                if (!spanCantidad) {
                    console.error("Elemento de cantidad no encontrado");
                    return;
                }
                
                const cantidadOriginal = parseInt(spanCantidad.textContent);
                spanCantidad.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                
                const formData = new FormData();
                formData.append('producto_id', productoId);
                formData.append('accion', accion);
                formData.append('almacen_id', almacenId);
                
                fetch('actualizar_cantidad.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const nuevaCantidad = parseInt(data.nueva_cantidad);
                        spanCantidad.textContent = nuevaCantidad;
                        mostrarNotificacion('Cantidad actualizada correctamente', 'exito');
                        
                        // Actualizar el data-cantidad para los botones relacionados con este producto
                        const botonesProducto = document.querySelectorAll(`.btn.enviar[data-id="${productoId}"]`);
                        botonesProducto.forEach(btn => {
                            btn.setAttribute('data-cantidad', nuevaCantidad);
                        });
                        
                        // Actualizar visualización de botones según la nueva cantidad
                        // Si antes era 0 y ahora es > 0, mostrar botones
                        // Si antes era > 0 y ahora es 0, ocultar botones
                        if ((cantidadOriginal <= 0 && nuevaCantidad > 0) || 
                            (cantidadOriginal > 0 && nuevaCantidad <= 0)) {
                            actualizarBotonesProducto(productoId, nuevaCantidad);
                        }
                    } else {
                        spanCantidad.textContent = cantidadOriginal;
                        mostrarNotificacion(data.message || 'Error al actualizar la cantidad', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    spanCantidad.textContent = cantidadOriginal;
                    mostrarNotificacion('Error de conexión', 'error');
                });
            });
        });
    }

    // Script para pendientes.php - Cerrar las notificaciones
    const btnCerrarNotificaciones = document.querySelectorAll('.notificacion .cerrar');
    
    btnCerrarNotificaciones.forEach(function(btn) {
        btn.addEventListener('click', function() {
            const notificacion = this.parentElement;
            notificacion.style.opacity = '0';
            setTimeout(function() {
                notificacion.style.display = 'none';
            }, 300);
        });
    });
    
    // Auto-cerrar notificaciones después de 5 segundos
    setTimeout(function() {
        const notificaciones = document.querySelectorAll('.notificacion');
        notificaciones.forEach(function(notificacion) {
            notificacion.style.opacity = '0';
            setTimeout(function() {
                notificacion.style.display = 'none';
            }, 300);
        });
    }, 5000);
});