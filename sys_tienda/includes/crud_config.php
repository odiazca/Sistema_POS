<?php
/**
 * Configuración de los módulos CRUD (catálogos).
 * cols:   [campo, título, tipo]   tipos: text, money, num, date, estado, color, icon, stock, cat, tag
 * fields: [campo, etiqueta, tipo, ancho(1-12), opciones]  tipos: text, number, money, select, textarea, date, color, password, email, switch
 */
function crud_entities(): array
{
    $cats = "SELECT id, nombre FROM categorias WHERE estado=1 ORDER BY nombre";
    $marcas = "SELECT id, nombre FROM marcas WHERE estado=1 ORDER BY nombre";
    $roles = "SELECT id, nombre FROM roles ORDER BY id";
    $sucs = "SELECT id, nombre FROM sucursales WHERE estado=1 ORDER BY id";
    $prods = "SELECT id, nombre FROM productos WHERE estado=1 ORDER BY nombre";
    $unidades = ['UND' => 'Unidad', 'KG' => 'Kilogramo', 'LT' => 'Litro', 'PQT' => 'Paquete', 'BLS' => 'Bolsa', 'BOT' => 'Botella', 'LAT' => 'Lata', 'CJA' => 'Caja', 'DOC' => 'Docena'];

    return [
    'productos' => [
        'table' => 'productos',
        'title' => 'Productos', 'singular' => 'Producto', 'icon' => 'bi-box-seam', 'sub' => 'Catálogo de productos con precios, stock y vencimientos',
        'write' => [1, 3], 'delete' => false,
        'select' => "SELECT p.*, " . stock_col() . " AS stock, p.stock AS stock_total, c.nombre categoria, c.color cat_color, m.nombre marca FROM productos p JOIN categorias c ON c.id=p.categoria_id LEFT JOIN marcas m ON m.id=p.marca_id",
        'count' => "SELECT COUNT(*) FROM productos p JOIN categorias c ON c.id=p.categoria_id",
        'search' => ['p.nombre', 'p.codigo', 'p.codigo_barras'], 'order' => 'p.nombre', 'alias' => 'p',
        'filters' => [['categoria_id', 'Categoría', 'p.categoria_id', $cats], ['stock', 'Stock', null, ['bajo' => 'Stock bajo', 'agotado' => 'Agotados', 'vence' => 'Por vencer (30 d)']]],
        'cols' => [['codigo', 'Código', 'text'], ['nombre', 'Producto', 'prod'], ['categoria', 'Categoría', 'cat'], ['precio_compra', 'Costo', 'money'], ['precio_venta', 'P. venta', 'money'], ['stock', 'Stock', 'stock'], ['estado', 'Estado', 'estado']],
        'fields' => [
            ['codigo', 'Código interno', 'text', 4, null, true], ['codigo_barras', 'Código de barras', 'text', 4], ['unidad', 'Unidad', 'select', 4, $unidades, true],
            ['nombre', 'Nombre del producto', 'text', 12, null, true],
            ['categoria_id', 'Categoría', 'select', 6, $cats, true], ['marca_id', 'Marca', 'select', 6, $marcas],
            ['precio_compra', 'Precio de compra (costo)', 'money', 4, null, true], ['precio_venta', 'Precio de venta', 'money', 4, null, true], ['precio_mayor', 'Precio por mayor', 'money', 4],
            ['cantidad_mayor', 'Cant. mínima por mayor', 'number', 4], ['stock', 'Stock inicial (en la sucursal activa)', 'number', 4, null, false, 'only_new'], ['stock_minimo', 'Stock mínimo (alerta)', 'number', 4, null, true],
            ['fecha_vencimiento', 'Fecha de vencimiento', 'date', 4],
            ['descripcion', 'Descripción', 'textarea', 12], ['estado', 'Producto activo', 'switch', 12],
        ],
        'defaults' => fn() => ['codigo' => 'P' . str_pad((string)((int)db()->query('SELECT COALESCE(MAX(id),0)+1 FROM productos')->fetchColumn()), 4, '0', STR_PAD_LEFT), 'unidad' => 'UND', 'stock' => 0, 'stock_minimo' => (float)cfg('stock_minimo_defecto', '5'), 'estado' => 1],
        'validate' => function (array $d, int $id): void {
            if ($d['precio_venta'] < $d['precio_compra']) throw new RuntimeException('El precio de venta es menor al costo. Verifica los precios.');
            if (!empty($d['precio_mayor']) && $d['precio_mayor'] < $d['precio_compra']) throw new RuntimeException('El precio por mayor es menor al costo.');
            if (!empty($d['precio_mayor']) && empty($d['cantidad_mayor'])) throw new RuntimeException('Indica desde qué cantidad aplica el precio por mayor.');
            if (!$id && ($d['stock'] ?? 0) > 0 && suc() === 0) throw new RuntimeException('Para registrar stock inicial selecciona una sucursal concreta.');
        },
        'before_save' => function (array $d, int $id): array { if (!$id) { $st = (float)($d['stock'] ?? 0); $d['stock'] = 0; $GLOBALS['_stock_ini'] = $st; } $d['cantidad_mayor'] = $d['cantidad_mayor'] ?? 0; return $d; },
        'after_save' => function (int $id, array $d, bool $new): void {
            $st = (float)($GLOBALS['_stock_ini'] ?? 0);
            if ($new && $st > 0) { stock_mover($id, suc_w(), $st); kardex($id, 'ENTRADA', $st, 'Stock inicial'); }
        },
    ],
    'categorias' => [
        'fem' => true,
        'table' => 'categorias',
        'title' => 'Categorías', 'singular' => 'Categoría', 'icon' => 'bi-tags', 'sub' => 'Agrupa tus productos para ventas más rápidas', 'write' => [1, 3], 'delete' => false,
        'select' => "SELECT c.*, (SELECT COUNT(*) FROM productos p WHERE p.categoria_id=c.id) productos FROM categorias c", 'count' => "SELECT COUNT(*) FROM categorias c",
        'search' => ['c.nombre'], 'order' => 'c.nombre', 'alias' => 'c',
        'cols' => [['nombre', 'Categoría', 'icon'], ['color', 'Color', 'color'], ['productos', 'Productos', 'num'], ['estado', 'Estado', 'estado']],
        'fields' => [['nombre', 'Nombre', 'text', 12, null, true], ['icono', 'Ícono (Bootstrap Icons)', 'text', 8], ['color', 'Color', 'color', 4], ['estado', 'Activa', 'switch', 12]],
        'defaults' => fn() => ['icono' => 'bi-tag', 'color' => '#5b5ef4', 'estado' => 1],
    ],
    'marcas' => [
        'fem' => true,
        'table' => 'marcas',
        'title' => 'Marcas', 'singular' => 'Marca', 'icon' => 'bi-award', 'sub' => 'Marcas de los productos', 'write' => [1, 3], 'delete' => false,
        'select' => "SELECT m.*, (SELECT COUNT(*) FROM productos p WHERE p.marca_id=m.id) productos FROM marcas m", 'count' => "SELECT COUNT(*) FROM marcas m",
        'search' => ['m.nombre'], 'order' => 'm.nombre', 'alias' => 'm',
        'cols' => [['nombre', 'Marca', 'text'], ['productos', 'Productos', 'num'], ['estado', 'Estado', 'estado']],
        'fields' => [['nombre', 'Nombre', 'text', 12, null, true], ['estado', 'Activa', 'switch', 12]],
        'defaults' => fn() => ['estado' => 1],
    ],
    'proveedores' => [
        'table' => 'proveedores',
        'title' => 'Proveedores', 'singular' => 'Proveedor', 'icon' => 'bi-truck', 'sub' => 'Empresas que te abastecen', 'write' => [1, 3], 'delete' => false,
        'select' => "SELECT v.* FROM proveedores v", 'count' => "SELECT COUNT(*) FROM proveedores v",
        'search' => ['v.razon_social', 'v.ruc', 'v.contacto'], 'order' => 'v.razon_social', 'alias' => 'v',
        'cols' => [['razon_social', 'Razón social', 'text'], ['ruc', 'RUC', 'text'], ['contacto', 'Contacto', 'text'], ['telefono', 'Teléfono', 'text'], ['estado', 'Estado', 'estado']],
        'fields' => [['razon_social', 'Razón social', 'text', 8, null, true], ['ruc', 'RUC', 'text', 4], ['contacto', 'Persona de contacto', 'text', 6], ['telefono', 'Teléfono', 'text', 6],
                     ['email', 'Correo', 'email', 6], ['direccion', 'Dirección', 'text', 6], ['estado', 'Activo', 'switch', 12]],
        'defaults' => fn() => ['estado' => 1],
    ],
    'clientes' => [
        'table' => 'clientes',
        'title' => 'Clientes', 'singular' => 'Cliente', 'icon' => 'bi-people', 'sub' => 'Directorio de clientes y su saldo pendiente', 'write' => [1, 2], 'delete' => false, 'protected' => [1],
        'select' => "SELECT c.* FROM clientes c", 'count' => "SELECT COUNT(*) FROM clientes c",
        'search' => ['c.nombre', 'c.documento', 'c.telefono'], 'order' => 'c.nombre', 'alias' => 'c',
        'cols' => [['nombre', 'Cliente', 'text'], ['documento', 'Documento', 'doc'], ['telefono', 'Teléfono', 'text'], ['puntos', 'Puntos', 'num'], ['saldo_deuda', 'Deuda', 'debt'], ['estado', 'Estado', 'estado']],
        'fields' => [['tipo_doc', 'Tipo doc.', 'select', 3, ['DNI' => 'DNI', 'RUC' => 'RUC', 'CE' => 'C. Extranjería', 'OTRO' => 'Otro']], ['documento', 'N° documento', 'text', 4], ['nombre', 'Nombre / Razón social', 'text', 12, null, true],
                     ['telefono', 'Teléfono', 'text', 4], ['email', 'Correo', 'email', 4], ['fecha_nacimiento', 'Cumpleaños', 'date', 4], ['limite_credito', 'Límite de crédito', 'money', 4], ['direccion', 'Dirección', 'text', 8],
                     ['origen', 'Origen del cliente (CRM)', 'select', 6, ['Tienda' => 'Tienda', 'Referido' => 'Referido', 'WhatsApp' => 'WhatsApp', 'Redes sociales' => 'Redes sociales', 'Volante / publicidad' => 'Volante / publicidad', 'Otro' => 'Otro']],
                     ['acepta_marketing', 'Acepta mensajes y promociones por WhatsApp', 'switch', 6], ['estado', 'Activo', 'switch', 12]],
        'defaults' => fn() => ['tipo_doc' => 'DNI', 'limite_credito' => 0, 'acepta_marketing' => 1, 'estado' => 1],
        'validate' => function (array $d, int $id): void {
            if (($d['tipo_doc'] ?? '') === 'DNI' && !empty($d['documento']) && !preg_match('/^\d{8}$/', $d['documento'])) throw new RuntimeException('El DNI debe tener 8 dígitos.');
            if (($d['tipo_doc'] ?? '') === 'RUC' && !empty($d['documento']) && !preg_match('/^\d{11}$/', $d['documento'])) throw new RuntimeException('El RUC debe tener 11 dígitos.');
        },
    ],
    'gastos' => [
        'table' => 'gastos',
        'title' => 'Gastos', 'singular' => 'Gasto', 'icon' => 'bi-wallet2', 'sub' => 'Registro de egresos operativos del negocio', 'write' => [1], 'delete' => false,
        'suc' => 'g.sucursal_id', 'select' => "SELECT g.*, (SELECT nombre FROM sucursales WHERE id=g.sucursal_id) sucursal FROM gastos g", 'count' => "SELECT COUNT(*) FROM gastos g",
        'search' => ['g.concepto', 'g.categoria'], 'order' => 'g.fecha DESC, g.id', 'alias' => 'g', 'dates' => 'g.fecha', 'sum' => ['Total en el período', 'SUM(CASE WHEN g.estado=1 THEN g.monto ELSE 0 END)'],
        'filters' => [['categoria', 'Categoría', 'g.categoria', ['Servicios' => 'Servicios', 'Insumos' => 'Insumos', 'Transporte' => 'Transporte', 'Planilla' => 'Planilla', 'Alquiler' => 'Alquiler', 'Impuestos' => 'Impuestos', 'Mantenimiento' => 'Mantenimiento', 'General' => 'General']]],
        'cols' => [['fecha', 'Fecha', 'date'], ['concepto', 'Concepto', 'text'], ['categoria', 'Categoría', 'tag'], ['sucursal', 'Sucursal', 'tag'], ['monto', 'Monto', 'money'], ['estado', 'Estado', 'estado']],
        'fields' => [['concepto', 'Concepto', 'text', 12, null, true], ['categoria', 'Categoría', 'select', 4, ['Servicios' => 'Servicios', 'Insumos' => 'Insumos', 'Transporte' => 'Transporte', 'Planilla' => 'Planilla', 'Alquiler' => 'Alquiler', 'Impuestos' => 'Impuestos', 'Mantenimiento' => 'Mantenimiento', 'General' => 'General'], true],
                     ['monto', 'Monto (' . moneda() . ')', 'money', 4, null, true], ['fecha', 'Fecha', 'date', 4, null, true], ['estado', 'Gasto activo (cuenta en reportes)', 'switch', 12]],
        'defaults' => fn() => ['categoria' => 'General', 'fecha' => date('Y-m-d'), 'estado' => 1],
        'before_save' => function (array $d, int $id): array { if (!$id) { $d['usuario_id'] = usuario()['id']; $d['sucursal_id'] = suc_w(); } return $d; },
        'extra_cols' => ['usuario_id'],
    ],
    'usuarios' => [
        'table' => 'usuarios',
        'title' => 'Usuarios y roles', 'singular' => 'Usuario', 'icon' => 'bi-person-gear', 'sub' => 'Administra quién accede al sistema y con qué permisos', 'write' => [1], 'delete' => false,
        'select' => "SELECT u.id,u.rol_id,u.sucursal_id,(SELECT nombre FROM sucursales WHERE id=u.sucursal_id) sucursal,u.nombres,u.apellidos,u.usuario,u.email,u.estado,u.ultimo_acceso, r.nombre rol FROM usuarios u JOIN roles r ON r.id=u.rol_id", 'count' => "SELECT COUNT(*) FROM usuarios u JOIN roles r ON r.id=u.rol_id",
        'search' => ['u.nombres', 'u.apellidos', 'u.usuario', 'u.email'], 'order' => 'u.nombres', 'alias' => 'u',
        'filters' => [['rol_id', 'Rol', 'u.rol_id', $roles]],
        'cols' => [['nombres', 'Usuario', 'user'], ['usuario', 'Login', 'text'], ['rol', 'Rol', 'tag'], ['sucursal', 'Sucursal', 'tag'], ['ultimo_acceso', 'Último acceso', 'datetime'], ['estado', 'Estado', 'estado']],
        'fields' => [['nombres', 'Nombres', 'text', 6, null, true], ['apellidos', 'Apellidos', 'text', 6, null, true], ['usuario', 'Usuario (login)', 'text', 6, null, true], ['rol_id', 'Rol', 'select', 6, $roles, true],
                     ['sucursal_id', 'Sucursal asignada', 'select', 6, $sucs, true], ['password', 'Contraseña', 'password', 6], ['email', 'Correo', 'email', 12], ['estado', 'Usuario activo', 'switch', 12]],
        'defaults' => fn() => ['rol_id' => 2, 'sucursal_id' => suc() ?: 1, 'estado' => 1],
        'validate' => function (array $d, int $id): void {
            if (!$id && ($d['password'] ?? '') === '') throw new RuntimeException('La contraseña es obligatoria.');
            if (($d['password'] ?? '') !== '' && ($err = validar_clave($d['password'], $d['usuario'] ?? ''))) throw new RuntimeException($err);
            if ($id === usuario()['id'] && (int)$d['estado'] === 0) throw new RuntimeException('No puedes desactivar tu propio usuario.');
            if ($id === usuario()['id'] && (int)$d['rol_id'] !== 1) throw new RuntimeException('No puedes quitarte el rol de administrador.');
            if (!preg_match('/^[a-zA-Z0-9._-]{3,30}$/', $d['usuario'])) throw new RuntimeException('El usuario solo admite letras, números, punto y guion (3-30).');
        },
        'before_save' => function (array $d, int $id): array {
            if (($d['password'] ?? '') !== '') {
                $d['password'] = password_hash($d['password'], PASSWORD_DEFAULT);
                if ($id !== usuario()['id']) $d['cambiar_clave'] = 1;   // el usuario debe elegir su propia contraseña al ingresar
            } else unset($d['password']);
            return $d;
        },
        'hide_in_get' => ['password'],
    ],
    'sucursales' => [
        'fem' => true,
        'table' => 'sucursales',
        'title' => 'Sucursales', 'singular' => 'Sucursal', 'icon' => 'bi-shop-window', 'sub' => 'Locales del negocio: cada uno con su stock, caja y series de comprobantes', 'write' => [1], 'delete' => false,
        'select' => "SELECT s.*, (SELECT COUNT(*) FROM usuarios u WHERE u.sucursal_id=s.id) usuarios FROM sucursales s", 'count' => "SELECT COUNT(*) FROM sucursales s",
        'search' => ['s.nombre', 's.direccion'], 'order' => 's.id', 'alias' => 's',
        'cols' => [['nombre', 'Sucursal', 'text'], ['direccion', 'Dirección', 'text'], ['telefono', 'Teléfono', 'text'], ['usuarios', 'Usuarios', 'num'], ['estado', 'Estado', 'estado']],
        'fields' => [['nombre', 'Nombre de la sucursal', 'text', 12, null, true], ['direccion', 'Dirección', 'text', 8], ['telefono', 'Teléfono', 'text', 4], ['estado', 'Sucursal activa', 'switch', 12]],
        'defaults' => fn() => ['estado' => 1],
        'after_save' => function (int $id, array $d, bool $new): void {
            if (!$new) return;
            // series propias de la nueva sucursal (T/B/F + número de sucursal)
            foreach (['TICKET' => 'T', 'BOLETA' => 'B', 'FACTURA' => 'F'] as $t => $l)
                q('INSERT INTO series_comprobantes (sucursal_id,tipo,serie,correlativo) VALUES (?,?,?,0)', [$id, $t, $l . str_pad((string)$id, 3, '0', STR_PAD_LEFT)]);
        },
    ],
    'promociones' => [
        'fem' => true,
        'table' => 'promociones',
        'title' => 'Promociones', 'singular' => 'Promoción', 'icon' => 'bi-percent', 'sub' => 'Descuentos por producto o categoría y ofertas 2x1 / NxM, aplicados automáticamente en el POS', 'write' => [1], 'delete' => false,
        'select' => "SELECT pr.*, COALESCE(p.nombre, c.nombre, 'Toda la tienda') objetivo,
            CASE pr.tipo WHEN 'PORCENTAJE' THEN CONCAT(TRIM(TRAILING '.' FROM TRIM(TRAILING '0' FROM pr.valor)),'% dscto.') WHEN 'MONTO' THEN CONCAT('S/ ',pr.valor,' por unidad') ELSE CONCAT('Lleva ',pr.lleva,' paga ',pr.paga) END regla,
            (CURDATE() BETWEEN pr.fecha_inicio AND pr.fecha_fin) vigente, (SELECT nombre FROM sucursales WHERE id=pr.sucursal_id) sucursal
            FROM promociones pr LEFT JOIN productos p ON p.id=pr.producto_id LEFT JOIN categorias c ON c.id=pr.categoria_id",
        'count' => "SELECT COUNT(*) FROM promociones pr", 'search' => ['pr.nombre'], 'order' => 'pr.fecha_fin DESC, pr.id', 'alias' => 'pr',
        'cols' => [['nombre', 'Promoción', 'text'], ['objetivo', 'Aplica a', 'text'], ['regla', 'Regla', 'tag'], ['fecha_fin', 'Vence', 'date'], ['sucursal', 'Sucursal', 'text'], ['estado', 'Estado', 'estado']],
        'fields' => [
            ['nombre', 'Nombre de la promoción', 'text', 12, null, true],
            ['tipo', 'Tipo', 'select', 4, ['PORCENTAJE' => '% de descuento', 'MONTO' => moneda() . ' de descuento por unidad', 'NXM' => 'Lleva N paga M (2x1, 3x2…)'], true],
            ['alcance', 'Aplica a', 'select', 4, ['PRODUCTO' => 'Un producto', 'CATEGORIA' => 'Una categoría', 'TODO' => 'Toda la tienda'], true],
            ['sucursal_id', 'Sucursal', 'select', 4, $sucs],
            ['producto_id', 'Producto (si aplica)', 'select', 6, $prods], ['categoria_id', 'Categoría (si aplica)', 'select', 6, $cats],
            ['valor', 'Valor (% o ' . moneda() . ')', 'number', 4], ['lleva', 'Lleva (N)', 'number', 4], ['paga', 'Paga (M)', 'number', 4],
            ['fecha_inicio', 'Desde', 'date', 6, null, true], ['fecha_fin', 'Hasta', 'date', 6, null, true], ['estado', 'Promoción activa', 'switch', 12],
        ],
        'defaults' => fn() => ['tipo' => 'PORCENTAJE', 'alcance' => 'PRODUCTO', 'valor' => 10, 'lleva' => 2, 'paga' => 1, 'fecha_inicio' => date('Y-m-d'), 'fecha_fin' => date('Y-m-d', strtotime('+30 days')), 'estado' => 1],
        'validate' => function (array $d, int $id): void {
            if ($d['fecha_fin'] < $d['fecha_inicio']) throw new RuntimeException('La fecha final no puede ser anterior a la inicial.');
            if ($d['alcance'] === 'PRODUCTO' && empty($d['producto_id'])) throw new RuntimeException('Selecciona el producto de la promoción.');
            if ($d['alcance'] === 'CATEGORIA' && empty($d['categoria_id'])) throw new RuntimeException('Selecciona la categoría de la promoción.');
            if ($d['tipo'] === 'PORCENTAJE' && ($d['valor'] <= 0 || $d['valor'] > 100)) throw new RuntimeException('El porcentaje debe estar entre 1 y 100.');
            if ($d['tipo'] === 'MONTO' && $d['valor'] <= 0) throw new RuntimeException('Indica el descuento en soles por unidad.');
            if ($d['tipo'] === 'NXM' && !((int)$d['lleva'] >= 2 && (int)$d['paga'] >= 1 && (int)$d['paga'] < (int)$d['lleva'])) throw new RuntimeException('En «Lleva N paga M», N debe ser ≥ 2 y M menor que N (ej. lleva 2 paga 1).');
        },
        'before_save' => function (array $d, int $id): array {
            if ($d['alcance'] !== 'PRODUCTO') $d['producto_id'] = null;
            if ($d['alcance'] !== 'CATEGORIA') $d['categoria_id'] = null;
            if ($d['tipo'] === 'NXM') { $d['valor'] = 0; } else { $d['lleva'] = 0; $d['paga'] = 0; }
            $d['lleva'] = (int)$d['lleva']; $d['paga'] = (int)$d['paga']; $d['valor'] = $d['valor'] ?? 0;
            return $d;
        },
    ],
    ];
}

