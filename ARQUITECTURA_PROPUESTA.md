# PROPUESTA DETALLADA DE ARQUITECTURA PARA EL PROYECTO LYM
**Documento técnico de diseño:** Fase 2 — Propuesta Arquitectónica  
**Proyecto:** LYM (Tienda Web, CRM y SCM)  
**Fecha:** 21 de Septiembre de 2026  

---

## 1. COMPARATIVA ARQUITECTÓNICA: ACTUAL VS. PROPUESTA

### Arquitectura Actual (Plana y Altamente Acoplada)
* Todos los archivos HTML de tienda, productos, compras, CRM y SCM conviven en la **raíz del proyecto**.
* Los endpoints PHP se encuentran dispersos en la carpeta `php/` sin división por módulo o dominio de negocio.
* `includes/config.php` centraliza de forma monolítica la configuración de base de datos, sesiones, funciones de autenticación, funciones de roles, sanitización, token CSRF y creación de notificaciones.
* La carpeta duplicada `LYM/` genera confusión operativa y desperdicio de almacenamiento.

### Arquitectura Propuesta (Modular y Desacoplada)
```text
LYM/
│
├── public/                          # Directorio expuesto al servidor web (DocumentRoot)
│   ├── index.php                    # Front controller / Entrada a la tienda pública
│   ├── assets/                      # Estilos, scripts del cliente e imágenes
│   │   ├── css/
│   │   │   ├── tienda.css           # Estilos de tienda (antes Proyecto.css)
│   │   │   └── gestor.css           # Estilos de CRM/SCM (antes crm-style.css)
│   │   ├── js/
│   │   │   └── evaluaciones.js      # Widget de valoraciones y reseñas
│   │   └── img/                     # Recursos gráficos y fotos de productos
│   ├── tienda/                      # Vistas públicas de productos y paquetes
│   │   ├── tazas.html
│   │   ├── tapetes.html
│   │   ├── sudaderas.html
│   │   ├── uniformes.html
│   │   ├── termos.html
│   │   ├── prodrelacionados.html    # Nombre corregido (sin espacio tipográfico)
│   │   ├── Promociones.html
│   │   ├── subasta.html
│   │   ├── comunidad.html
│   │   └── paquetes/
│   │       ├── basico.html
│   │       ├── viajero.html
│   │       ├── deportivo.html
│   │       ├── potterhead.html
│   │       └── estudiante.html
│   ├── cliente/                     # Flujo de compra y panel del cliente autenticado
│   │   ├── carrito.html
│   │   ├── datospersonales.html
│   │   ├── checkout.html
│   │   ├── tarjeta.html
│   │   ├── ticket.html
│   │   ├── compras.html
│   │   ├── ticketcompras.html
│   │   ├── perfil.html
│   │   └── publicaciones.html
│   └── auth/                        # Componentes y modales de autenticación pública
│
├── admin/                           # Punto de acceso administrativo
│   └── index.php                    # Guardián de sesión admin y cargador del gestor
│
├── crm/                             # Módulo de Relaciones con Clientes
│   ├── index.php                    # Guardián de sesión staff/admin para CRM
│   └── views/                       # Pantallas parciales inyectadas por el shell
│       ├── crm-dashboard.html
│       ├── crm-clientes.html
│       ├── crm-detalle-cliente.html
│       ├── crm-interacciones.html
│       ├── crm-actividad.html
│       ├── crm-descuentos.html
│       ├── crm-evaluaciones.html
│       ├── crm-usuarios.html
│       └── crm-reportes.html
│
├── scm/                             # Módulo de Cadena de Suministro y Logística
│   ├── index.php                    # Guardián de sesión staff/admin para SCM
│   └── views/                       # Pantallas parciales inyectadas por el shell
│       ├── scm-inicio.html
│       ├── scm-inventario.html
│       ├── scm-productos.html
│       ├── scm-proveedores.html
│       ├── scm-pedidos.html
│       ├── scm-movimientos.html
│       ├── scm-logistica.html
│       └── scm-reportes.html
│
├── api/                             # Capa de API RESTful / JSON Endpoints
│   ├── auth/
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── register.php
│   │   ├── check_session.php
│   │   └── cambiar_password.php
│   ├── clientes/
│   │   ├── index.php                # Listado, búsqueda y creación
│   │   └── dropdown.php             # Listado optimizado para modales
│   ├── productos/
│   │   ├── index.php                # Catálogo público (antes get_products.php)
│   │   ├── admin.php                # Gestión admin (antes admin_products.php)
│   │   └── evaluaciones.php         # Reseñas (antes api_evaluaciones.php)
│   ├── pedidos/
│   │   ├── cupones.php              # Validación de cupones (antes api_cupones.php)
│   │   └── index.php                # Gestión de compras y checkout
│   ├── notificaciones/
│   │   └── index.php                # Notificaciones al cliente (antes api_notificaciones.php)
│   ├── crm/
│   │   ├── index.php                # API unificada o modular de CRM
│   │   └── trabajadores.php         # Gestión de trabajadores (antes api_trabajadores.php)
│   └── scm/
│       ├── index.php                # Endpoint central de SCM (resuelve scm-productos.html)
│       └── inventario.php           # Operaciones de stock y almacén
│
├── config/                          # Configuración del entorno
│   ├── database.php                 # Conexión PDO a MySQL / MariaDB
│   ├── session.php                  # Seguridad y configuración de cookies de sesión
│   └── app.php                      # Parámetros generales, rutas base y constantes
│
├── includes/                        # Lógica compartida del negocio
│   ├── config.php                   # Puente de compatibilidad (retrocompatibilidad)
│   ├── auth.php                     # Funciones isLoggedIn(), getCurrentUser()
│   ├── permissions.php              # Funciones isAdmin(), isWorker(), isStaff()
│   └── functions.php                # Funciones cleanInput(), helpers de respuesta JSON
│
├── database/                        # Control de persistencia y esquemas
│   ├── schema.sql                   # Estructura pura de tablas DDL corregida y unificada
│   └── seed.sql                     # Datos semilla iniciales para pruebas
│
├── docs/                            # Documentación y auditorías
│   ├── AUDITORIA_LYM.md
│   └── ARQUITECTURA_PROPUESTA.md
│
└── README.md                        # Manual técnico de instalación, uso y arquitectura
```

