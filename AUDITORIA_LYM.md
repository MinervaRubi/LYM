# AUDITORÍA INTEGRAL DEL PROYECTO LYM
**Fecha de auditoría:** 21 de Septiembre de 2026  
**Sistema:** LYM — Plataforma Web de Personalización, Tienda en Línea, CRM y SCM  
**Entorno de ejecución analizado:** PHP 8.2, MariaDB 10.4 / MySQL, Servidor Apache (XAMPP), HTML5, CSS3, JavaScript Vanilla  

---

## 1. ARQUITECTURA ACTUAL

El proyecto LYM está concebido actualmente bajo una **arquitectura monolítica mixta** con fuerte acoplamiento en el directorio raíz. Combina dos paradigmas de desarrollo:

1. **Front-office / Tienda pública (Multi-page + SPA híbrido):**
   * El punto de entrada principal es `sistema.html` (o `index.php` que incluye `sistema.html`).
   * La página principal funciona como un catálogo interactivo con filtrado de productos, modal de inicio de sesión de clientes y personal, modal de registro y carrito de compras dinámico.
   * Existen páginas estáticas satélite para productos individuales (`tazas.html`, `sudaderas.html`, `tapetes.html`, `uniformes.html`, `termos.html`), paquetes promocionales (`basico.html`, `viajero.html`, `deportivo.html`, `potterhead.html`, `estudiante.html`) y un flujo de compra lineal (`carrito.html` $\rightarrow$ `datospersonales.html` $\rightarrow$ `checkout.html` $\rightarrow$ `tarjeta.html` $\rightarrow$ `ticket.html`).

2. **Back-office / Gestor Administrativo, CRM y SCM (Single Page Application artesanal):**
   * El punto de entrada es `admin.php` (para Administradores) o `crm.php` (para Personal Staff), los cuales verifican la sesión y cargan la plantilla contenedora `gestor.html`.
   * `gestor.html` implementa un shell SPA propio mediante la función JavaScript `loadExternalView(viewUrl, button, moduleCategory)`. Esta función ejecuta `fetch()` sobre archivos HTML parciales (`crm-*.html` y `scm-*.html`), inyecta el HTML en un contenedor `<main id="mainContent">` y busca y evalúa dinámicamente las etiquetas `<script>` para inicializar la lógica de cada pantalla.

3. **Capa Backend y Persistencia:**
   * La lógica de servidor está implementada mediante scripts PHP procedimentales ubicados en la carpeta `php/`, que responden en formato JSON a peticiones asíncronas (`fetch`).
   * La configuración centralizada y la conexión a base de datos reside en `includes/config.php` mediante la clase `PDO`.
   * Existen endpoints monolíticos (como `php/crm_api.php`, con más de 800 líneas) que centralizan múltiples operaciones mediante el parámetro `?resource=`.

---

## 2. ESTRUCTURA DE CARPETAS ACTUAL

El inventario de directorios y archivos en la raíz del proyecto es el siguiente:

