<?php
/** Estructura del menú vertical: [titulo, icono, slug|url, hijos[], badge] */
function menu_items(): array
{
    return [
        ['label' => 'Principal'],
        ['t' => 'Dashboard', 'i' => 'bi-grid-1x2-fill', 'u' => 'dashboard.php', 'k' => 'dashboard', 'r' => 'dashboard'],
        ['label' => 'Operación'],
        ['t' => 'Punto de Venta', 'i' => 'bi-cart-check-fill', 'u' => 'modulo.php?m=pos', 'k' => 'pos', 'r' => 'pos', 'badge' => 'F2', 'badge_class' => 'new'],
        ['t' => 'Ventas', 'i' => 'bi-receipt-cutoff', 'u' => 'modulo.php?m=ventas', 'k' => 'ventas', 'r' => 'ventas'],
        ['t' => 'Cotizaciones', 'i' => 'bi-file-earmark-text-fill', 'u' => 'modulo.php?m=cotizaciones', 'k' => 'cotizaciones', 'r' => 'cotizaciones'],
        ['t' => 'Devoluciones', 'i' => 'bi-arrow-return-left', 'u' => 'modulo.php?m=devoluciones', 'k' => 'devoluciones', 'r' => 'devoluciones'],
        ['t' => 'Caja', 'i' => 'bi-safe2-fill', 'u' => 'modulo.php?m=caja', 'k' => 'caja', 'r' => 'caja'],
        ['label' => 'Inventario'],
        ['t' => 'Productos', 'i' => 'bi-box-seam-fill', 'k' => 'productos', 'sub' => [
            ['Listado de productos', 'productos'], ['Categorías', 'categorias'], ['Marcas', 'marcas'], ['Importar / exportar', 'importar'], ['Etiquetas de código de barras', 'etiquetas']]],
        ['t' => 'Almacén', 'i' => 'bi-building-fill', 'k' => 'almacen', 'sub' => [
            ['Kardex y ajustes', 'kardex'], ['Traspasos entre sucursales', 'traspasos'], ['Stock bajo', 'stock-bajo'], ['Por vencer', 'vencimientos']]],
        ['t' => 'Compras', 'i' => 'bi-bag-plus-fill', 'k' => 'compras', 'sub' => [
            ['Registrar compra', 'compras-nueva'], ['Historial de compras', 'compras'], ['Proveedores', 'proveedores']]],
        ['label' => 'Relaciones'],
        ['t' => 'Clientes', 'i' => 'bi-people-fill', 'k' => 'clientes', 'sub' => [
            ['Directorio de clientes', 'clientes'], ['Créditos y cobranzas', 'creditos']]],
        ['t' => 'Promociones', 'i' => 'bi-percent', 'u' => 'modulo.php?m=promociones', 'k' => 'promociones', 'r' => 'promociones'],
        ['label' => 'Finanzas'],
        ['t' => 'Gastos', 'i' => 'bi-wallet2', 'u' => 'modulo.php?m=gastos', 'k' => 'gastos', 'r' => 'gastos'],
        ['t' => 'Chat IA', 'i' => 'bi-chat-dots-fill', 'u' => 'modulo.php?m=chat', 'k' => 'chat', 'r' => 'chat'],
        ['t' => 'Asistente IA', 'i' => 'bi-stars', 'u' => 'modulo.php?m=asistente', 'k' => 'asistente', 'r' => 'asistente', 'badge' => 'IA', 'badge_class' => 'new'],
        ['t' => 'Reportes', 'i' => 'bi-bar-chart-line-fill', 'k' => 'reportes', 'sub' => [
            ['Ventas por período', 'rep-ventas'], ['Utilidades', 'rep-utilidad'], ['Más vendidos', 'rep-top'], ['Ventas por cajero', 'rep-cajero'], ['Valorización de inventario', 'rep-inventario'], ['Consolidado por sucursal', 'rep-sucursales']]],
        ['label' => 'Administración'],
        ['t' => 'Sucursales', 'i' => 'bi-shop-window', 'u' => 'modulo.php?m=sucursales', 'k' => 'sucursales', 'r' => 'sucursales'],
        ['t' => 'Auditoría', 'i' => 'bi-shield-check', 'u' => 'modulo.php?m=auditoria', 'k' => 'auditoria', 'r' => 'auditoria'],
        ['t' => 'Usuarios y roles', 'i' => 'bi-person-gear', 'u' => 'modulo.php?m=usuarios', 'k' => 'usuarios', 'r' => 'usuarios'],
        ['t' => 'Empresa', 'i' => 'bi-building-fill', 'u' => 'modulo.php?m=empresa', 'k' => 'empresa', 'r' => 'empresa'],
        ['t' => 'Copias de seguridad', 'i' => 'bi-database-fill-check', 'u' => 'modulo.php?m=respaldos', 'k' => 'respaldos', 'r' => 'respaldos'],
        ['t' => 'Configuración', 'i' => 'bi-gear-fill', 'u' => 'modulo.php?m=config', 'k' => 'config', 'r' => 'config'],
    ];
}

/** Filtra el menú según el rol del usuario */
function menu_visible(): array
{
    $out = []; $pend = null;
    foreach (menu_items() as $m) {
        if (isset($m['label'])) { $pend = $m; continue; }
        if (!empty($m['sub'])) {
            $m['sub'] = array_values(array_filter($m['sub'], fn($s) => can($s[1])));
            if (!$m['sub']) continue;
        } elseif (!can($m['r'])) continue;
        if ($pend) { $out[] = $pend; $pend = null; }
        $out[] = $m;
    }
    return $out;
}

/** Catálogo de nombres para el placeholder de módulos */
function modulo_titulo(string $slug): string
{
    foreach (menu_items() as $m) {
        if (($m['k'] ?? '') === $slug) return $m['t'];
        foreach ($m['sub'] ?? [] as $s) if ($s[1] === $slug) return $s[0];
    }
    return ucfirst($slug);
}
