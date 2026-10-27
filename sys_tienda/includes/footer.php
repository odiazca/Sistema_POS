  </main>
  <footer class="text-center text-muted small pb-4"><?= e($empresa) ?> · <?= e(APP_NAME) ?> v<?= APP_VERSION ?> · © <?= date('Y') ?></footer>
</div>
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index:2000" id="toasts"></div>
<div class="modal fade" id="mdlConfirm" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content border-0" style="border-radius:18px"><div class="modal-body text-center p-4">
  <div class="kpi-ic ic-amber mx-auto mb-3" style="width:56px;height:56px;font-size:1.6rem"><i class="bi bi-question-lg"></i></div>
  <h6 class="fw-bold mb-1" id="cfTitle">¿Confirmar?</h6><p class="text-muted small mb-3" id="cfMsg"></p>
  <div class="d-flex gap-2"><button class="btn btn-light flex-fill" data-bs-dismiss="modal">Cancelar</button><button class="btn-pos flex-fill justify-content-center" id="cfOk">Aceptar</button></div></div></div></div></div>
<div class="modal fade" id="mdlPass" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content border-0" style="border-radius:18px"><form id="frmPass"><div class="modal-body p-4">
  <h6 class="fw-bold mb-3"><i class="bi bi-key me-2"></i>Cambiar contraseña</h6>
  <input type="password" class="form-control mb-2" name="actual" placeholder="Contraseña actual" required>
  <input type="password" class="form-control mb-2" name="nueva" placeholder="Nueva contraseña (mín. 8, letras y números)" minlength="8" required>
  <input type="password" class="form-control mb-3" name="nueva2" placeholder="Repite la nueva contraseña" minlength="8" required>
  <div class="d-flex gap-2"><button type="button" class="btn btn-light flex-fill" data-bs-dismiss="modal">Cancelar</button><button class="btn-pos flex-fill justify-content-center">Guardar</button></div></div></form></div></div></div>
<?php $bn = [['dashboard.php', 'bi-grid-1x2-fill', 'Inicio', 'dashboard'], ['modulo.php?m=pos', 'bi-cart3', 'Vender', 'pos'], ['modulo.php?m=ventas', 'bi-receipt', 'Ventas', 'ventas'], ['modulo.php?m=caja', 'bi-cash-stack', 'Caja', 'caja']]; $cur = basename($_SERVER['SCRIPT_NAME']) . (isset($_GET['m']) ? '?m=' . $_GET['m'] : ''); ?>
<nav class="bottom-nav" aria-label="Navegación rápida">
<?php foreach ($bn as $b): if ($b[3] !== 'dashboard' && !can($b[3])) continue; ?><a href="<?= BASE_URL . '/' . $b[0] ?>" class="<?= $cur === $b[0] ? 'on' : '' ?>"><i class="bi <?= $b[1] ?>"></i><span><?= $b[2] ?></span></a><?php endforeach; ?>
<button type="button" onclick="document.body.classList.toggle('sb-open')"><i class="bi bi-list"></i><span>Menú</span></button>
</nav>
<script src="<?= BASE_URL ?>/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script>window.APP={csrf:"<?= csrf_token() ?>",rol:<?= (int)usuario()["rol_id"] ?>,base:"<?= BASE_URL ?>",moneda:<?= json_encode(moneda()) ?>,impuesto:<?= json_encode(impuesto()) ?>,fmt:<?= json_encode(array_intersect_key(fmt_cfg(), array_flip(["dec", "sd", "sm", "pos"]))) ?>,user:<?= json_encode(explode(' ', usuario()['nombre'])[0]) ?>,print:{modo:<?= json_encode(cfg('print_modo', 'navegador')) ?>,auto:<?= cfg('print_auto', '0') === '1' ? 'true' : 'false' ?>,cajon:<?= cfg('print_cajon', '0') === '1' ? 'true' : 'false' ?>}}</script>
<script src="<?= BASE_URL ?>/assets/js/app.js?v=<?= ASSET_V ?>"></script>
<script src="<?= BASE_URL ?>/assets/js/pwa.js?v=<?= ASSET_V ?>"></script>
<script src="<?= BASE_URL ?>/assets/vendor/tomselect/tom-select.complete.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/selectsearch.js?v=<?= ASSET_V ?>" defer></script>
<?php if (usuario()["rol_id"] === ROL_ADMIN && !in_array(BASE_URL . "/assets/js/chat.js?v=" . ASSET_V, $extraJs ?? [], true)): ?><script src="<?= BASE_URL ?>/assets/js/chat.js?v=<?= ASSET_V ?>"></script><?php endif; ?>
<?php foreach (($extraJs ?? []) as $js): ?><script src="<?= $js ?>"></script><?php endforeach; ?>
</body></html>