```text
Negociosll LYM/
│
├── .git/                                # Repositorio Git principal
├── imagen/                              # Recursos gráficos, logotipos, fotos de productos y categorías
├── includes/
│   └── config.php                       # Configuración de base de datos, sesiones, helpers de roles y CSRF
│
├── php/                                 # Scripts backend y endpoints API
│   ├── admin_products.php               # CRUD de productos para el administrador
│   ├── api_cambiar_password.php         # Cambio de contraseña de usuario
│   ├── api_clientes_dropdown.php        # Listado ligero de clientes para selects
│   ├── api_contacto.php                 # Formulario de contacto público
│   ├── api_cupones.php                  # Validación de cupones de descuento
│   ├── api_evaluaciones.php             # Reseñas y calificaciones de productos
│   ├── api_notificaciones.php           # Consulta y marcado de notificaciones de usuario
│   ├── api_trabajadores.php             # Gestión de trabajadores y privilegios de admin
│   ├── check_session.php                # Endpoint de verificación de sesión activa
│   ├── clientes.php                     # CRUD alternativo de clientes (legado)
│   ├── crm.php                          # Backend legado de estadísticas CRM
│   ├── crm_api.php                      # API unificada para todo el CRM (dashboard, clientes, tareas, descuentos)
│   ├── crm_dashboard.php                # Endpoint legado de KPIs
│   ├── dashboard_data.php               # Endpoint legado de métricas generales
│   ├── fix_admin_account.php            # Script utilitario/backdoor de restablecimiento de admin
│   ├── get_products.php                 # Catálogo público de productos y paquetes
│   ├── info.php                         # Script de diagnóstico phpinfo()
│   ├── login.php                        # Autenticación de usuarios (cliente y staff)
│   ├── logout.php                       # Cierre de sesión y destrucción de cookies
│   ├── migrate_db.php                   # Script de migración y verificación de esquema
│   ├── pago.php                         # Archivo HTML estático de PayPal con extensión .php
│   ├── register.php                     # Registro de nuevos clientes
│   └── test_connection.php              # Diagnóstico de conexión a MySQL y conteo de tablas
│
├── LYM/                                 # [DUPLICACIÓN CRÍTICA] Clon anidado recursivo completo del repositorio
│
├── Vistas Principales y Shells:
│   ├── index.html                       # Landing page comercial pública (redirige a sistema.html)
│   ├── index.php                        # Carga sistema.html mediante include
│   ├── sistema.html                     # Tienda principal y catálogo completo interactivo
│   ├── admin.php                        # Guardián de acceso admin que incluye gestor.html
│   ├── admin.html                       # Redirección estática hacia admin.php
│   ├── crm.php                          # Guardián de acceso staff que incluye gestor.html
│   ├── gestor.html                      # Shell SPA del panel CRM, SCM y Gestión de Usuarios
│   ├── intranet.html                    # [OBSOLETO] Prototipo anterior de panel intranet
│   └── perfil.html                      # Perfil de usuario, notificaciones y cupones
│
├── Vistas Parciales del Módulo CRM (Cargadas por gestor.html):
│   ├── crm-dashboard.html               # Dashboard con KPIs y gráficas
│   ├── crm-clientes.html                # Directorio y listado de clientes
│   ├── crm-detalle-cliente.html         # Vista 360° de cliente e historial
│   ├── crm-interacciones.html           # Bitácora general de interacciones
│   ├── crm-actividad.html               # Agenda de actividades y tareas pendientes
│   ├── crm-descuentos.html              # Aprobación y gestión de cupones/descuentos
│   ├── crm-evaluaciones.html            # Monitoreo de satisfacción del cliente
│   ├── crm-usuarios.html                # Gestión de trabajadores y asignación de roles
│   ├── crm-reportes.html                # Reportes de embudo y conversión
│   ├── crm-editar-etapa.html            # [OBSOLETO] Reemplazado por modal en gestor.html
│   └── crm-registrar-interaccion.html   # [OBSOLETO] Reemplazado por modal en gestor.html
│
├── Vistas Parciales del Módulo SCM (Cargadas por gestor.html):
│   ├── scm-inicio.html                  # Panel de control de la cadena de suministro
│   ├── scm-inventario.html              # Stock de almacén y niveles de inventario
│   ├── scm-productos.html               # Catálogo de artículos SCM y especificaciones
│   ├── scm-proveedores.html             # Directorio de proveedores y contactos
│   ├── scm-pedidos.html                 # Órdenes de compra y suministros
│   ├── scm-movimientos.html             # Entradas, salidas y ajustes de almacén
│   ├── scm-logistica.html               # Envíos, paqueterías y guías de rastreo
│   └── scm-reportes.html                # Analítica logística y costos de abastecimiento
│
├── Vistas de Productos y Paquetes de la Tienda:
│   ├── tazas.html                       # Detalle y opciones de tazas personalizadas
│   ├── tapetes.html                     # Detalle y opciones de tapetes afelpados
│   ├── sudaderas.html                   # Detalle y opciones de sudaderas
│   ├── uniformes.html                   # Detalle y opciones de uniformes
│   ├── termos.html                      # Detalle y opciones de termos y cilindros
│   ├── basico.html                      # Paquete comercial Básico
│   ├── viajero.html                     # Paquete comercial Viajero
│   ├── deportivo.html                   # Paquete comercial Deportivo
│   ├── potterhead.html                  # Paquete temático Potterhead
│   ├── estudiante.html                  # Paquete comercial Estudiante
│   ├── Promociones.html                 # Pantalla de promociones especiales
│   ├── subasta.html                     # Pantalla de subastas interactivas
│   ├── comunidad.html                   # Sección de comunidad y blog
│   ├── publicaciones.html               # Publicaciones del usuario
│   └── prodrelacionados .html           # [ERROR DE NOMBRE] Espacio en blanco antes de .html
│
├── Vistas de Checkout y Compras:
│   ├── carrito.html                     # Carrito de compras y aplicación de cupones
│   ├── datospersonales.html             # Formulario de datos de envío del cliente
│   ├── checkout.html                    # Selección de método de pago
│   ├── tarjeta.html                     # Formulario de pago con tarjeta
│   ├── ticket.html                      # Comprobante de pago generado post-compra
│   ├── compras.html                     # Historial de compras del cliente logueado
│   └── ticketcompras.html               # Visualización individual de ticket histórico
│
├── Archivos de Estilo y Scripts:
│   ├── Proyecto.css                     # Estilos principales de la tienda (73.6 KB)
│   ├── crm-style.css                    # Estilos del gestor CRM y SCM (20.5 KB)
│   └── evaluaciones.js                  # Widget interactivo de calificación y reseñas
│
├── Scripts de Base de Datos:
│   ├── database.sql                     # Volcado inicial con tipos BIGINT
│   ├── lym.sql                          # Volcado phpMyAdmin MariaDB
│   └── lym_base_datos_completa.sql      # Volcado más reciente con solicitudes_descuento
│
└── Documentación:
    ├── INSTRUCCIONES.md                 # Documentación inicial del proyecto
    └── test_results.md                  # Bitácora de pruebas previas
```