function crud_opts($o): array
{
    if (is_string($o)) return db()->query($o)->fetchAll(PDO::FETCH_KEY_PAIR);
    return (array)$o;
}

/** Configuración serializable para el front-end */
function crud_public(string $key, array $c): array
{
    $fields = [];
    foreach ($c['fields'] as $f) $fields[] = ['name' => $f[0], 'label' => $f[1], 'type' => $f[2], 'w' => $f[3] ?? 12, 'options' => isset($f[4]) ? crud_opts($f[4]) : null, 'req' => !empty($f[5]), 'flag' => $f[6] ?? null];
    $filters = [];
    foreach ($c['filters'] ?? [] as $f) $filters[] = ['key' => $f[0], 'label' => $f[1], 'options' => crud_opts($f[3])];
    return ['entity' => $key, 'title' => $c['title'], 'singular' => $c['singular'], 'cols' => $c['cols'], 'fields' => $fields, 'filters' => $filters,
            'write' => in_array(usuario()['rol_id'], $c['write'], true), 'delete' => !empty($c['delete']), 'protected' => $c['protected'] ?? [],
            'nuevo' => !empty($c['fem']) ? 'Nueva' : 'Nuevo', 'dates' => !empty($c['dates']), 'sum' => $c['sum'][0] ?? null, 'hasEstado' => (bool)array_filter($c['fields'], fn($f) => $f[0] === 'estado')];
}
