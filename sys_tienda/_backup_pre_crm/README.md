# SysTienda · Sistema POS para tienda (PHP nativo + Bootstrap 5 + MySQL)

## Instalación (XAMPP / WAMP / Laragon)
1. Copia la carpeta `sys_tienda` dentro de `htdocs` (XAMPP) o `www` (WAMP/Laragon).
2. Inicia **Apache** y **MySQL**.
3. Si tu MySQL tiene contraseña, edítala en `config/config.php` (por defecto: usuario `root`, sin contraseña).
4. Abre `http://localhost/sys_tienda/install.php` → crea la BD `sys_tienda`, tablas y datos de demostración.
5. Entra en `http://localhost/sys_tienda/` y **elimina `install.php`** cuando termines.

## Usuarios de demostración
| Usuario | Contraseña | Rol |
|---|---|---|
| admin | admin123 | Administrador (todo) |
| cajero | cajero123 | Cajero (POS, ventas, caja, clientes, créditos) |
| luis | cajero123 | Cajero |

Para empezar a operar: **Configuración → Mantenimiento** y borra los datos de demostración
(movimientos, o también el catálogo). Cambia las contraseñas (avatar → Cambiar contraseña).

## Módulos
Dashboard · Punto de Venta (código de barras, ventas en espera, 6 formas de pago, crédito, ticket 80 mm) ·
Ventas (historial, detalle, anulación) · Caja (apertura, ingresos/egresos, arqueo y cierre) ·
Productos, Categorías, Marcas · Kardex y ajustes · Stock bajo · Por vencer · Compras y Proveedores ·
Clientes y Créditos/Cobranzas · Gastos · Reportes (ventas, utilidades, más vendidos, por cajero, inventario; CSV e impresión) ·
Usuarios y roles · Configuración (negocio, series, respaldo .sql, mantenimiento).

## Atajos
`F2` nueva venta · `F3` buscar producto · `F4` vaciar carrito · `F9` cobrar · `Ctrl+K` búsqueda global.

## Seguridad
Contraseñas con `password_hash`, consultas preparadas (PDO), token CSRF en toda escritura,
bloqueo de 10 min tras 5 intentos fallidos, permisos por rol, transacciones en ventas/compras/ajustes.

## Funciona sin internet
Bootstrap, iconos, Chart.js y la fuente Inter están incluidos en `assets/vendor`.

---

## Novedades v1.1

**Varias sucursales**: cada sucursal tiene su propio stock, caja, series de comprobantes (T/B/F + número), compras y gastos. El administrador cambia de sucursal (o ve «Todas») desde el selector superior; cajeros y almaceneros quedan fijos en su sucursal. Módulo *Traspasos* para mover mercadería entre sucursales (kardex en ambas) y *Reporte consolidado por sucursal*.

**Productos avanzados**: foto del producto (JPG/PNG/WEBP ≤ 1 MB), importación/exportación CSV con plantilla, etiquetas de código de barras imprimibles (EAN-13 / CODE-128) y precio por mayor automático en el POS (`cantidad_mayor` + `precio_mayor`).

**Cotizaciones y devoluciones**: cotizaciones con vigencia, impresión A4, envío por WhatsApp y conversión a venta (`POS ?cot=ID`). Devoluciones parciales con reintegro de stock y reembolso en efectivo (egreso de caja), mismo medio o descuento de deuda; los reportes y el dashboard muestran ventas netas.

**Promociones y fidelización**: descuentos por producto/categoría/toda la tienda (%, S/ por unidad, 2x1 / NxM) aplicados automáticamente; puntos (1 por cada S/ 10, valor S/ 0.10, configurable), canje en el POS y descuento de cumpleaños.

**Operación y control**: reporte imprimible de cierre de caja, comprobante por WhatsApp y módulo de *Auditoría* (quién hizo qué).

> Al actualizar desde v1.0 ejecuta de nuevo `install.php` (el esquema cambió; reinstalar borra los datos de demostración).
> Usuarios demo: admin/admin123 · cajero/cajero123 (Central) · luis/cajero123 (Norte).


## v1.3 · Impresión térmica, respaldos y seguridad

**Impresión térmica (Configuración → Impresión)**
- Modos: *navegador* (vista de impresión, con `--kiosk-printing` en Chrome para imprimir sin diálogo), *red* (IP:9100) y *local* (impresora compartida de Windows `\\localhost\TICKET`, COM/LPT o `/dev/usb/lp0`).
- Ticket en ESC/POS (58 u 80 mm), impresión automática al cobrar, apertura del cajón monedero (pulso ESC p) en ventas en efectivo, botón «Abrir cajón» en Caja y cierre de caja en ticket. Botones de prueba incluidos.
- Si la impresora no responde, el sistema avisa y abre la vista del navegador como respaldo.