---

## 3. DEPENDENCIAS PRINCIPALES

### A. Matriz de Invocación HTML $\rightarrow$ Backend PHP
| Archivo HTML / JS | Endpoint PHP Invocado | Parámetros / Payload | Propósito |
| :--- | :--- | :--- | :--- |
| `sistema.html` | `php/check_session.php` | GET | Comprobar sesión de cliente / staff |
| `sistema.html` | `php/login.php` | POST `{username, password}` | Inicio de sesión |
| `sistema.html` | `php/register.php` | POST `{username, email, password}` | Registro de cliente |
| `sistema.html` | `php/logout.php` | POST | Cerrar sesión activa |
| `sistema.html` | `php/api_contacto.php` | POST `{nombre, correo, mensaje}` | Formulario de contacto |
| `evaluaciones.js` | `php/api_evaluaciones.php` | GET `?producto_nombre=...` / POST | Consultar y registrar valoraciones |
| `carrito.html` | `php/api_cupones.php` | POST `{action:'validar', codigo_cupon:...}` | Validar cupón de descuento |
| `perfil.html` | `php/check_session.php` | GET | Datos de usuario logueado |
| `perfil.html` | `php/api_notificaciones.php`| GET / POST `{id:0}` | Ver y marcar notificaciones |
| `perfil.html` | `php/api_cambiar_password.php`| POST `{old_password, new_password}` | Modificar credencial |
| `gestor.html` | `php/check_session.php` | GET | Validar permisos de administrador/staff |
| `gestor.html` | `php/logout.php` | POST | Cerrar sesión de staff |
| `gestor.html` | `php/crm_api.php` | GET / POST `?resource=...` | Operaciones globales de modales |
| `gestor.html` | `php/api_trabajadores.php` | GET / POST / PATCH / DELETE | Gestión de cuentas de trabajadores |
| `gestor.html` | `php/api_clientes_dropdown.php`| GET | Listado para selects de clientes |
| `crm-dashboard.html` | `php/crm_api.php` | GET `?resource=dashboard` | KPIs, funnel y clientes en riesgo |
| `crm-clientes.html` | `php/crm_api.php` | GET `?resource=clientes` / `eliminar_cliente` | Listado y eliminación |
| `crm-detalle-cliente.html`| `php/crm_api.php` | GET `?resource=cliente_detalle&id=...` | Ficha técnica 360° |
| `crm-interacciones.html`| `php/crm_api.php` | GET `?resource=interacciones` | Historial de interacciones |
| `crm-actividad.html` | `php/crm_api.php` | GET `?resource=mi_actividad` / `cambiar_estado_interaccion` | Agenda y tareas |
| `crm-descuentos.html` | `php/crm_api.php` | GET `?resource=descuentos` / `aprobar_descuento` / `rechazar_descuento` | Flujo de cupones |
| `crm-evaluaciones.html`| `php/crm_api.php` | GET `?resource=evaluaciones` | Reseñas en panel CRM |
| `crm-usuarios.html` | `php/api_trabajadores.php` | POST / PATCH / DELETE | Administración de personal |
| `crm-reportes.html` | `php/crm_api.php` | GET `?resource=reportes` | Analítica comercial |
| `scm-productos.html` | `php/productos.php` *(FALTA)* | GET | Catálogo SCM (falla al no existir) |

