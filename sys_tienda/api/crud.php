<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/crud_config.php';

$ents = crud_entities();
$e = $_GET['e'] ?? (body()['e'] ?? '');
if (!isset($ents[$e])) json_fail('Entidad no válida', 404);
$c = $ents[$e];
$a = $_GET['a'] ?? (body()['a'] ?? 'list');
$isWrite = in_array($a, ['save', 'toggle', 'delete'], true);
api_guard([], $isWrite);
if (!can($e)) json_fail('Sin permiso', 403);
if ($isWrite && !in_array(usuario()['rol_id'], $c['write'], true)) json_fail('Tu rol solo permite consultar este módulo.', 403);
$pdo = db(); $alias = $c['alias'];

function field_defs(array $c): array { $o = []; foreach ($c['fields'] as $f) $o[$f[0]] = $f; return $o; }

switch ($a) {
case 'list':
    $where = []; $p = [];
    if (($s = trim($_GET['q'] ?? '')) !== '') {
        $parts = []; foreach ($c['search'] as $col) { $parts[] = "$col LIKE ?"; $p[] = "%$s%"; }
        $where[] = '(' . implode(' OR ', $parts) . ')';
    }
    foreach ($c['filters'] ?? [] as $f) {
        $v = $_GET['f_' . $f[0]] ?? ''; if ($v === '') continue;
        if ($f[0] === 'stock') {
            $sk = stock_col($alias);
            $where[] = ['bajo' => "$sk<=$alias.stock_minimo", 'agotado' => "$sk<=0", 'vence' => "($alias.fecha_vencimiento IS NOT NULL AND $alias.fecha_vencimiento<=CURDATE()+INTERVAL 30 DAY)"][$v] ?? '1=1';
        } else { $where[] = "{$f[2]} = ?"; $p[] = $v; }
    }
    if (($_GET['estado'] ?? '') !== '' && array_filter($c['fields'], fn($f) => $f[0] === 'estado')) { $where[] = "$alias.estado = ?"; $p[] = (int)$_GET['estado']; }
    if (!empty($c['dates'])) {
        if (!empty($_GET['desde'])) { $where[] = "{$c['dates']} >= ?"; $p[] = $_GET['desde']; }
        if (!empty($_GET['hasta'])) { $where[] = "{$c['dates']} <= ?"; $p[] = $_GET['hasta']; }
    }
    if (!empty($c['suc']) && suc() > 0) $where[] = $c['suc'] . ' = ' . suc();
    $w = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    $per = in_array((int)cfg('filas_pagina', '12'), [10, 12, 15, 20, 25, 50], true) ? (int)cfg('filas_pagina', '12') : 12; $page = max(1, (int)($_GET['page'] ?? 1));
    $total = (int)q($c['count'] . $w, $p)->fetchColumn();
    $pages = max(1, (int)ceil($total / $per)); $page = min($page, $pages);
    $order = preg_match('/DESC/i', $c['order']) ? $c['order'] : $c['order'] . ' ASC';
    $rows = q($c['select'] . $w . " ORDER BY $order LIMIT $per OFFSET " . (($page - 1) * $per), $p)->fetchAll();
    $out = ['ok' => true, 'rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per' => $per];
    if (!empty($c['sum'])) $out['sum'] = (float)q("SELECT COALESCE({$c['sum'][1]},0) FROM {$c['table']} $alias" . $w, $p)->fetchColumn();
    json_out($out);

case 'get':
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) json_out(['ok' => true, 'row' => ($c['defaults'])()]);
    $r = q($c['select'] . " WHERE $alias.id = ?", [$id])->fetch();
    if (!$r) json_fail('Registro no encontrado', 404);
    foreach ($c['hide_in_get'] ?? [] as $h) unset($r[$h]);
    json_out(['ok' => true, 'row' => $r]);

