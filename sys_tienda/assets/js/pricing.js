/* Precio mayorista + promociones (espejo de includes/pricing.php) */
window.calcLineaP = function (i, promos) {
  const r2 = (x) => Math.round((x + Number.EPSILON) * 100) / 100;
  let precio = +i.precio_venta;
  if (+i.cantidad_mayor > 0 && +i.precio_mayor > 0 && i.cant >= +i.cantidad_mayor) precio = +i.precio_mayor;
  const base = r2(i.cant * precio); let best = 0, name = null;
  for (const pr of promos || []) {
    const ok = pr.alcance === 'TODO' || (pr.alcance === 'PRODUCTO' && +pr.producto_id === +i.id) || (pr.alcance === 'CATEGORIA' && +pr.categoria_id === +i.categoria_id);
    if (!ok) continue; let d = 0;
    if (pr.tipo === 'PORCENTAJE') d = base * pr.valor / 100; else if (pr.tipo === 'MONTO') d = Math.min(base, pr.valor * i.cant);
    else if (pr.tipo === 'NXM' && pr.lleva > pr.paga && pr.lleva > 0) d = Math.floor(i.cant / pr.lleva) * (pr.lleva - pr.paga) * precio;
    d = r2(Math.min(d, base)); if (d > best) { best = d; name = pr.nombre; }
  }
  return { precio, base, desc: best, promo: name, sub: r2(base - best) };
};
