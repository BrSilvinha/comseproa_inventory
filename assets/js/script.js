document.addEventListener("DOMContentLoaded", function () {
    const submenus = document.querySelectorAll(".submenu-container .submenu");
    const submenuLinks = document.querySelectorAll(".submenu-container > a");

    submenuLinks.forEach(menu => {
        menu.addEventListener("click", function (event) {
            event.preventDefault();
            let submenu = this.nextElementSibling;
            if (submenu) {
                const isActive = submenu.classList.contains("activo");
                submenus.forEach(sub => sub.classList.remove("activo"));
                if (!isActive) {
                    submenu.classList.add("activo");
                }
            }
        });
    });

    document.addEventListener("click", function (event) {
        if (!event.target.closest(".submenu-container")) {
            submenus.forEach(sub => sub.classList.remove("activo"));
        }
    });

    // Manejo de eliminación de usuarios
    document.querySelectorAll(".btn-eliminar").forEach(button => {
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
                    alert("✅ Usuario eliminado correctamente");
                    location.reload(); // Recargar para ver cambios
                } else {
                    alert("❌ Error: " + data.message);
                }
            })
            .catch(error => {
                console.error("❌ Error en fetch:", error);
                alert("❌ Hubo un problema con la solicitud");
            });
        });
    });
});
