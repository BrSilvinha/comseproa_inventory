<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | COMSEPROA</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS exclusivo para login -->
    <link rel="stylesheet" href="../assets/css/login-styles.css">
    
    <!-- Meta tags adicionales para mejor SEO y rendimiento -->
    <meta name="description" content="Iniciar sesión en el sistema de gestión de inventario COMSEPROA">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0a253c">
    
    <!-- Favicons -->
    <link rel="icon" type="image/x-icon" href="../assets/img/favicon.ico">
    <link rel="apple-touch-icon" href="../assets/img/apple-touch-icon.png">
</head>
<body>
    <div class="login-container">
        <!-- Sección Izquierda: Logo y Nombre -->
        <div class="left-section">
            <img src="../assets/img/logo.png" alt="Logo de COMSEPROA - Sistema de Gestión de Inventario" class="logo">
        </div>

        <!-- Línea Divisoria -->
        <div class="divider"></div>

        <!-- Sección Derecha: Formulario de Login -->
        <div class="right-section">
            <h2>Login</h2>
            <form action="../auth/login.php" method="POST" id="loginForm">
                <div>
                    <label for="correo">Correo Electrónico:</label>
                    <input 
                        type="email" 
                        id="correo" 
                        name="correo" 
                        required 
                        autocomplete="email"
                        placeholder="ejemplo@comseproa.com"
                        aria-describedby="correo-help"
                    >
                </div>

                <div>
                    <label for="password">Contraseña:</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        autocomplete="current-password"
                        placeholder="Ingresa tu contraseña"
                        minlength="6"
                        aria-describedby="password-help"
                    >
                </div>

                <button type="submit" id="loginBtn">
                    Iniciar Sesión
                </button>
            </form>
        </div>
    </div>

    <!-- JavaScript para mejorar la experiencia del usuario -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const submitBtn = document.getElementById('loginBtn');
            const inputs = form.querySelectorAll('input');

            // Agregar efecto de carga al enviar formulario
            form.addEventListener('submit', function(e) {
                submitBtn.classList.add('loading');
                submitBtn.textContent = 'Iniciando sesión...';
                
                // Simular validación antes de enviar
                let isValid = true;
                inputs.forEach(input => {
                    if (!input.checkValidity()) {
                        isValid = false;
                        input.focus();
                        return false;
                    }
                });

                if (!isValid) {
                    e.preventDefault();
                    submitBtn.classList.remove('loading');
                    submitBtn.textContent = 'Iniciar Sesión';
                }
            });

            // Mejorar la experiencia con el teclado
            inputs.forEach((input, index) => {
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const nextInput = inputs[index + 1];
                        if (nextInput) {
                            nextInput.focus();
                        } else {
                            form.submit();
                        }
                    }
                });

                // Agregar validación en tiempo real
                input.addEventListener('blur', function() {
                    if (this.value && !this.checkValidity()) {
                        this.style.borderColor = '#dc3545';
                    } else if (this.value && this.checkValidity()) {
                        this.style.borderColor = '#28a745';
                    }
                });

                input.addEventListener('input', function() {
                    if (this.checkValidity()) {
                        this.style.borderColor = '#28a745';
                    } else {
                        this.style.borderColor = '#dc3545';
                    }
                });
            });

            // Prevenir envío múltiple
            let isSubmitting = false;
            form.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    e.preventDefault();
                    return false;
                }
                isSubmitting = true;
            });
        });

        // Manejar errores de carga de imagen
        document.querySelector('.logo').addEventListener('error', function() {
            this.style.display = 'none';
            console.warn('Logo no pudo cargar, verificar ruta de imagen');
        });

        // Accessibility: Manejar navegación con teclado
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Tab') {
                document.body.classList.add('keyboard-navigation');
            }
        });

        document.addEventListener('mousedown', function() {
            document.body.classList.remove('keyboard-navigation');
        });
    </script>

    <!-- Estilos adicionales para navegación por teclado -->
    <style>
        .keyboard-navigation input:focus,
        .keyboard-navigation button:focus {
            outline: 3px solid #0a253c !important;
            outline-offset: 2px !important;
        }
    </style>
</body>
</html>