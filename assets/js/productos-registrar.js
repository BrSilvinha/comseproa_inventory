/* ============================================
   PRODUCTOS REGISTRAR - JAVASCRIPT ESPECÍFICO
   ============================================ */

class ProductosRegistrar {
    constructor() {
        this.formContainer = document.querySelector('.form-container');
        this.form = document.getElementById('formRegistrarProducto');
        this.hasChanges = false;
        this.validationRules = this.initValidationRules();
        
        this.inicializar();
        this.configurarEventListeners();
        this.configurarValidaciones();
    }

    inicializar() {
        // Configurar sidebar
        this.configurarSidebar();
        
        // Auto-cerrar alertas
        this.configurarAlertas();
        
        // Configurar contador de caracteres
        this.configurarContadorCaracteres();
        
        // Configurar controles de cantidad
        this.configurarControlesCantidad();
        
        // Configurar campos condicionales
        this.configurarCamposCondicionales();
        
        // Cargar datos guardados temporalmente
        this.cargarDatosTemporales();
    }

    initValidationRules() {
        return {
            nombre: {
                required: true,
                minLength: 3,
                maxLength: 100,
                pattern: /^[a-zA-ZáéíóúÁÉÍÓÚñÑ0-9\s\-\.]+$/
            },
            modelo: {
                maxLength: 50,
                pattern: /^[a-zA-Z0-9\s\-\.]+$/
            },
            color: {
                maxLength: 30,
                pattern: /^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/
            },
            talla_dimensiones: {
                maxLength: 50
            },
            cantidad: {
                required: true,
                min: 1,
                max: 999999
            },
            unidad_medida: {
                required: true
            },
            estado: {
                required: true
            },
            almacen_id: {
                required: true
            },
            categoria_id: {
                required: true
            },
            observaciones: {
                maxLength: 500
            }
        };
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

    configurarContadorCaracteres() {
        const observacionesField = document.getElementById('observaciones');
        const charCounter = document.getElementById('charCount');
        
        if (observacionesField && charCounter) {
            const updateCounter = () => {
                const currentLength = observacionesField.value.length;
                charCounter.textContent = currentLength;
                
                // Cambiar color según el límite
                if (currentLength > 400) {
                    charCounter.style.color = 'var(--danger-color)';
                } else if (currentLength > 300) {
                    charCounter.style.color = 'var(--warning-color)';
                } else {
                    charCounter.style.color = 'var(--text-muted)';
                }
            };
            
            observacionesField.addEventListener('input', updateCounter);
            updateCounter(); // Actualizar al cargar
        }
    }

    configurarControlesCantidad() {
        const cantidadInput = document.getElementById('cantidad');
        const minusBtn = document.querySelector('.qty-btn.minus');
        const plusBtn = document.querySelector('.qty-btn.plus');
        
        if (cantidadInput && minusBtn && plusBtn) {
            minusBtn.addEventListener('click', () => this.adjustQuantity(-1));
            plusBtn.addEventListener('click', () => this.adjustQuantity(1));
            
            cantidadInput.addEventListener('change', () => {
                this.validarCantidad();
            });
        }
    }

    configurarCamposCondicionales() {
        const categoriaSelect = document.getElementById('categoria_id');
        
        if (categoriaSelect) {
            categoriaSelect.addEventListener('change', () => {
                this.actualizarCamposSegunCategoria();
            });
            
            // Actualizar al cargar si ya hay una categoría seleccionada
            if (categoriaSelect.value) {
                this.actualizarCamposSegunCategoria();
            }
        }
    }

    configurarEventListeners() {
        // Manejar envío del formulario
        if (this.form) {
            this.form.addEventListener('submit', (e) => this.manejarEnvioFormulario(e));
            
            // Detectar cambios en el formulario
            this.form.addEventListener('input', () => this.detectarCambios());
            this.form.addEventListener('change', () => this.detectarCambios());
        }

        // Atajos de teclado
        document.addEventListener('keydown', (e) => this.manejarAtajosTeclado(e));

        // Advertencia al salir sin guardar
        window.addEventListener('beforeunload', (e) => {
            if (this.hasChanges) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Configurar validación en tiempo real
        this.configurarValidacionTiempoReal();
    }

    configurarValidaciones() {
        // Agregar validaciones personalizadas a los campos
        Object.keys(this.validationRules).forEach(fieldName => {
            const field = document.getElementById(fieldName);
            if (field) {
                field.addEventListener('blur', () => this.validarCampo(fieldName));
                field.addEventListener('input', () => this.validarCampoTiempoReal(fieldName));
            }
        });
    }

    configurarValidacionTiempoReal() {
        const campos = this.form.querySelectorAll('input, select, textarea');
        campos.forEach(campo => {
            campo.addEventListener('input', () => {
                this.validarCampoIndividual(campo);
            });
        });
    }

    adjustQuantity(increment) {
        const cantidadInput = document.getElementById('cantidad');
        if (!cantidadInput) return;

        let currentValue = parseInt(cantidadInput.value) || 1;
        let newValue = currentValue + increment;
        
        // Validar límites
        newValue = Math.max(1, Math.min(newValue, 999999));
        
        cantidadInput.value = newValue;
        this.validarCantidad();
        this.detectarCambios();
    }

    validarCantidad() {
        const cantidadInput = document.getElementById('cantidad');
        if (!cantidadInput) return;

        const value = parseInt(cantidadInput.value);
        
        if (value < 1 || value > 999999 || isNaN(value)) {
            this.mostrarErrorCampo(cantidadInput, 'La cantidad debe estar entre 1 y 999,999');
            return false;
        } else {
            this.limpiarErrorCampo(cantidadInput);
            return true;
        }
    }

    actualizarCamposSegunCategoria() {
        const categoriaSelect = document.getElementById('categoria_id');
        const categoriaId = categoriaSelect.value;
        
        // Configuraciones específicas por categoría
        const configuraciones = {
            '1': { // Ropa
                modelo: { visible: true, required: false },
                color: { visible: true, required: false },
                talla_dimensiones: { visible: true, required: false, placeholder: 'Ej: XL, 42...' }
            },
            '2': { // Accesorios de seguridad
                modelo: { visible: true, required: false },
                color: { visible: true, required: false },
                talla_dimensiones: { visible: true, required: false, placeholder: 'Ej: Mediano, 25cm...' }
            },
            '3': { // Kebras y fundas nuevas
                modelo: { visible: false, required: false },
                color: { visible: true, required: false },
                talla_dimensiones: { visible: true, required: false, placeholder: 'Ej: 30x25x10 cm...' }
            },
            '4': { // Armas
                modelo: { visible: true, required: true },
                color: { visible: true, required: false },
                talla_dimensiones: { visible: false, required: false }
            },
            '6': { // Walkie-Talkie
                modelo: { visible: true, required: true },
                color: { visible: true, required: false },
                talla_dimensiones: { visible: false, required: false }
            }
        };
        
        const config = configuraciones[categoriaId];
        
        if (config) {
            this.aplicarConfiguracionCampos(config);
        } else {
            // Configuración por defecto
            this.aplicarConfiguracionCampos({
                modelo: { visible: true, required: false },
                color: { visible: true, required: false },
                talla_dimensiones: { visible: true, required: false, placeholder: 'Talla o dimensiones...' }
            });
        }
    }

    aplicarConfiguracionCampos(config) {
        Object.keys(config).forEach(fieldName => {
            const field = document.getElementById(fieldName);
            const formGroup = field?.closest('.form-group');
            const label = formGroup?.querySelector('.form-label');
            
            if (field && formGroup) {
                // Mostrar/ocultar campo
                formGroup.style.display = config[fieldName].visible ? 'flex' : 'none';
                
                // Configurar requerido
                if (config[fieldName].required) {
                    field.setAttribute('required', '');
                    if (label && !label.textContent.includes('*')) {
                        label.innerHTML += ' *';
                    }
                } else {
                    field.removeAttribute('required');
                    if (label) {
                        label.innerHTML = label.innerHTML.replace(' *', '');
                    }
                }
                
                // Configurar placeholder
                if (config[fieldName].placeholder) {
                    field.setAttribute('placeholder', config[fieldName].placeholder);
                }
            }
        });
    }

    validarCampo(fieldName) {
        const field = document.getElementById(fieldName);
        const rules = this.validationRules[fieldName];
        
        if (!field || !rules) return true;

        const value = field.value.trim();
        
        // Validar campo requerido
        if (rules.required && !value) {
            this.mostrarErrorCampo(field, 'Este campo es obligatorio');
            return false;
        }
        
        // Si está vacío y no es requerido, es válido
        if (!value && !rules.required) {
            this.limpiarErrorCampo(field);
            return true;
        }
        
        // Validar longitud mínima
        if (rules.minLength && value.length < rules.minLength) {
            this.mostrarErrorCampo(field, `Mínimo ${rules.minLength} caracteres`);
            return false;
        }
        
        // Validar longitud máxima
        if (rules.maxLength && value.length > rules.maxLength) {
            this.mostrarErrorCampo(field, `Máximo ${rules.maxLength} caracteres`);
            return false;
        }
        
        // Validar patrón
        if (rules.pattern && !rules.pattern.test(value)) {
            this.mostrarErrorCampo(field, 'Formato no válido');
            return false;
        }
        
        // Validar valores numéricos
        if (rules.min !== undefined || rules.max !== undefined) {
            const numValue = parseInt(value);
            if (isNaN(numValue)) {
                this.mostrarErrorCampo(field, 'Debe ser un número válido');
                return false;
            }
            
            if (rules.min !== undefined && numValue < rules.min) {
                this.mostrarErrorCampo(field, `Valor mínimo: ${rules.min}`);
                return false;
            }
            
            if (rules.max !== undefined && numValue > rules.max) {
                this.mostrarErrorCampo(field, `Valor máximo: ${rules.max}`);
                return false;
            }
        }
        
        this.limpiarErrorCampo(field);
        return true;
    }

    validarCampoTiempoReal(fieldName) {
        // Validación menos estricta para tiempo real
        const field = document.getElementById(fieldName);
        const rules = this.validationRules[fieldName];
        
        if (!field || !rules) return;

        const value = field.value.trim();
        
        // Solo mostrar errores de longitud máxima en tiempo real
        if (rules.maxLength && value.length > rules.maxLength) {
            this.mostrarErrorCampo(field, `Máximo ${rules.maxLength} caracteres`);
        } else if (field.classList.contains('error')) {
            // Solo limpiar si había un error de longitud
            const errorElement = field.parentNode.querySelector('.field-error');
            if (errorElement && errorElement.textContent.includes('Máximo')) {
                this.limpiarErrorCampo(field);
            }
        }
    }

    validarCampoIndividual(campo) {
        // Validación visual inmediata
        if (campo.hasAttribute('required') && !campo.value.trim()) {
            campo.classList.add('invalid');
            campo.classList.remove('valid');
        } else if (campo.value.trim()) {
            campo.classList.add('valid');
            campo.classList.remove('invalid');
        } else {
            campo.classList.remove('valid', 'invalid');
        }
    }

    mostrarErrorCampo(field, mensaje) {
        field.classList.add('error');
        field.classList.remove('valid');
        
        // Remover error previo
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
        
        // Agregar nuevo error
        const errorElement = document.createElement('div');
        errorElement.className = 'field-error';
        errorElement.innerHTML = `<i class="fas fa-exclamation-triangle"></i> ${mensaje}`;
        field.parentNode.appendChild(errorElement);
    }

    limpiarErrorCampo(field) {
        field.classList.remove('error');
        if (field.value.trim()) {
            field.classList.add('valid');
        }
        
        const existingError = field.parentNode.querySelector('.field-error');
        if (existingError) {
            existingError.remove();
        }
    }

    validarFormularioCompleto() {
        let esValido = true;
        
        Object.keys(this.validationRules).forEach(fieldName => {
            if (!this.validarCampo(fieldName)) {
                esValido = false;
            }
        });
        
        return esValido;
    }

    detectarCambios() {
        this.hasChanges = true;
        
        // Marcar formulario como modificado
        if (this.formContainer) {
            this.formContainer.classList.add('form-changed');
        }
        
        // Actualizar título de la página
        if (!document.title.startsWith('*')) {
            document.title = '* ' + document.title;
        }
    }

    async manejarEnvioFormulario(e) {
        e.preventDefault();
        
        // Validar formulario completo
        if (!this.validarFormularioCompleto()) {
            this.mostrarNotificacion('Por favor, corrija los errores en el formulario', 'error');
            this.resaltarPrimerError();
            return;
        }
        
        // Confirmación
        const confirmado = await this.confirmarAccion(
            '¿Está seguro que desea registrar este producto?',
            'Confirmar Registro',
            'info'
        );
        
        if (!confirmado) return;
        
        // Mostrar estado de carga
        const submitButton = document.getElementById('btnRegistrar');
        const originalText = submitButton.innerHTML;
        
        submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando...';
        submitButton.disabled = true;
        
        try {
            // Guardar datos temporalmente
            this.guardarDatosTemporales();
            
            // Enviar formulario
            this.form.submit();
            
        } catch (error) {
            console.error('Error al enviar formulario:', error);
            this.mostrarNotificacion('Error al procesar el formulario', 'error');
            
            // Restaurar botón
            submitButton.innerHTML = originalText;
            submitButton.disabled = false;
        }
    }

    resaltarPrimerError() {
        const primerError = document.querySelector('.field-error');
        if (primerError) {
            const campo = primerError.parentNode.querySelector('input, select, textarea');
            if (campo) {
                campo.focus();
                campo.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    }

    manejarAtajosTeclado(e) {
        // Ctrl + S para guardar
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            document.getElementById('btnRegistrar').click();
        }
        
        // Ctrl + R para limpiar
        if (e.ctrlKey && e.key === 'r') {
            e.preventDefault();
            this.limpiarFormulario();
        }
        
        // Esc para cancelar
        if (e.key === 'Escape') {
            window.location.href = 'listar.php';
        }
    }

    limpiarFormulario() {
        if (this.hasChanges) {
            this.confirmarAccion(
                '¿Está seguro que desea limpiar el formulario? Se perderán todos los datos ingresados.',
                'Confirmar Limpiar',
                'warning'
            ).then(confirmado => {
                if (confirmado) {
                    this.ejecutarLimpiarFormulario();
                }
            });
        } else {
            this.ejecutarLimpiarFormulario();
        }
    }

    ejecutarLimpiarFormulario() {
        this.form.reset();
        
        // Limpiar errores
        const errorElements = this.form.querySelectorAll('.field-error');
        errorElements.forEach(error => error.remove());
        
        // Limpiar clases de validación
        const fields = this.form.querySelectorAll('input, select, textarea');
        fields.forEach(field => {
            field.classList.remove('error', 'valid', 'invalid');
        });
        
        // Resetear contador de caracteres
        document.getElementById('charCount').textContent = '0';
        
        // Resetear cantidad a 1
        document.getElementById('cantidad').value = '1';
        
        // Marcar como sin cambios
        this.hasChanges = false;
        this.formContainer.classList.remove('form-changed');
        document.title = document.title.replace('* ', '');
        
        // Eliminar datos temporales
        this.eliminarDatosTemporales();
        
        this.mostrarNotificacion('Formulario limpiado correctamente', 'info');
    }

    guardarDatosTemporales() {
        const formData = new FormData(this.form);
        const datos = {};
        
        for (let [key, value] of formData.entries()) {
            datos[key] = value;
        }
        
        localStorage.setItem('productos_registrar_temp', JSON.stringify(datos));
    }

    cargarDatosTemporales() {
        const datosGuardados = localStorage.getItem('productos_registrar_temp');
        
        if (datosGuardados) {
            try {
                const datos = JSON.parse(datosGuardados);
                
                Object.keys(datos).forEach(key => {
                    const field = document.getElementById(key);
                    if (field && !field.value) {  // Solo cargar si el campo está vacío
                        field.value = datos[key];
                    }
                });
                
                this.mostrarNotificacion('Se han restaurado datos no guardados', 'info');
            } catch (error) {
                console.error('Error al cargar datos temporales:', error);
            }
        }
    }

    eliminarDatosTemporales() {
        localStorage.removeItem('productos_registrar_temp');
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
                <div class="modal" style="display: block; z-index: 3000; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5);">
                    <div class="modal-content" style="background: white; margin: 10% auto; max-width: 400px; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.3);">
                        <div class="modal-header" style="background: var(--primary-color); color: white; padding: 20px; text-align: center;">
                            <h2 style="margin: 0; font-size: 18px;">
                                <i class="fas fa-question-circle"></i>
                                ${titulo}
                            </h2>
                        </div>
                        <div class="modal-body" style="padding: 25px; text-align: center;">
                            <p style="margin: 0; font-size: 16px; line-height: 1.5;">${mensaje}</p>
                        </div>
                        <div class="modal-footer" style="padding: 20px; display: flex; gap: 12px; justify-content: center; background: var(--light-color);">
                            <button class="btn-modal btn-cancel" id="btnCancelar" style="padding: 12px 20px; border: 1px solid var(--border-color); background: white; color: var(--text-primary); border-radius: 8px; cursor: pointer;">
                                <i class="fas fa-times"></i> Cancelar
                            </button>
                            <button class="btn-modal btn-confirm" id="btnConfirmar" style="padding: 12px 20px; border: none; background: var(--accent-color); color: white; border-radius: 8px; cursor: pointer;">
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
function adjustQuantity(increment) {
    window.productosRegistrar.adjustQuantity(increment);
}

function limpiarFormulario() {
    window.productosRegistrar.limpiarFormulario();
}

function manejarCerrarSesion(event) {
    event.preventDefault();
    
    window.productosRegistrar.confirmarAccion(
        '¿Estás seguro que deseas cerrar sesión?',
        'Cerrar Sesión',
        'warning'
    ).then(confirmado => {
        if (confirmado) {
            window.productosRegistrar.mostrarNotificacion('Cerrando sesión...', 'info', 2000);
            setTimeout(() => {
                window.location.href = '../logout.php';
            }, 1000);
        }
    });
}

// Agregar estilos CSS adicionales
const additionalStyles = `
    .field-error {
        color: var(--danger-color);
        font-size: 12px;
        margin-top: 5px;
        display: flex;
        align-items: center;
        gap: 5px;
        animation: slideInDown 0.3s ease;
    }
    
    .error {
        border-color: var(--danger-color) !important;
        background: rgba(220, 53, 69, 0.05) !important;
    }
    
    .valid {
        border-color: var(--success-color) !important;
        background: rgba(40, 167, 69, 0.05) !important;
    }
    
    .invalid {
        border-color: var(--warning-color) !important;
        background: rgba(255, 193, 7, 0.05) !important;
    }
    
    @keyframes slideInDown {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    @keyframes slideOutRight {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(100%);
        }
    }
    
    @keyframes slideOutUp {
        from {
            opacity: 1;
            transform: translateY(0);
        }
        to {
            opacity: 0;
            transform: translateY(-20px);
        }
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