### B. Matriz de Inclusión PHP $\rightarrow$ PHP
* `admin.php` $\rightarrow$ `require_once __DIR__ . '/includes/config.php'` $\rightarrow$ `include __DIR__ . '/gestor.html'`
* `crm.php` $\rightarrow$ `require_once __DIR__ . '/includes/config.php'` $\rightarrow$ `include __DIR__ . '/gestor.html'`
* `index.php` $\rightarrow$ `require_once __DIR__ . '/includes/config.php'` $\rightarrow$ `include __DIR__ . '/sistema.html'`
* Todos los archivos en `php/*.php` $\rightarrow$ `require_once __DIR__ . '/../includes/config.php'`

### C. Dependencias CSS y Fuentes por Módulo
* **Tienda pública:** `Proyecto.css`, Google Fonts (*Poppins*), Font Awesome 6.x CDN.
* **Panel Gestor (CRM/SCM):** `crm-style.css`, Google Fonts (*Plus Jakarta Sans*), Font Awesome 6.5.0 CDN.
* **Componentes JS externos:** Chart.js (CDN cargado en `gestor.html` y `crm-dashboard.html`).

---

## 4. MÓDULOS ENCONTRADOS

1. **Módulo de Tienda Pública y Catálogo:**
   * Navegación por categorías de productos personalizados (tazas, textiles, tapetes, uniformes, termos) y paquetes combinados.
   * Sistema de subastas y publicaciones comunitarias (`subasta.html`, `comunidad.html`, `publicaciones.html`).
2. **Módulo de Autenticación, Usuarios y Seguridad:**
   * Modal de login y registro en tienda.
   * Control de sesiones basadas en cookies PHP seguras (`session.cookie_httponly`).
   * Distinción de 3 roles: `cliente`, `trabajador`, `admin`.
3. **Módulo de Carrito, Compras y Checkout:**
   * Carrito persistente en `localStorage`.
   * Validación de cupones contra la base de datos (`php/api_cupones.php`).
   * Pasos de captura de datos de envío, forma de pago y generación de ticket imprimible.
   * Historial de órdenes por cliente (`compras.html`, `ticketcompras.html`).
4. **Módulo de Perfil de Cliente:**
   * Consulta de perfil personal, actualización de contraseña.
   * Bandeja de entrada de notificaciones y cupones otorgados.
5. **Módulo CRM (Customer Relationship Management):**
   * Vista 360° del cliente, embudo de ventas (`Prospecto`, `Contacto`, `Cotizando`, `Negociación`, `Activo`, `Frecuente`, `Inactivo`).
   * Bitácora de interacciones omnicanal (llamada, correo, WhatsApp, notas).
   * Agenda de actividades pendientes del trabajador con recordatorios.
   * Sistema de propuesta y aprobación jerárquica de descuentos con generación de cupones.
   * Monitoreo de satisfacción y evaluaciones de productos.