case 'save':
    $b = body(); $id = (int)($b['id'] ?? 0); $in = $b['data'] ?? []; $new = $id === 0;
    if ($id && in_array($id, $c['protected'] ?? [], true)) json_fail('Este registro del sistema no se puede modificar.');
    $d = [];
    foreach (field_defs($c) as $name => $f) {
        [, $label, $type, , , $req] = array_pad($f, 6, null); $flag = $f[6] ?? null;
        if ($flag === 'only_new' && !$new) continue;
        $v = $in[$name] ?? null;
        if ($type === 'switch') { $d[$name] = !empty($v) && $v !== '0' ? 1 : 0; continue; }
        if (is_string($v)) $v = trim($v);
        if ($v === '' || $v === null) {
            if ($req && $type !== 'password') json_fail("El campo «$label» es obligatorio.");
            $d[$name] = $type === 'password' ? '' : (in_array($type, ['number', 'money'], true) && $req ? 0 : null); continue;
        }
        if (in_array($type, ['number', 'money'], true)) { if (!is_numeric($v) || $v < 0) json_fail("«$label» debe ser un número válido (≥ 0)."); $v = round((float)$v, 2); }
        if ($type === 'select' && is_numeric($v) && !in_array($name, ['unidad'], true) && substr($name, -3) === '_id') $v = (int)$v;
        if ($type === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) json_fail('El correo no es válido.');
        if ($type === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) json_fail("«$label» no tiene una fecha válida.");
        $d[$name] = $v;
    }
    try {
        if (isset($c['validate'])) ($c['validate'])($d, $id);
        if (isset($c['before_save'])) $d = ($c['before_save'])($d, $id);
        $pdo->beginTransaction();
        if ($new) {
            $cols = array_keys($d);
            q('INSERT INTO ' . $c['table'] . ' (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')', array_values($d));
            $id = (int)$pdo->lastInsertId();
        } else {
            $sets = implode(',', array_map(fn($k) => "$k=?", array_keys($d)));
            q('UPDATE ' . $c['table'] . " SET $sets WHERE id=?", [...array_values($d), $id]);
        }
        if (isset($c['after_save'])) ($c['after_save'])($id, $d, $new);
        $pdo->commit();
        audit($new ? 'CREAR' : 'EDITAR', $e, $id, $c['singular'] . ': ' . ($d['nombre'] ?? $d['concepto'] ?? $d['nombres'] ?? $d['razon_social'] ?? '#' . $id));
        json_out(['ok' => true, 'id' => $id, 'msg' => $new ? 'Registro creado' : 'Cambios guardados']);
    } catch (PDOException $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($ex->getCode() == 23000) json_fail('Ya existe un registro con ese código/nombre/usuario. Usa un valor distinto.');
        json_fail('Error de base de datos: ' . $ex->getMessage(), 500);
    } catch (RuntimeException $ex) { if ($pdo->inTransaction()) $pdo->rollBack(); json_fail($ex->getMessage()); }

case 'toggle':
    $id = (int)(body()['id'] ?? 0);
    if (in_array($id, $c['protected'] ?? [], true)) json_fail('Este registro del sistema no se puede desactivar.');
    if ($e === 'usuarios' && $id === usuario()['id']) json_fail('No puedes desactivar tu propio usuario.');
    q("UPDATE {$c['table']} SET estado = 1 - estado WHERE id=?", [$id]); audit('ESTADO', $e, $id, 'Cambio de estado');
    json_out(['ok' => true]);

case 'delete':
    if (empty($c['delete'])) json_fail('Este registro no se puede eliminar; desactívalo.');
    $id = (int)(body()['id'] ?? 0);
    if (in_array($id, $c['protected'] ?? [], true)) json_fail('Este registro del sistema no se puede eliminar.');
    try { q("DELETE FROM {$c['table']} WHERE id=?", [$id]); audit('ELIMINAR', $e, $id, $c['singular']); json_out(['ok' => true]); }
    catch (PDOException $ex) { json_fail('No se puede eliminar: tiene movimientos asociados. Mejor desactívalo.'); }
}
json_fail('Acción no válida');
