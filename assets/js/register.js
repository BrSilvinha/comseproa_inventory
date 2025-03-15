document.getElementById("registerForm").addEventListener("submit", function(event) {
    let nombre = document.getElementById("nombre").value.trim();
    let apellidos = document.getElementById("apellidos").value.trim();
    let dni = document.getElementById("dni").value.trim();
    let correo = document.getElementById("correo").value.trim();
    let contraseña = document.getElementById("contraseña").value.trim();
    let confirmar_contraseña = document.getElementById("confirmar_contraseña").value.trim();
    let rol = document.getElementById("rol").value;
    let rolesValidos = ["admin", "almacenero", "supervisor"]; // Lista de roles permitidos
    
    
    if (!rolesValidos.includes(rol)) {
    alert("Seleccione un rol válido.");
    event.preventDefault();
    return;
    }


    // Validar campos vacíos
    if (!nombre || !apellidos || !dni || !correo || !contraseña || !confirmar_contraseña || !rol) {
        alert("Todos los campos son obligatorios.");
        event.preventDefault();
        return;
    }

    // Validar DNI (8 números)
    if (!/^\d{8}$/.test(dni)) {
        alert("El DNI debe contener exactamente 8 números.");
        event.preventDefault();
        return;
    }

    // Validar correo
    let correoRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!correoRegex.test(correo)) {
        alert("Ingrese un correo electrónico válido.");
        event.preventDefault();
        return;
    }

    // Validar contraseña
    if (contraseña.length < 8) {
        alert("La contraseña debe tener al menos 8 caracteres.");
        event.preventDefault();
        return;
    }

    // Confirmar contraseña
    if (contraseña !== confirmar_contraseña) {
        alert("Las contraseñas no coinciden.");
        event.preventDefault();
        return;
    }
    
});