6. **Módulo SCM (Supply Chain Management):**
   * Panel de control logístico y de compras.
   * Gestión de inventarios, almacenes, movimientos (entradas/salidas).
   * Directorio de proveedores, órdenes de compra, control de envíos y logística de distribución.
7. **Módulo de Administración del Sistema:**
   * Panel de control de personal y trabajadores.
   * Promoción de trabajadores a administradores y degradación de privilegios.
   * CRUD de productos y paquetes de la tienda.

---

## 5. PROBLEMAS DETECTADOS

1. **Ruptura en módulo SCM (`php/productos.php` no existe):**
   * `scm-productos.html` realiza una llamada a `fetch("php/productos.php")`. Dicho archivo no existe en el proyecto (existen `admin_products.php` y `get_products.php`), provocando un error 404 en la consola al acceder a esa pantalla.
2. **Error de sintaxis en nombre de archivo:**
   * Existe un archivo llamado `prodrelacionados .html` con un espacio tipográfico antes de la extensión. En `sudaderas.html`, `tazas.html`, `termos.html` y `uniformes.html` se hace referencia a `"prodrelacionados .html"` (con espacio), mientras que en `subasta.html` se enlaza a `"prodrelacionados.html"` (sin espacio), lo que rompe la navegación en entornos Linux/Apache que diferencian estrictamente los nombres.
3. **Hardcoding de nombres de usuario en la lógica de negocio:**
   * En múltiples archivos (`sistema.html`, `gestor.html`, `fix_admin_account.php`, `includes/config.php`) está codificado de forma rígida el usuario `juanlalo`:
     ```javascript
     if (currentUser.role === 'admin' || currentUser.username?.toLowerCase() === 'juanlalo')
     ```
   * Esto rompe el principio de abstracción basado en roles y permisos dinámicos (RBAC).
4. **Falta de endpoint SCM en el Backend:**
   * Las pantallas `scm-inventario.html`, `scm-logistica.html`, `scm-movimientos.html`, `scm-pedidos.html`, `scm-proveedores.html` y `scm-reportes.html` operan actualmente con datos estáticos simulados en el cliente; carecen de un API en PHP y de tablas en MySQL que persistan la información.
5. **Carga duplicada de versiones de CSS en `gestor.html`:**
   * Línea 16: `<link rel="stylesheet" href="crm-style.css?v=2.2">`
   * Línea 19: `<link rel="stylesheet" href="crm-style.css?v=2.3">`
   * Se incluye dos veces la misma hoja de estilos.

---

## 6. DUPLICACIONES