**Respaldos (Configuración → Respaldos)**
- Respaldo automático sin cron (cada 1 h … 1 semana, mientras alguien usa el sistema), conservando los últimos N; copia opcional a otra carpeta (USB, nube sincronizada).
- Respaldo manual, descarga, subida de un .sql/.sql.gz y **restauración** (pide contraseña + escribir RESTAURAR y guarda antes un respaldo «previo»).
- Para respaldar aunque nadie use el sistema: Programador de tareas de Windows → `php C:\laragon\www\sys_tienda\backup_cron.php`.

**Seguridad (Configuración → Seguridad)**
- Contraseñas: mínimo 8, letras y números, sin claves comunes; las de demostración obligan a cambiarlas al ingresar (`cambiar_clave.php`). Un usuario nuevo o con clave restablecida por el administrador también debe cambiarla.
- Bloqueo por usuario (5 intentos) y por equipo (20 intentos / 15 min), cierre de sesión por inactividad, cookies seguras en HTTPS, cabeceras de seguridad y panel de revisión.
- Producción: copia `config/local.example.php` a `config/local.php` (APP_ENV=produccion + usuario de BD propio). Con eso `install.php` queda bloqueado tras instalar, no se muestran errores y se activa HSTS.
- `.htaccess` bloquea `config/`, `includes/`, `database/`, `backups/` y archivos .sql/.lock/.log. En Nginx hay que replicar esas reglas.

## v1.4 · Empresa y Copias de seguridad
- **Empresa** (Administración → Empresa): datos de la empresa, logo (menú, login, tickets y cotizaciones, ticket térmico), moneda (símbolo, código, decimales, posición), separadores decimal y de miles con vista previa, nombre y porcentaje del impuesto, zona horaria, filas por página, días de alerta de vencimiento y stock mínimo por defecto. Los decimales solo cambian la presentación; los cálculos internos usan 2.
- **Copias de seguridad** (Administración → Copias de seguridad): crear, descargar, subir, restaurar y programar copias; asistente de restablecimiento (solo movimientos / + catálogo / empresa nueva) con contraseña, palabra de confirmación y copia previa automática.
- Configuración conserva Ventas y fidelización, Comprobantes, Impresión y Seguridad.

## v1.5 · Móvil y PWA
- Instalable como aplicación (Android/iOS/PC): menú del usuario → «Instalar aplicación». Requiere HTTPS (o localhost); en iPhone: Compartir → Añadir a pantalla de inicio.
- Barra de navegación inferior en móvil (Inicio, Vender, Ventas, Caja, Menú), POS con carrito deslizable, formularios a pantalla completa, tablas con desplazamiento táctil y campos de 16 px (sin zoom en iOS).
- Service worker (`sw.js`): guarda solo archivos estáticos; las páginas y la API siempre van a la red. Sin conexión muestra `offline.html` y un aviso rojo. Las ventas requieren conexión.
- `manifest.php` toma el nombre de la empresa de la configuración.

## v1.6 · Asistente de reportes con IA
- Menú → **Asistente IA** (solo administrador): preguntas en español → consulta SQL → tabla, gráfico (barras/líneas/torta), resumen en lenguaje natural, exportación a CSV y consultas favoritas (se repiten sin gastar IA).
- Proveedor gratuito configurable (Google Gemini, Groq, OpenRouter, Ollama local u otro compatible con OpenAI). La clave se guarda cifrada (`config/secret.key`, no se incluye en los respaldos ni en el zip).
- Seguridad: la IA solo ve vistas de lectura `v_ia_*` (sin usuarios ni contraseñas); el SQL se valida (solo SELECT, sin comentarios, sin tablas reales, sin funciones peligrosas), corre en transacción de solo lectura con límite de 8 s y 500 filas.
- Privacidad: al proveedor se envía la pregunta y, con el resumen activado, hasta 25 filas del resultado. Con Ollama nada sale del equipo.

## v1.7 · Chat IA
- Burbuja flotante en todas las pantallas (solo administrador) y página «Chat IA» en el menú. Consulta productos, ventas, clientes, proveedores, compras, gastos y explica cómo usar el sistema. Solo consulta: no registra ni modifica datos.
- Comparte proveedor, clave y capa de seguridad con el Asistente de reportes. Activación con solo pegar la clave gratuita de Gemini.