---

## 2. QUÉ ARCHIVOS SE MOVERÍAN

| Archivo Actual | Ubicación Propuesta | Justificación |
| :--- | :--- | :--- |
| `sistema.html` | `public/index.php` (o `public/tienda/sistema.html`) | Es la tienda pública principal y catálogo comercial. |
| `index.html` | `public/landing.html` | Landing page informativa comercial. |
| `tazas.html`, `sudaderas.html`, `tapetes.html`, `uniformes.html`, `termos.html` | `public/tienda/` | Páginas de detalle de productos de la tienda. |
| `basico.html`, `viajero.html`, `deportivo.html`, `potterhead.html`, `estudiante.html` | `public/tienda/paquetes/` | Páginas de paquetes combinados. |
| `carrito.html`, `datospersonales.html`, `checkout.html`, `tarjeta.html`, `ticket.html`, `compras.html`, `ticketcompras.html`, `perfil.html` | `public/cliente/` | Flujo de compra y gestión privada del cliente. |
| `gestor.html` | Compartido por `admin/` y `crm/` (o plantilla base) | Shell SPA de gestión operativa. |
| `crm-*.html` (9 archivos activos) | `crm/views/` | Pantallas parciales del CRM. |
| `scm-*.html` (8 archivos activos) | `scm/views/` | Pantallas parciales de la cadena de suministro. |
| `Proyecto.css` | `public/assets/css/tienda.css` | Hoja de estilos del escaparate público. |
| `crm-style.css` | `public/assets/css/gestor.css` | Hoja de estilos de las interfaces operativas. |
| `evaluaciones.js` | `public/assets/js/evaluaciones.js` | Script interactivo del cliente. |
| `imagen/*` | `public/assets/img/*` | Activos multimedia y fotos. |
| `php/login.php`, `register.php`, `logout.php`, `check_session.php` | `api/auth/` | Microservicios de sesión y autenticación. |
| `php/crm_api.php`, `php/api_trabajadores.php` | `api/crm/` | Backend operativo de CRM y personal. |
| `php/get_products.php`, `admin_products.php` | `api/productos/` | Servicios de catálogo y administración de productos. |
| `php/api_cupones.php` | `api/pedidos/cupones.php` | Servicio de validación de cupones. |
| `php/api_notificaciones.php` | `api/notificaciones/` | Servicio de notificaciones al cliente. |
| `php/api_cambiar_password.php` | `api/auth/cambiar_password.php` | Servicio de seguridad de usuario. |

