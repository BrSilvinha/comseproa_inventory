/* ============================================
   PRODUCTOS REGISTRAR - JAVASCRIPT ESPECÍFICO
   ============================================ */

class ProductosRegistrar {
    constructor() {
        this.form = null;
        this.camposPorCategoria = {
            1: ["nombre", "modelo", "color", "talla_dimensiones", "cantidad", "unidad_medida", "estado", "observaciones"],
            2: ["nombre", "modelo", "color", "cantidad", "unidad_medida", "estado", "observaciones"],
            3: ["nombre", "color", "talla_dimensiones", "cantidad", "unidad_medida", "estado", "observaciones"],
            4: ["nombre", "modelo", "color", "cantidad", "unidad_medida", "estado", "observaciones"],
            6: ["nombre", "modelo", "color", "cantidad", "unidad_medida", "estado", "observaciones"]
        };
        this.validacionEnTiempoReal = true;
        
        this.inicializar();
    }

    inicializar() {
        // Configurar sidebar
        this.configurarSidebar();
        
        // Configurar formulario
        this.configurarFormulario();
        
        // Auto-cerrar alertas
        this.configurarAlertas();
        
        // Configurar atajos de teclado
        this.configurarAtajos();
        
        // Configurar validación en tiempo real
        this.configurarValidacion();
        
        // Verificar si hay categoría preseleccionada
        this.verificarCategoriaPreseleccionada();
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

    configurarFormulario() {
        this.form = document.getElementById('formRegistrarProducto');
        const categoriaSelect = document.getElementById('categoria_id');
        const productFields = document.getElementById('productFields');

        if (!this.form || !categoriaSelect || !productFields) {
            console.error('Elementos del formulario no encontrados');
            return;
        }

        // Evento para cambio de categoría
        categoriaSelect.addEventListener('change', (e) => {
            const categoriaId = parseInt(e.target.value);
            this.mostrarCamposPorCategoria(categoriaId);
        });

        // Evento para envío del formulario
        this.form.addEventListener('submit', (e) => {
            this.manejarEnvioFormulario(e);
        });

        // Eventos para campos dinámicos
        this.configurarEventosCampos();
    }

    verificarCategoriaPreseleccionada() {
        const categoriaSelect = document.getElementById('categoria_id');
        if (categoriaSelect && categoriaSelect.value) {
            const categoriaId = parseInt(categoriaSelect.value);
            this.mostrarCamposPorCategoria(categoriaId);
        }
    }

    mostrarCamposPorCategoria(categoriaId) {
        const productFields = document.getElementById('productFields');
        const allFields = productFields.querySelectorAll('[data-field]');
        
        // Ocultar todos los campos primero
        allFields.forEach(field => {
            field.style.display = 'none';
            const input = field.querySelector('input, select, textarea');
            if (input) {
                input.removeAttribute('required');
            }
        });

        if (categoriaId && this.camposPorCategoria[categoriaId]) {
            // Mostrar campos específicos de la categoría
            const camposVisibles = this.camposPorCategoria[categoriaId];
            
            camposVisibles.forEach(campo => {
                const fieldElement = productFields.querySelector(`[data-field="${campo}"]`);
                if (fieldElement) {
                    fieldElement.style.display = 'flex';
                    
                    // Configurar campos requeridos
                    const input = fieldElement.querySelector('input, select, textarea');
                    if (input && this.esCampoRequerido(campo)) {
                        input.setAttribute('required', 'true');
                    }
                }
            });

            // Mostrar la sección de campos
            productFields.style.display = 'block';
            
            // Animar la aparición
            productFields.style.opacity = '0';
            productFields.style.transform = 'translateY(20px)';
            
            setTimeout(() => {
                productFields.style.transition = 'all 0.4s ease';
                productFields.style.opacity = '1';
                productFields.style.transform = 'translateY(0)';
            }, 10);

            // Focus en el primer campo visible
            setTimeout(() => {
                const primerCampo = productFields.querySelector('input:not([type="hidden"]), select, textarea');
                if (primerCampo) {
                    primerCampo.focus();
                }
            }, 100);

        } else {
            // Ocultar la sección si no hay categoría seleccionada
            productFields.style.display = 'none';
        }
    }

    esCampoRequerido(campo) {
        const camposRequeridos = ['nombre', 'cantidad', 'unidad_medida', 'estado'];
        return camposRequeridos.includes(campo);
    }

    configurarEventosCampos() {
        // Configurar validación en tiempo real para todos los campos
        const inputs = this.form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            // Evento input para validación en tiempo real
            input.addEventListener('input', () => {
                if (this.validacionEnTiempoReal) {
                    this.validarCampo(input);
                }
            });

            // Evento blur para validación al salir del campo
            input.addEventListener('blur', () => {
                this.validarCampo(input);
            });

            // Evento focus para limpiar errores
            input.addEventListener('focus', () => {
                this.limpiarErroresCampo(input);
            });
        });

