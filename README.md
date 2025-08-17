# 🏢 COMSEPROA Inventory System

<div align="center">

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?style=for-the-badge&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=for-the-badge&logo=mysql&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)
![Chart.js](https://img.shields.io/badge/Chart.js-4.0+-FF6384?style=for-the-badge&logo=chartdotjs&logoColor=white)

**Sistema de Inventario Moderno y Seguro para GRUPO SEAL**

[🚀 Demo](#demo) • [📖 Instalación](#instalación) • [🛡️ Seguridad](#seguridad) • [⚡ Características](#características)

</div>

---

### 📋 Descripción

COMSEPROA es un sistema integral de gestión de inventarios desarrollado para GRUPO SEAL, diseñado para controlar y administrar eficientemente el inventario de uniformes, equipos de seguridad y materiales operativos distribuidos en múltiples almacenes.

## ⚡ Características Principales

### 🎯 **Nuevas Funcionalidades Modernas (v2.0)**

- 📊 **Dashboard Interactivo** - Gráficos en tiempo real con Chart.js
- 🔍 **Búsqueda Instantánea** - Sin recargar página, con filtros avanzados  
- 🌙 **Dark Mode** - Tema oscuro/claro con detección automática
- 📱 **Responsive Design** - Optimizado para móviles y tablets
- 🔔 **Notificaciones Toast** - Sistema moderno de alertas
- 🛡️ **Seguridad Avanzada** - CSRF, rate limiting, headers de seguridad

### 🏢 **Gestión Multi-Almacén**
- Control de inventario en múltiples ubicaciones
- Transferencias automáticas entre almacenes
- Seguimiento en tiempo real de stock por ubicación
- Reportes consolidados y por almacén específico

### 👥 **Sistema de Usuarios y Roles**
- **Administradores**: Control total del sistema
- **Almaceneros**: Gestión limitada a su almacén asignado
- Autenticación segura con sesiones
- Control de permisos granular

### 📦 **Gestión de Productos**
- Categorización avanzada de productos
- Control detallado de stock (modelo, color, talla)
- Estados de productos (Nuevo, Usado, Dañado)
- Alertas de stock crítico automáticas
- Ajustes manuales de inventario

### 🔄 **Sistema de Transferencias**
- Solicitudes de transferencia entre almacenes
- Flujo de aprobación/rechazo
- Notificaciones automáticas
- Historial completo de movimientos

### 📊 **Reportes y Analytics**
- Dashboard con gráficos interactivos
- Inventario general y por almacén
- Análisis de movimientos con filtros
- Actividad de usuarios detallada
- Exportación a PDF/Excel

## 🛠️ Tecnologías Utilizadas

### Backend
- **PHP 8.0+** - Arquitectura MVC moderna
- **MySQL 8.0+** - Base de datos optimizada
- **PDO/MySQLi** - Conexión segura con prepared statements
- **Autoloader PSR-4** - Carga automática de clases

### Frontend Moderno
- **HTML5 Semántico** - Estructura accesible
- **CSS3 Avanzado** - Variables CSS, Grid, Flexbox
- **JavaScript ES6+** - Módulos, async/await, fetch API
- **Chart.js 4.0** - Gráficos interactivos
- **Font Awesome 6** - Iconografía completa
- **Google Fonts (Poppins)** - Tipografía moderna

### Seguridad Empresarial
- **Autenticación robusta** - bcrypt, sesiones seguras
- **Protección CSRF** - Tokens únicos en formularios  
- **Rate Limiting** - Prevención de ataques de fuerza bruta
- **Headers de seguridad** - CSP, HSTS, X-Frame-Options
- **Validación completa** - Sanitización automática
- **Logging de seguridad** - Auditoría completa

## 🏗️ Arquitectura del Sistema

```
comseproa_inventory/
├── 📁 api/                    # Endpoints REST modernos
│   ├── dashboard_stats.php    # Estadísticas en tiempo real
│   └── search.php            # Búsqueda instantánea
├── 📁 assets/                # Recursos estáticos optimizados
│   ├── css/                  # Hojas de estilo modernas
│   │   ├── dashboard-modern.css
│   │   ├── responsive-mobile.css
│   │   └── main.css
│   ├── js/                   # JavaScript modular
│   │   ├── dashboard-charts.js
│   │   ├── instant-search.js
│   │   ├── toast-notifications.js
│   │   └── theme-manager.js
│   └── img/                  # Imágenes optimizadas
├── 📁 auth/                  # Sistema de autenticación
│   └── login.php            # Login con protección CSRF
├── 📁 core/                  # Clases principales del sistema
│   ├── Autoloader.php        # Carga automática PSR-4
│   ├── Config.php           # Configuración centralizada
│   ├── Database.php         # Conexión optimizada a BD
│   ├── Logger.php           # Sistema de logging avanzado
│   ├── Security.php         # Funciones de seguridad
│   ├── Session.php          # Manejo seguro de sesiones
│   ├── TemplateHelper.php   # Helpers para vistas
│   └── Validator.php        # Validación y CSRF
├── 📁 config/               # Configuración del sistema
│   ├── app.php             # Configuración principal
│   └── database.php        # Configuración de BD
├── 📁 logs/                 # Registros del sistema
├── 📁 [modules]/           # Módulos funcionales
│   ├── almacenes/          # Gestión de almacenes
│   ├── productos/          # Gestión de productos  
│   ├── usuarios/           # Gestión de usuarios
│   ├── reportes/           # Sistema de reportes
│   └── notificaciones/     # Centro de notificaciones
├── 📄 .env                 # Variables de entorno
├── 📄 .htaccess            # Configuración de seguridad
├── 📄 bootstrap.php        # Inicialización del sistema
├── 📄 dashboard.php        # Dashboard moderno
├── 📄 index.php           # Punto de entrada
└── 📄 comseproa_db.sql    # Esquema de base de datos
```

### 🗄️ Esquema de Base de Datos

#### Tablas Principales

**usuarios**
- Control de acceso y roles
- Asignación a almacenes específicos

**almacenes**
- Gestión de múltiples ubicaciones
- Información de ubicación

**productos**
- Inventario detallado
- Categorización y especificaciones

**movimientos**
- Historial completo de transacciones
- Tipos: entrada, salida, transferencia

**solicitudes_transferencia**
- Flujo de aprobación
- Trazabilidad de solicitudes

**entrega_uniformes**
- Registro de entregas a personal
- Control por destinatario

**logs_actividad**
- Auditoría completa del sistema
- Trazabilidad de acciones

## ⚡ Instalación Rápida

### 📋 Requisitos Previos

- **PHP 8.0+** con extensiones: mysqli, session, json
- **MySQL 8.0+** o MariaDB 10.4+
- **Servidor Web** (Apache/Nginx) con mod_rewrite
- **SSL Certificate** (recomendado para producción)

### 🚀 Instalación Paso a Paso

1. **Clonar el repositorio**
   ```bash
   git clone https://github.com/BrSilvinha/comseproa_inventory.git
   cd comseproa_inventory
   ```

2. **Configurar base de datos**
   ```sql
   CREATE DATABASE comseproa_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   SOURCE comseproa_db.sql;
   ```

3. **Configurar variables de entorno**
   ```bash
   cp .env.example .env
   ```
   
   Editar `.env` con tus credenciales:
   ```env
   # Configuración de Base de Datos
   DB_HOST=localhost
   DB_USERNAME=tu_usuario
   DB_PASSWORD=tu_contraseña
   DB_NAME=comseproa_db
   
   # Configuración de la Aplicación
   APP_URL=https://tu-dominio.com
   APP_ENV=production
   APP_DEBUG=false
   
   # Configuración de Seguridad
   MAX_LOGIN_ATTEMPTS=5
   LOGIN_LOCKOUT_TIME=900
   SESSION_SECURE=true
   ```

4. **Configurar permisos**
   ```bash
   chmod 755 -R .
   chmod 644 -R *.php
   chmod 777 logs/
   chmod 600 .env
   ```

5. **Crear usuario administrador**
   - Accede a `tu-dominio.com/setup_admin.php`
   - Usa las credenciales por defecto:
     - **Email:** admin@comseproa.com
     - **Contraseña:** admin123
   - ⚠️ **Cambia la contraseña inmediatamente**

### 🎯 Acceso al Sistema

**Credenciales por defecto:**
- **Email**: admin@comseproa.com
- **Contraseña**: admin123
- **Rol**: Administrador

> ⚠️ **Importante**: Cambia las credenciales inmediatamente después del primer login.

### 🔧 Configuración

#### Variables de Entorno
```php
// Configuraciones recomendadas en config/database.php
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_secure', 1); // Solo HTTPS
```

#### Configuración de Sesiones
- Timeout automático por inactividad
- Regeneración de session ID por seguridad
- Validación de roles en cada página

### 📱 Características Responsivas

- **Diseño Mobile-First**
- **Menú hamburguesa** para dispositivos móviles
- **Tablas responsivas** con scroll horizontal
- **Formularios optimizados** para touch
- **Interfaz adaptativa** para diferentes tamaños de pantalla

### 🔐 Seguridad Implementada

#### Autenticación
- Hashing seguro de contraseñas (bcrypt)
- Validación de sesiones
- Regeneración de session ID
- Timeout por inactividad

#### Autorización
- Control de acceso por roles
- Verificación de permisos por página
- Restricciones de almacén para almaceneros

#### Prevención de Ataques
- SQL Injection (prepared statements)
- XSS (htmlspecialchars, validación de entrada)
- CSRF (validaciones de origen)
- Session Hijacking (regeneración de ID)

### 📊 Funcionalidades Destacadas

#### Dashboard Inteligente
- **Estadísticas en tiempo real**
- **Accesos rápidos** por rol de usuario
- **Notificaciones integradas**
- **Navegación contextual**

#### Sistema de Inventario Avanzado
- **Control granular** de productos
- **Alertas automáticas** de stock crítico
- **Ajustes manuales** con auditoría
- **Categorización flexible**

#### Flujo de Transferencias
- **Solicitudes estructuradas**
- **Proceso de aprobación** configurable
- **Notificaciones automáticas**
- **Historial completo** de decisiones

#### Reportes Ejecutivos
- **Inventario consolidado** por almacén
- **Análisis de movimientos** con filtros
- **Actividad de usuarios** detallada
- **Exportación a PDF** profesional

### 🚨 Solución de Problemas

#### Problemas Comunes

**Error de conexión a base de datos**
```
- Verificar credenciales en config/database.php
- Confirmar que MySQL esté ejecutándose
- Validar permisos de usuario de BD
```

**Sesiones no funcionan**
```
- Verificar configuración de PHP sessions
- Confirmar permisos de escritura en /tmp
- Revisar configuración de cookies
```

**Problemas de permisos**
```
- Verificar permisos de archivos (755/644)
- Confirmar ownership del directorio web
- Revivar configuración de SELinux (si aplica)
```

### 🤝 Contribución

Para contribuir al proyecto:

1. Fork del repositorio
2. Crear rama feature (`git checkout -b feature/AmazingFeature`)
3. Commit cambios (`git commit -m 'Add some AmazingFeature'`)
4. Push a la rama (`git push origin feature/AmazingFeature`)
5. Crear Pull Request

### 📝 Licencia

Este proyecto está desarrollado para uso interno de GRUPO SEAL. Todos los derechos reservados.

### 👨‍💻 Desarrollado por

**GRUPO SEAL - Equipo de Desarrollo**
- Sistema diseñado para gestión eficiente de inventarios
- Enfoque en seguridad y usabilidad
- Arquitectura escalable y mantenible

---

## 🚀 Roadmap y Versiones

### 📅 Versión Actual: 2.0.0

**Nuevas características implementadas:**
- ✅ Dashboard interactivo con gráficos Chart.js
- ✅ Búsqueda instantánea sin recargar página
- ✅ Sistema de notificaciones toast modernas
- ✅ Dark mode con detección automática
- ✅ Responsive design optimizado para móvil
- ✅ Seguridad avanzada (CSRF, rate limiting, headers)

### 🔮 Próximas Versiones

**v2.1 - Analytics Avanzados**
- [ ] Predicción de stock con IA
- [ ] Dashboards personalizables
- [ ] Métricas de rendimiento

**v2.2 - Movilidad**
- [ ] PWA (Progressive Web App)
- [ ] Códigos QR para productos
- [ ] Aplicación móvil nativa

**v2.3 - Integraciones**
- [ ] API REST completa
- [ ] Webhooks para sistemas externos
- [ ] Backup automático a la nube

---

## 📄 Licencia

**Sistema Propietario - GRUPO SEAL**

Este software es propiedad exclusiva de GRUPO SEAL y está protegido por derechos de autor. Su uso está restringido únicamente a las operaciones autorizadas de la empresa.

---

## 👨‍💻 Créditos

**Desarrollado para GRUPO SEAL**

- 🌐 **Website**: [inventary.gruposealsac.me](https://inventary.gruposealsac.me)
- 📧 **Soporte**: soporte@gruposealsac.me
- 🏢 **Empresa**: GRUPO SEAL SAC

---

<div align="center">

**⭐ Sistema COMSEPROA v2.0 - Gestión de Inventarios Moderna ⭐**

*Desarrollado con ❤️ para optimizar las operaciones de GRUPO SEAL*

**Última actualización**: Agosto 2025

</div>