---

## 3. QUÉ ARCHIVOS SE FUSIONARÍAN

1. **`includes/config.php` $\rightarrow$ Modularización:**
   * La lógica de conexión se separa en `config/database.php`.
   * La gestión de sesión se separa en `config/session.php`.
   * Los roles y guardas se separan en `includes/auth.php` y `includes/permissions.php`.
   * `includes/config.php` se conserva como un cargador puente que incluye los módulos anteriores para no romper scripts existentes.
2. **`php/clientes.php` y `php/api_clientes_dropdown.php`:**
   * Actualmente hay 3 formas distintas de consultar clientes en `php/crm_api.php`, `php/clientes.php` y `php/api_clientes_dropdown.php`. Se fusionan de forma limpia bajo `api/clientes/index.php` con soporte para listado completo y modo select ligero (`?dropdown=1`).
3. **Endpoints de Dashboards (`php/crm_dashboard.php` y `php/dashboard_data.php`):**
   * Sus consultas están duplicadas con respecto a `php/crm_api.php?resource=dashboard`. Se unifican en el servicio central de dashboard.

---

## 4. QUÉ ARCHIVOS DEBERÍAN CONSERVARSE

* **Todas las pantallas de la tienda:** Ninguna pantalla de producto o paquete se eliminará para mantener intacta la experiencia del comprador.
* **Todo el flujo de compra y ticket:** `carrito.html`, `datospersonales.html`, `checkout.html`, `tarjeta.html`, `ticket.html`, `compras.html`, `ticketcompras.html`.
* **Todas las 9 pantallas funcionales del CRM:** `crm-dashboard.html`, `crm-clientes.html`, `crm-detalle-cliente.html`, `crm-interacciones.html`, `crm-actividad.html`, `crm-descuentos.html`, `crm-evaluaciones.html`, `crm-usuarios.html`, `crm-reportes.html`.
* **Todas las 8 pantallas funcionales del SCM:** `scm-inicio.html`, `scm-inventario.html`, `scm-productos.html`, `scm-proveedores.html`, `scm-pedidos.html`, `scm-movimientos.html`, `scm-logistica.html`, `scm-reportes.html`.
* **Scripts de base de datos originales:** `database.sql`, `lym.sql` y `lym_base_datos_completa.sql` se conservan intactos en el repositorio como referencia histórica hasta validar completamente `database/schema.sql` y `database/seed.sql`.

---

## 5. QUÉ ARCHIVOS PODRÍAN ELIMINARSE (O ARCHIVARSE) TRAS VERIFICACIÓN

1. **La carpeta recursiva anidada `LYM/`:**
   * Contiene 91 archivos duplicados. Una vez respaldada, puede eliminarse del repositorio para evitar inconsistencias de versión.
2. **`intranet.html`:**
   * Prototipo arcaico de CRM que fue sustituido por `gestor.html`. No tiene ningún enlace entrante en el proyecto.
3. **`admin.html`:**
   * Archivo de 15 líneas que únicamente ejecuta una redirección a `admin.php`. Puede reemplazarse por una regla en servidor o eliminarse una vez se estandarice el enlace hacia `admin/`.
4. **`crm-editar-etapa.html` y `crm-registrar-interaccion.html`:**
   * Archivos creados inicialmente como páginas completas, pero cuya funcionalidad fue absorbida por los modales interactivos en `gestor.html` (`abrirModalGlobalEtapa()` y `abrirModalGlobalInteraccion()`).
5. **`php/pago.php`:**
   * Es una página HTML estática con un formulario de PayPal que quedó en la carpeta `php/` por error de ubicación.
6. **`php/fix_admin_account.php`:**
   * Script de restablecimiento sin autenticación que representa un riesgo de seguridad crítico.

---

## 6. QUÉ RUTAS TENDRÍAN QUE ACTUALIZARSE

Al mover archivos a carpetas estructuradas, deberán actualizarse sistemáticamente:

1. **Cargador dinámico del Gestor (`gestor.html`):**
   * Cambiar:
     ```javascript
     loadExternalView('crm-clientes.html', this, 'CRM');
     loadExternalView('scm-inicio.html', this, 'SCM');
     ```
   * Por:
     ```javascript
     loadExternalView('../crm/views/crm-clientes.html', this, 'CRM');
     loadExternalView('../scm/views/scm-inicio.html', this, 'SCM');
     ```
     *(O configurar un prefijo base dinámico `const MODULE_BASE_URL`)*.
2. **Llamadas `fetch()` en Vistas HTML y Scripts JS:**
   * En `sistema.html`, `perfil.html`, `carrito.html`:
     * De: `fetch("php/login.php")` $\rightarrow$ A: `fetch("../api/auth/login.php")` (o `/api/auth/login.php`).
     * De: `fetch("php/check_session.php")` $\rightarrow$ A: `fetch("../api/auth/check_session.php")`.
     * De: `fetch("php/api_notificaciones.php")` $\rightarrow$ A: `fetch("../api/notificaciones/index.php")`.
   * En `scm-productos.html`:
     * De: `fetch("php/productos.php")` $\rightarrow$ A: `fetch("../api/scm/index.php")`.
3. **Inclusiones de Backend en PHP:**
   * De: `require_once __DIR__ . '/../includes/config.php'`
   * A: `require_once __DIR__ . '/../../config/database.php'` (según la profundidad del directorio).
4. **Hojas de Estilo y Fuentes:**
   * En archivos HTML:
     * `<link rel="stylesheet" href="Proyecto.css">` $\rightarrow$ `<link rel="stylesheet" href="../assets/css/tienda.css">`
     * `<link rel="stylesheet" href="crm-style.css">` $\rightarrow$ `<link rel="stylesheet" href="../assets/css/gestor.css">`
5. **Corrección tipográfica de Productos Relacionados:**
   * `prodrelacionados .html` $\rightarrow$ renombrado a `prodrelacionados.html`.
   * Actualizar enlaces en `sudaderas.html`, `tazas.html`, `termos.html` y `uniformes.html`.

---

## 7. QUÉ RIESGOS EXISTEN Y ESTRATEGIA DE MITIGACIÓN

| Riesgo Identificado | Causa Potencial | Estrategia de Mitigación Segura |
| :--- | :--- | :--- |
| **Ruptura de llamadas de API (Error 404)** | Vistas HTML llamando a la ruta anterior `php/*.php`. | Mantener proxies o archivos puente en `php/` que redirijan internamente a `api/` durante la transición. |
| **Pérdida de la sesión entre módulos** | Diferentes dominios de ruta de cookies al moverse entre `/public/`, `/crm/`, `/admin/`. | Establecer de manera explícita en `config/session.php`: `session_set_cookie_params(['path' => '/', 'httponly' => true]);`. |
| **Fallo en scripts inyectados dinámicamente** | `gestor.html` inyecta scripts de `crm-*.html`. Si las rutas relativas en esos scripts difieren del contexto de `gestor.html`, fallan. | Normalizar todas las llamadas API para que utilicen rutas absolutas al host (`/api/...`) o una constante JavaScript `API_URL`. |
| **Pérdida de datos en MySQL** | Discrepancias entre las tablas de los archivos SQL y la base de datos viva. | No tocar tablas vivas hasta haber generado y verificado `database/schema.sql` y `database/seed.sql` respetando la estructura real que ya contiene MariaDB. |
| **Inconsistencia de permisos** | Conflictos entre el rol `trabajador` y la visualización de botones de administración. | Mantener las funciones canónicas `isAdmin()`, `isWorker()`, `isStaff()` y desacoplar los cheques hardcodeados a `juanlalo`. |

---

## 8. CONCLUSIÓN Y ESTADO ACTUAL

Se han completado en su totalidad la **Fase 1 (Auditoría Integral)** y la **Fase 2 (Propuesta Arquitectónica)**.

> [!IMPORTANT]
> **REGLA DE DETENCIÓN (Fase 3):**
> Siguiendo las instrucciones expresas del usuario, **no se ha modificado ni eliminado ningún archivo del proyecto**. Se detiene la ejecución para presentar los hallazgos y el plan de migración para su revisión y aprobación.