1. **Directorio recursivo clonado `LYM/`:**
   * Existe una carpeta completa `c:\Users\MRRA_\OneDrive\Escritorio\Negociosll LYM\LYM\` con 91 archivos que duplican exactamente el proyecto principal. Esto genera riesgo de editar archivos en la carpeta incorrecta y añade peso innecesario.
2. **Duplicación de endpoints para clientes:**
   * `php/clientes.php` vs. `php/crm_api.php?resource=clientes` vs. `php/api_clientes_dropdown.php`.
   * En `gestor.html` existen bloques `try/catch` encadenados que prueban un endpoint y, si falla, llaman al otro.
3. **Duplicación de endpoints para dashboards:**
   * Existen tres endpoints distintos que realizan conteos similares de pedidos y clientes: `php/crm_dashboard.php`, `php/dashboard_data.php` y `php/crm_api.php?resource=dashboard`.
4. **Duplicación de lógica de redirección y shell:**
   * `admin.html` sólo redirige a `admin.php`.
   * `admin.php` y `crm.php` contienen exactamente la misma estructura para incluir `gestor.html`.

---

## 7. RIESGOS DE REORGANIZACIÓN

Si los archivos se mueven de carpeta sin una estrategia precisa, surgirán los siguientes fallos críticos:

1. **Ruptura del Shell SPA (`loadExternalView`):**
   * En `gestor.html`, la función `loadExternalView` hace `fetch('crm-clientes.html')` esperando encontrar el archivo en la misma ruta relativa del navegador. Si los módulos se mueven a carpetas como `/crm/` o `/scm/`, las llamadas devolverán 404 a menos que se ajusten las rutas de navegación.
2. **Ruptura de llamadas relativas a la API:**
   * Todas las vistas HTML ejecutan llamadas del tipo `fetch("php/crm_api.php")` o `fetch("php/login.php")`. Si un archivo HTML se mueve a un subdirectorio (por ejemplo, `tienda/sistema.html`), la ruta `"php/login.php"` buscará en `tienda/php/login.php` y fallará.
3. **Ruptura de `require_once` en PHP:**
   * Todos los endpoints en `php/` invocan `require_once __DIR__ . '/../includes/config.php'`. Moverlos a `api/crm/` cambiará la profundidad de directorios (`__DIR__ . '/../../includes/config.php'`).
4. **Pérdida de sesión y cookies:**
   * Las cookies de sesión de PHP se configuran con un path determinado (`/`). Al cambiar la estructura de URL de los scripts sin configurar `session.cookie_path = '/'`, las sesiones pueden perderse entre la tienda y el panel de administración.
5. **Ruptura de enlaces de imágenes:**
   * Las imágenes están referenciadas como `imagen/...` o `url("imagen/...")` en CSS y HTML. Si las vistas o el CSS cambian de nivel, los gráficos dejarán de cargar.

---

## 8. PROBLEMAS DE SEGURIDAD

1. **Backdoor de recuperación sin autenticación (`php/fix_admin_account.php`):**
   * **Nivel: CRÍTICO.** Cualquier usuario que visite en el navegador `http://localhost/php/fix_admin_account.php` (sin estar autenticado) provoca que el sistema reescriba la contraseña del administrador `juanlalo` a `123456` y le asigne rol de administrador.
2. **Exposición de información del servidor (`php/info.php` y `php/test_connection.php`):**
   * **Nivel: ALTO.** `info.php` ejecuta `phpinfo()`, revelando rutas completas del sistema, variables de entorno y módulos cargados. `test_connection.php` lista públicamente las tablas de la base de datos y la cantidad de registros.
3. **Ausencia de validación de CSRF en endpoints de mutación:**
   * **Nivel: ALTO.** Aunque `includes/config.php` cuenta con las funciones `generateCSRFToken()` y `verifyCSRFToken()`, ningún endpoint (`login.php`, `register.php`, `crm_api.php`, `api_trabajadores.php`) valida tokens CSRF en sus peticiones POST.
4. **Falta de Rate Limiting (Protección contra fuerza bruta):**
   * **Nivel: MEDIO.** `php/login.php` permite intentos ilimitados de inicio de sesión sin bloqueo por IP ni retardo progresivo.
5. **Sanitización de entrada distorsionante (`cleanInput`):**
   * `cleanInput()` ejecuta `htmlspecialchars()` antes de almacenar en la base de datos. Esto altera contraseñas o datos con caracteres especiales y provoca doble escape cuando se vuelve a renderizar en el cliente. El escape debe ocurrir en la salida (renderizado), no en el almacenamiento.
6. **Inconsistencia de control de acceso en endpoints legados:**
   * `php/crm_dashboard.php` y `php/dashboard_data.php` no verifican `isStaff()` antes de entregar estadísticas de ventas y clientes.

---

## 9. PROBLEMAS DE BASE DE DATOS Y COMPARATIVA DE ARCHIVOS SQL

### A. Comparativa de los 3 Archivos SQL y la Base de Datos Real en MySQL

