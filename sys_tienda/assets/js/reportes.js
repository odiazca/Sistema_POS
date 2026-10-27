(function () {
  const $ = (s) => document.querySelector(s); let chart = null, DATA = null; const R = window.REP;
  const iso = (d) => d.toISOString().slice(0, 10), today = new Date(); const ld = (y, m, d) => new Date(Date.UTC(y, m, d, 12));
  function preset(p) {
    const y = today.getFullYear(), m = today.getMonth(); let a = ld(y, m, today.getDate()), b = a;
    if (p === '7') a = new Date(b - 6 * 864e5); if (p === '30') a = new Date(b - 29 * 864e5); if (p === 'mes') a = ld(y, m, 1); if (p === 'prev') { a = ld(y, m - 1, 1); b = ld(y, m, 0); }
    $('#desde').value = iso(a); $('#hasta').value = iso(b); document.querySelectorAll('#presets button').forEach((x) => x.classList.toggle('on', x.dataset.p === p)); load();
  }
  const F = { text: (v) => esc(v), num: (v) => fmt(v, v % 1 ? 2 : 0), money: (v) => money(v), pct: (v) => fmt(v, 1) + '%', date: (v) => v.split('-').reverse().join('/') };
  async function load() {
    try {
      const r = await api(`reportes.php?r=${R}&desde=${$('#desde').value}&hasta=${$('#hasta').value}`); DATA = r;
      if (r.nodates) $('#filtros').style.display = 'none';
      $('#sum').innerHTML = r.summary.map((s) => `<div class="col-6 col-xl"><div class="cardx"><div class="muted small">${esc(s.l)}</div><div class="fs-5 fw-800 num">${s.t === 'text' ? esc(s.v) : F[s.t](s.v)}</div></div></div>`).join('');
      $('#th').innerHTML = '<tr>' + r.cols.map((c) => `<th class="${['num', 'money', 'pct'].includes(c[2]) ? 'r' : ''}">${c[1]}</th>`).join('') + '</tr>';
      $('#tb').innerHTML = r.rows.length ? r.rows.map((x) => '<tr>' + r.cols.map((c) => `<td class="${['num', 'money', 'pct'].includes(c[2]) ? 'r num' : ''}" ${c[2] === 'text' ? 'style="white-space:normal"' : ''}>${F[c[2]](x[c[0]] ?? 0)}</td>`).join('') + '</tr>').join('') : `<tr><td colspan="${r.cols.length}"><div class="empty"><i class="bi bi-bar-chart"></i>Sin datos en el período</div></td></tr>`;
      $('#nRows').textContent = r.rows.length + ' registros'; draw(r.chart);
    } catch (e) { toast(e.message, 'danger'); }
  }
  function draw(c) {
    Chart.defaults.font.family = "'Inter',sans-serif"; const css = (v) => getComputedStyle(document.documentElement).getPropertyValue(v).trim(); Chart.defaults.color = css('--muted'); Chart.defaults.borderColor = css('--line');
    chart?.destroy(); const ctx = $('#ch').getContext('2d'), pal = ['#5b5ef4', '#14b8a6', '#f59e0b', '#f43f5e', '#0ea5e9', '#8b5cf6', '#10b981', '#ec4899', '#d97706', '#64748b'];
    const ds = c.datasets.map((d) => { if (c.type === 'doughnut') return { ...d, backgroundColor: pal, borderColor: css('--card'), borderWidth: 3 };
      if (c.type === 'line') { const g = ctx.createLinearGradient(0, 0, 0, 300); g.addColorStop(0, 'rgba(91,94,244,.3)'); g.addColorStop(1, 'rgba(91,94,244,0)'); return { ...d, borderColor: '#5b5ef4', backgroundColor: g, fill: true, tension: .38, borderWidth: 2.5, pointRadius: c.labels.length > 31 ? 0 : 3 }; }
      return { ...d, backgroundColor: '#5b5ef4', borderRadius: 6, barPercentage: .7 }; });
    chart = new Chart(ctx, { type: c.type === 'line' ? 'line' : c.type === 'doughnut' ? 'doughnut' : 'bar', data: { labels: c.labels, datasets: ds },
      options: { responsive: true, maintainAspectRatio: false, indexAxis: c.type === 'bar' ? 'y' : 'x', cutout: c.type === 'doughnut' ? '62%' : undefined,
        plugins: { legend: { display: c.type === 'doughnut', position: 'bottom', labels: { usePointStyle: true, boxWidth: 8 } }, tooltip: { backgroundColor: '#0f172a', padding: 10, cornerRadius: 10, callbacks: { label: (x) => ` ${money(x.parsed.y ?? x.parsed.x ?? x.parsed)}` } } },
        scales: c.type === 'doughnut' ? {} : { x: { grid: { display: c.type === 'bar' }, beginAtZero: true, ticks: { maxTicksLimit: 8 } }, y: { grid: { display: c.type !== 'bar' }, beginAtZero: true, ticks: { callback(v) { return c.type === 'bar' ? String(this.getLabelForValue(v)).slice(0, 22) : v; } } } } } });
  }
  document.querySelectorAll('#presets button').forEach((b) => b.onclick = () => preset(b.dataset.p));
  ['desde', 'hasta'].forEach((i) => $('#' + i).addEventListener('change', () => { document.querySelectorAll('#presets button').forEach((x) => x.classList.remove('on')); load(); }));
  $('#btnCsv').onclick = () => DATA && exportCSV('reporte_' + R, DATA.cols.map((c) => c[1]), DATA.rows.map((x) => DATA.cols.map((c) => x[c[0]])));
  window.addEventListener('themechange', () => DATA && draw(DATA.chart));
  preset('mes');
})();