        // Configurar eventos específicos
        this.configurarCampoNumerico();
        this.configurarAutocompletado();
    }

    configurarCampoNumerico() {
        const cantidadInput = document.getElementById('cantidad');
        
        if (cantidadInput) {
            // Permitir solo números
            cantidadInput.addEventListener('keypress', (e) => {
                if (!/\d/.test(e.key) && !['Backspace', 'Delete', 'Tab', 'Enter', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
                    e.preventDefault();
                }
            });

            // Validar rango
            cantidadInput.addEventListener('input', (e) => {
                const value = parseInt(e.target.value);
                if (value < 1) {
                    e.target.value = 1;
                }
                if (value > 99999) {
                    e.target.value = 99999;
                }
            });
        }
    }

    configurarAutocompletado() {
        // Configurar autocompletado para unidades de medida comunes
        const unidadInput = document.getElementById('unidad_medida');
        
        if (unidadInput && unidadInput.tagName === 'SELECT') {
            // Ya es un select, no necesita autocompletado
            return;
        }

        // Si fuera un input text, aquí se configuraría el autocompletado
    }

    validarCampo(input) {
        const formGroup = input.closest('.form-group');
        const isRequired = input.hasAttribute('required');
        const value = input.value.trim();

        // Limpiar errores previos
        this.limpiarErroresCampo(input);

        let esValido = true;
        let mensajeError = '';

        // Validaciones específicas por tipo de campo
        if (isRequired && !value) {
            esValido = false;
            mensajeError = 'Este campo es obligatorio';
        } else if (input.type === 'number') {
            const numValue = parseInt(value);
            if (value && (isNaN(numValue) || numValue < 1)) {
                esValido = false;
                mensajeError = 'Debe ser un número mayor a 0';
            }
        } else if (input.id === 'nombre' && value) {
            if (value.length < 3) {
                esValido = false;
                mensajeError = 'El nombre debe tener al menos 3 caracteres';
            } else if (value.length > 100) {
                esValido = false;
                mensajeError = 'El nombre no puede exceder 100 caracteres';
            }
        }

        // Aplicar clases de validación
        if (esValido) {
            formGroup.classList.remove('has-error');
            formGroup.classList.add('has-success');
            input.style.borderColor = '';
        } else {
            formGroup.classList.remove('has-success');
            formGroup.classList.add('has-error');
            this.mostrarErrorCampo(input, mensajeError);
        }

        // Actualizar estado del botón de envío
        this.actualizarEstadoBotonEnvio();

        return esValido;
    }

    limpiarErroresCampo(input) {
        const formGroup = input.closest('.form-group');
        formGroup.classList.remove('has-error', 'has-success');
        
        const errorMessage = formGroup.querySelector('.error-message');
        if (errorMessage) {
            errorMessage.remove();
        }
        
        input.style.borderColor = '';
    }

    mostrarErrorCampo(input, mensaje) {
        const formGroup = input.closest('.form-group');
        
        // Remover mensaje de error anterior
        const errorAnterior = formGroup.querySelector('.error-message');
        if (errorAnterior) {
            errorAnterior.remove();
        }
        
        // Crear nuevo mensaje de error
        const errorElement = document.createElement('div');
        errorElement.className = 'error-message';
        errorElement.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${mensaje}`;
        
        formGroup.appendChild(errorElement);
    }

    actualizarEstadoBotonEnvio() {
        const btnEnvio = document.getElementById('btnRegistrar');
        const camposRequeridos = this.form.querySelectorAll('[required]');
        let todosValidos = true;

        camposRequeridos.forEach(campo => {
            if (!campo.value.trim() || campo.closest('.form-group').classList.contains('has-error')) {
                todosValidos = false;
            }
        });

        if (btnEnvio) {
            btnEnvio.disabled = !todosValidos;
            if (todosValidos) {
                btnEnvio.style.opacity = '1';
            } else {
                btnEnvio.style.opacity = '0.6';
            }
        }
    }

    async manejarEnvioFormulario(e) {
        e.preventDefault();
        
        const btnEnvio = document.getElementById('btnRegistrar');
        const textoOriginal = btnEnvio.innerHTML;
        
        // Validar formulario completo
        if (!this.validarFormularioCompleto()) {
            this.mostrarNotificacion('Por favor, corrija los errores en el formulario', 'error');
            return;
        }

        // Confirmar envío
        const confirmado = await this.confirmarRegistro();
        if (!confirmado) return;

        // Mostrar estado de carga
        btnEnvio.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Registrando...</span>';
        btnEnvio.disabled = true;
        this.form.classList.add('loading');

        try {
            // Enviar formulario
            this.form.submit();
        } catch (error) {
            console.error('Error al enviar formulario:', error);
            this.mostrarNotificacion('Error al registrar el producto', 'error');
            
            // Restaurar botón
            btnEnvio.innerHTML = textoOriginal;
            btnEnvio.disabled = false;
            this.form.classList.remove('loading');
        }
    }

    validarFormularioCompleto() {
        const inputs = this.form.querySelectorAll('input[required], select[required], textarea[required]');
        let esValido = true;

        inputs.forEach(input => {
            if (!this.validarCampo(input)) {
                esValido = false;
            }
        });

        return esValido;
    }

    async confirmarRegistro() {
        const nombre = document.getElementById('nombre').value;
        const cantidad = document.getElementById('cantidad').value;
        const almacenSelect = document.getElementById('almacen_id');
        const almacenNombre = almacenSelect.options[almacenSelect.selectedIndex].text;
        
        return await this.confirmarAccion(
            `¿Desea registrar el producto "${nombre}" con cantidad ${cantidad} en el almacén "${almacenNombre}"?`,
            'Confirmar Registro',
            'info'
        );
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

    configurarAtajos() {
        document.addEventListener('keydown', (e) => {
            // Ctrl + S para guardar
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                document.getElementById('btnRegistrar').click();
            }
            
            // Ctrl + R para limpiar formulario
            if (e.ctrlKey && e.key === 'r') {
                e.preventDefault();
                this.limpiarFormulario();
            }
            
            // Esc para cancelar
            if (e.key === 'Escape') {
                window.location.href = 'listar.php';
            }
        });
    }

    configurarValidacion() {
        // Deshabilitar validación HTML5 nativa
        this.form.setAttribute('novalidate', 'true');
    }

    limpiarFormulario() {
        if (confirm('¿Está seguro que desea limpiar el formulario? Se perderán todos los datos ingresados.')) {
            // Resetear formulario
            this.form.reset();
            
            // Limpiar errores y clases de validación
            const formGroups = this.form.querySelectorAll('.form-group');
            formGroups.forEach(group => {
                group.classList.remove('has-error', 'has-success');
                const errorMessage = group.querySelector('.error-message');
                if (errorMessage) {
                    errorMessage.remove();
                }
            });
            
            // Ocultar campos de producto
            const productFields = document.getElementById('productFields');
            productFields.style.display = 'none';
            
            // Restaurar estado del botón
            const btnEnvio = document.getElementById('btnRegistrar');
            btnEnvio.disabled = true;
            btnEnvio.style.opacity = '0.6';
            
            // Focus en primer campo
            document.getElementById('almacen_id').focus();
            
            this.mostrarNotificacion('Formulario limpiado correctamente', 'info');
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

    async confirmarAccion(mensaje, titulo = 'Confirmar', tipo = 'info') {
        return new Promise((resolve) => {
            // Crear modal de confirmación dinámico
            const modalHtml = `
                <div class="modal-overlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 3000; display: flex; align-items: center; justify-content: center;">
                    <div class="modal-content" style="background: white; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.15); max-width: 450px; width: 90%; animation: slideInUp 0.3s ease;">
                        <div class="modal-header" style="background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); color: white; padding: 20px 25px; border-radius: 12px 12px 0 0;">
                            <h2 style="margin: 0; font-size: 18px; display: flex; align-items: center; gap: 10px;">
                                <i class="fas fa-question-circle"></i>
                                ${titulo}
                            </h2>
                        </div>
                        <div class="modal-body" style="padding: 25px;">
                            <p style="margin: 0; text-align: center; font-size: 16px; line-height: 1.5; color: var(--text-primary);">${mensaje}</p>
                        </div>
                        <div class="modal-footer" style="padding: 20px 25px; background: var(--light-color); border-radius: 0 0 12px 12px; display: flex; gap: 12px; justify-content: flex-end;">
                            <button class="btn-cancel" style="padding: 12px 20px; border: 1px solid var(--border-color); background: white; color: var(--text-primary); border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 500; transition: all 0.3s ease;">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                            <button class="btn-confirm" style="padding: 12px 20px; border: none; background: var(--accent-color); color: white; border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 500; transition: all 0.3s ease;">
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

            const btnConfirmar = confirmModal.querySelector('.btn-confirm');
            const btnCancelar = confirmModal.querySelector('.btn-cancel');

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

            // Efectos hover para botones
            btnCancelar.addEventListener('mouseenter', () => {
                btnCancelar.style.background = '#e9ecef';
            });
            btnCancelar.addEventListener('mouseleave', () => {
                btnCancelar.style.background = 'white';
            });

            btnConfirmar.addEventListener('mouseenter', () => {
                btnConfirmar.style.background = '#0056b3';
                btnConfirmar.style.transform = 'translateY(-2px)';
            });
            btnConfirmar.addEventListener('mouseleave', () => {
                btnConfirmar.style.background = 'var(--accent-color)';
                btnConfirmar.style.transform = 'translateY(0)';
            });
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
function limpiarFormulario() {
    window.productosRegistrar.limpiarFormulario();
}

function manejarCerrarSesion(event) {
    window.productosRegistrar.manejarCerrarSesion(event);
}

// Estilos CSS adicionales dinámicos
const additionalStyles = `
    @keyframes slideOutUp {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-20px); }
    }
    
    @keyframes slideOutRight {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(100%); }
    }
    
    @keyframes slideInUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .form-group[data-field] {
        transition: all 0.3s ease;
    }
    
    .form-group[data-field][style*="display: none"] {
        opacity: 0;
        transform: translateY(-10px);
    }
    
    .form-group[data-field]:not([style*="display: none"]) {
        opacity: 1;
        transform: translateY(0);
    }
`;

// Inyectar estilos adicionales
const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', () => {
    window.productosRegistrar = new ProductosRegistrar();
    console.log('Productos Registrar inicializado correctamente');
});