| Característica / Tabla | `database.sql` | `lym.sql` | `lym_base_datos_completa.sql` | Base de Datos Real (`lym` en MySQL) |
| :--- | :--- | :--- | :--- | :--- |
| **Tipo de Claves Primarias** | `BIGINT` | `INT(11)` | `INT(11)` | `INT(11)` |
| **Roles en `usuarios.role`** | `enum('cliente','admin')` | `enum('cliente','admin')` | `enum('cliente','admin')` | **`enum('cliente','admin','trabajador')`** |
| **Campos en `interacciones`** | Básico (sin usuario ni fechas) | Con `usuario_id`, sin estado | Con `usuario_id`, sin estado | **Tiene `estado` y `prioridad`** |
| **Campos en `evaluaciones_crm`** | Sin producto asociado | Sin producto asociado | Sin producto asociado | **Tiene `producto_id` y `producto_nombre`** |
| **Tabla `solicitudes_descuento`** | ❌ No existe | ❌ No existe | ✅ **Existe y definida** | ⚠️ **No importada aún en MySQL** |
| **Tabla `notificaciones`** | ❌ No existe | ❌ No existe | ❌ **No existe en el script** | ✅ **Existe y en uso en MySQL** |
| **Tabla `notas_internas_admin`**| ❌ No existe | ❌ No existe | ❌ No existe | ⚠️ Existe en MySQL (tabla previa) |
| **Campos en `clientes`** | Etapas fijas de ventas | Etapas de actividad | Etapas ampliadas (ambas) | Incluye campos de último descuento |

### B. Hallazgos Críticos de Base de Datos:
1. **Discrepancia en `usuarios.role`:** Los 3 archivos SQL tienen definido `ENUM('cliente', 'admin')`. Sin embargo, el código PHP (`api_trabajadores.php` y `config.php`) requiere obligatoriamente el rol `'trabajador'`. La base de datos real en XAMPP sí lo tiene (`enum('cliente','admin','trabajador')`). Si un desarrollador nuevo importa cualquiera de los tres archivos SQL, la creación de trabajadores fallará.
2. **Omisión de `notificaciones` en los scripts SQL:** La tabla `notificaciones` está activamente en uso por `perfil.html`, `php/api_notificaciones.php` y `includes/config.php` (`crearNotificacionCliente`), pero **no está presente en ninguno de los archivos `.sql`**.
3. **Omisión de columnas en `interacciones`:** `register.php` y `crm_api.php` hacen inserts especificando `estado` y `prioridad`. Los archivos `.sql` no incluyen estas columnas.
4. **Falta de importación de `solicitudes_descuento` en el servidor local:** `lym_base_datos_completa.sql` contiene la tabla correcta `solicitudes_descuento`, pero en el servidor MySQL local todavía no se había ejecutado el `CREATE TABLE`, provocando el error `Table 'lym.solicitudes_descuento' doesn't exist` en pruebas de cupones.

---

## 10. PROPUESTA DE NUEVA ARQUITECTURA

Se propone una estructura modular desacoplada basada en separación de responsabilidades:

