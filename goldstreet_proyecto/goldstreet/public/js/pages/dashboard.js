/* Dashboard: indicadores, gráfico semanal, productos críticos (RF05) y últimos movimientos */
App.init_dashboard = async () => {
  try {
    const r = await App.api('/dashboard');
    App.D.resumen = r.resumen;

    document.getElementById('gs-stat-productos').textContent = App.fmtNum(r.resumen.totalProductos);
    document.getElementById('gs-stat-valor').textContent = App.fmtDinero(r.resumen.valorInventario);
    document.getElementById('gs-stat-alertas').textContent = App.fmtNum(r.resumen.alertasBajo + r.resumen.agotados);
    document.getElementById('gs-stat-entradas').textContent = App.fmtNum(r.resumen.entradasHoy);
    document.getElementById('gs-stat-salidas').textContent = App.fmtNum(r.resumen.salidasHoy);
    document.getElementById('gs-stat-mermas').textContent = App.fmtDinero(r.resumen.valorMermasMes);

    document.getElementById('gs-recientes').innerHTML = r.inventarioReciente.length
      ? r.inventarioReciente.map((p) => `
          <tr class="border-t border-stone-100">
            <td class="px-4 py-2.5">
              <span class="font-medium">${App.esc(p.nombre)}</span>
              <span class="block text-[11px] text-stone-400">${p.ultimo_movimiento ? 'Últ. mov. ' + App.esc(p.ultimo_movimiento) : 'Sin movimientos'}</span>
            </td>
            <td class="px-4 py-2.5 whitespace-nowrap">${App.fmtNum(p.stock)} ${App.esc(p.unidad)}</td>
            <td class="px-4 py-2.5">${App.badgeEstado(p.estado_stock)}</td>
          </tr>`).join('')
      : App.filaVacia(3, 'Aún no hay productos activos.', 'El administrador los agrega desde Inventario › Nuevo producto.');

    document.getElementById('gs-criticos').innerHTML = r.criticos.length
      ? r.criticos.map((p) => `
          <tr class="border-t border-stone-100">
            <td class="px-4 py-2.5 font-medium">${App.esc(p.nombre)}</td>
            <td class="px-4 py-2.5">${App.esc(p.categoria) || '—'}</td>
            <td class="px-4 py-2.5">${App.fmtNum(p.stock)} ${App.esc(p.unidad)}</td>
            <td class="px-4 py-2.5">${App.fmtNum(p.stock_minimo)} ${App.esc(p.unidad)}</td>
            <td class="px-4 py-2.5">${App.badgeEstado(p.estado_stock)}</td>
          </tr>`).join('')
      : App.filaVacia(5, 'Todos los productos están sobre el mínimo.', 'No hay nada que reponer por ahora.');

    document.getElementById('gs-ultimos').innerHTML = r.ultimosMovimientos.length
      ? r.ultimosMovimientos.map((m) => App.filaMovimiento(m)).join('')
      : App.filaVacia(5, 'Aún no hay movimientos registrados.', 'Registra el primero con los botones de arriba.');

    App.graficoSemanal(r.consumoSemanal);

    // Admin: aviso de cuentas por pagar vencidas o que vencen esta semana
    const aviso = document.getElementById('gs-aviso-pagos');
    if (aviso && App.D.usuario?.rol === 'admin') {
      const cxp = (await App.api('/cuentas-por-pagar')).totales;
      if (cxp.vencido > 0 || cxp.porVencer7 > 0) {
        aviso.innerHTML = `<span class="font-display text-lg font-bold">!</span><span>`
          + (cxp.vencido > 0 ? `Hay <b>${App.fmtDinero(cxp.vencido)}</b> en facturas vencidas. ` : '')
          + (cxp.porVencer7 > 0 ? `<b>${App.fmtDinero(cxp.porVencer7)}</b> vencen en los próximos 7 días. ` : '')
          + '</span><span class="ml-auto font-semibold whitespace-nowrap">Ver cuentas por pagar →</span>';
        aviso.classList.remove('hidden');
      }
    }
  } catch (e) {
    App.toast(e.message, 'error');
  }
};

/* Barras de entradas, salidas y mermas ($) de los últimos 7 días (Chart.js) */
App.graficoSemanal = (dias) => {
  const canvas = document.getElementById('gs-grafico-semanal');
  if (!canvas) return;
  if (typeof Chart === 'undefined') {
    canvas.parentElement.innerHTML = '<p class="text-sm text-stone-400 text-center pt-24">No se pudo cargar la librería de gráficos (revisa la conexión a internet).</p>';
    return;
  }

  new Chart(canvas, {
    type: 'bar',
    data: {
      labels: dias.map((d) => d.etiqueta),
      datasets: [
        { label: 'Entradas', data: dias.map((d) => d.entradas), backgroundColor: App.COLORES.dorado, borderRadius: 4 },
        { label: 'Salidas',  data: dias.map((d) => d.salidas),  backgroundColor: App.COLORES.negro,  borderRadius: 4 },
        { label: 'Mermas',   data: dias.map((d) => d.mermas),   backgroundColor: App.COLORES.rojo,   borderRadius: 4 },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'bottom', labels: { usePointStyle: true, font: { family: 'Inter' } } },
        tooltip: { callbacks: { label: (c) => `${c.dataset.label}: ${App.fmtDinero(c.parsed.y)}` } },
      },
      scales: {
        x: { grid: { display: false }, ticks: { color: '#78716C', font: { family: 'Inter' } } },
        y: { beginAtZero: true, grid: { color: '#F4EADB' }, border: { display: false },
             ticks: { color: '#78716C', callback: (v) => App.fmtDinero(v), font: { family: 'Inter' } } },
      },
    },
  });
};
