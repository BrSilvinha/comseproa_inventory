document.addEventListener('DOMContentLoaded', function() {
    const formFiltros = document.getElementById('formulario-filtros');
    const tablaHistorial = document.getElementById('tabla-historial-entregas');
    const contenedorHistorial = document.getElementById('contenedor-historial');

    // Función para cargar historial de entregas
    function cargarHistorialEntregas(filtros = {}) {
        // Construir URL de consulta
        const params = new URLSearchParams(filtros);
        
        fetch(`/uniformes/historial_entregas_uniformes.php?${params}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mostrarHistorial(data.data);
                } else {
                    mostrarMensajeError(data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                mostrarMensajeError('No se pudo cargar el historial de entregas');
            });
    }

    // Función para mostrar historial en la tabla
    function mostrarHistorial(entregas) {
        // Limpiar tabla existente
        tablaHistorial.innerHTML = `
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Destinatario</th>
                    <th>DNI</th>
                    <th>Almacén</th>
                    <th>Productos Entregados</th>
                </tr>
            </thead>
            <tbody>
                ${entregas.map(entrega => `
                    <tr>
                        <td>${formatearFecha(entrega.fecha_entrega)}</td>
                        <td>${entrega.nombre_destinatario}</td>
                        <td>${entrega.dni_destinatario}</td>
                        <td>${entrega.almacen_nombre}</td>
                        <td>
                            <ul>
                                ${entrega.productos.map(producto => 
                                    `<li>${producto.nombre} (Cantidad: ${producto.cantidad})</li>`
                                ).join('')}
                            </ul>
                        </td>
                    </tr>
                `).join('')}
            </tbody>
        `;
    }

    // Función para formatear fecha
    function formatearFecha(fechaString) {
        const fecha = new Date(fechaString);
        return fecha.toLocaleDateString('es-PE', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric'
        });
    }

    // Función para mostrar mensaje de error
    function mostrarMensajeError(mensaje) {
        contenedorHistorial.innerHTML = `
            <div class="alert alert-danger">
                ${mensaje}
            </div>
        `;
    }

    // Manejar envío de formulario de filtros
    formFiltros.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const filtros = {
            dni: document.getElementById('filtro-dni').value,
            fecha_inicio: document.getElementById('filtro-fecha-inicio').value,
            fecha_fin: document.getElementById('filtro-fecha-fin').value
        };

        cargarHistorialEntregas(filtros);
    });

    // Cargar historial inicial
    cargarHistorialEntregas();
});