```text
LYM/
│
├── public/                              # Acceso público web (DocumentRoot recomendado)
│   ├── index.php                        # Entrada principal (carga tienda)
│   ├── assets/                          # Recursos estáticos globales
│   │   ├── css/
│   │   │   ├── tienda.css               # Proyecto.css renombrado y optimizado
│   │   │   └── gestor.css               # crm-style.css consolidado
│   │   ├── js/
│   │   │   └── evaluaciones.js          # Widget de reseñas
│   │   └── img/                         # Recursos gráficos organizados
│   │
│   ├── tienda/                          # Vistas de catálogo y detalle
│   │   ├── productos/                   # Detalle de tazas, sudaderas, tapetes, etc.
│   │   └── paquetes/                    # Detalle de paquetes (básico, viajero, etc.)
│   │
│   ├── cliente/                         # Vistas exclusivas del cliente autenticado
│   │   ├── carrito.html
│   │   ├── checkout.html
│   │   ├── compras.html
│   │   ├── ticket.html
│   │   └── perfil.html
│   │
│   └── auth/                            # Formatos de acceso y cierre
│
├── admin/                               # Panel de Administración general
│   └── index.php                        # Guardián de acceso y carga del gestor administrativo
│
├── crm/                                 # Módulo CRM desacoplado
│   ├── index.php                        # Entrada CRM para staff
│   └── views/                           # Vistas parciales (dashboard, clientes, actividades, etc.)
│
├── scm/                                 # Módulo SCM desacoplado
│   ├── index.php                        # Entrada SCM para staff
│   └── views/                           # Vistas parciales (inventario, logística, proveedores, etc.)
│
├── api/                                 # Capa de Servicios y Endpoints JSON
│   ├── auth/                            # login.php, logout.php, register.php, check_session.php
│   ├── clientes/                        # clientes.php, api_clientes_dropdown.php
│   ├── productos/                       # get_products.php, admin_products.php, api_evaluaciones.php
│   ├── pedidos/                         # api_cupones.php, pedidos, pagos
│   ├── notificaciones/                  # api_notificaciones.php
│   ├── crm/                             # crm_api.php
│   └── scm/                             # scm_api.php (nuevo endpoint backend para SCM)
│
├── config/                              # Configuración centralizada
│   ├── database.php                     # Conexión PDO y credenciales
│   ├── session.php                      # Parámetros de sesión y cookies
│   └── app.php                          # Constantes globales, URLs base y rutas
│
├── includes/                            # Lógica compartida reutilizable
│   ├── auth.php                         # Helpers de sesión y roles (isLoggedIn, isAdmin, isWorker)
│   ├── permissions.php                  # Reglas de autorización por rol
│   └── functions.php                    # Funciones auxiliares de sanitización y formateo
│
├── database/                            # Esquema formal y datos de inicialización
│   ├── schema.sql                       # DDL unificado, corregido y comprobado
│   └── seed.sql                         # Datos de prueba (usuarios admin/trabajador/cliente, catálogo)
│
├── docs/                                # Auditorías y guías técnicas
│   ├── AUDITORIA_LYM.md
│   └── ARQUITECTURA_PROPUESTA.md
│
└── README.md                            # Documentación completa de despliegue y uso
```

---

## 11. PLAN DE MIGRACIÓN POR FASES

La migración se llevará a cabo de manera estrictamente controlada para garantizar cero tiempo de inactividad y cero regresiones funcionales:

* **Fase 1 — Auditoría:** *(COMPLETADA)* Diagnóstico integral de archivos, dependencias, rutas y vulnerabilidades.
* **Fase 2 — Propuesta de Arquitectura:** *(COMPLETADA)* Redacción de `ARQUITECTURA_PROPUESTA.md` con mapeo exhaustivo de archivos y rutas.
* **Fase 3 — Aprobación:** Detención obligatoria para revisión y aprobación explícita del usuario.
* **Fase 4 — Reorganización de Backend y Configuración (`config/` e `includes/`):**
  * Desacoplar `includes/config.php` en `config/database.php`, `config/session.php` y `includes/auth.php`.
  * Mantener un puente de compatibilidad en `includes/config.php` para que ningún archivo existente se rompa.
* **Fase 5 — Estabilización del Esquema SQL (`database/schema.sql` y `database/seed.sql`):**
  * Unificar en `database/schema.sql` la estructura definitiva con soporte para `enum('cliente','admin','trabajador')`, tabla `notificaciones`, tabla `solicitudes_descuento` y columnas completas de `interacciones`.
  * Asegurar que la base de datos local en XAMPP cuente con todas las tablas operativas.
* **Fase 6 — Reorganización Progresiva de Endpoints (`api/`):**
  * Crear la estructura `api/` manteniendo redirecciones o proxies en `php/` para preservar compatibilidad con scripts no actualizados.
  * Implementar el backend faltante para SCM (`api/scm/` y `php/productos.php`).
* **Fase 7 — Reorganización de Módulos Frontend (`public/`, `crm/`, `scm/`):**
  * Actualizar las referencias de `loadExternalView()` en `gestor.html`.
  * Corregir el nombre de `prodrelacionados .html`.
  * Eliminar duplicaciones muertas (`LYM/`, `intranet.html`, `admin.html`).
* **Fase 8 — Endurecimiento de Seguridad:**
  * Eliminar backdoors (`php/fix_admin_account.php`, `php/info.php`).
  * Implementar protección CSRF y rate-limiting en autenticación.
* **Fase 9 — Verificación Integral y Documentación:**
  * Pruebas exhaustivas de todos los flujos de usuario (tienda, carrito, CRM, SCM, usuarios).
  * Creación del `README.md` final.
