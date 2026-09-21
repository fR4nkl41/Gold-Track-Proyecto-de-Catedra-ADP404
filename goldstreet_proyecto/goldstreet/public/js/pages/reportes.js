/* Reportes (Sprint 2): existencias, consumo por período, mermas y kardex por producto.
 * Cada reporte define: columnas, cómo pintar sus filas y qué exportar a CSV. */
App.init_reportes = async () => {
  const $ = (id) => document.getElementById(id);
  let tab = 'existencias';
  let actual = null;            // { titulo, columnas, filasCSV }

  $('gs-rep-desde').value = App.haceDias(29);
  $('gs-rep-hasta').value = App.fechaISO();

  try {
    const rp = await App.api('/productos');
    $('gs-rep-producto').innerHTML = rp.data
      .map((p) => `<option value="${p.id}">${App.esc(p.codigo)} · ${App.esc(p.nombre)}</option>`).join('');
  } catch (e) {
    App.toast(e.message, 'error');
  }

  // destacada = la cifra principal del reporte (negra con cifra dorada), como en el dashboard
  const tarjeta = (label, valor, destacada = false) => {
    return `
    <div class="rounded-xl border p-4 ${destacada ? 'bg-negro border-negro' : 'bg-white border-plato'}">
      <p class="text-[11px] font-semibold uppercase tracking-wider ${destacada ? 'text-crema/60' : 'text-stone-500'}">${App.esc(label)}</p>
      <p class="font-display text-2xl font-bold mt-1 tabular-nums ${destacada ? 'text-dorado' : 'text-negro'}">${App.esc(valor)}</p>
    </div>`;
  };

  const encabezado = (cols) => {
    $('gs-rep-thead').innerHTML = `<tr>${cols.map((c) => `<th class="px-4 py-3">${App.esc(c)}</th>`).join('')}</tr>`;
  };

  const td = (v, cls = '') => `<td class="px-4 py-2.5 ${cls}">${v}</td>`;

  const reportes = {
    async existencias() {
      const r = await App.api('/reportes/existencias');
      const cols = ['Código', 'Producto', 'Categoría', 'Proveedor', 'Stock', 'Mínimo', 'Costo', 'Valor', 'Estado'];
      encabezado(cols);
      $('gs-rep-resumen').innerHTML = tarjeta('Productos activos', App.fmtNum(r.totales.productos))
        + tarjeta('Valor total', App.fmtDinero(r.totales.valor), true)
        + tarjeta('Con alerta', App.fmtNum(r.totales.alertas))
        + r.porCategoria.map((c) => tarjeta(`${c.categoria} (${c.productos})`, App.fmtDinero(c.valor))).join('');
      $('gs-rep-tbody').innerHTML = r.data.length ? r.data.map((p) => `
        <tr class="border-t border-stone-100">
          ${td(App.esc(p.codigo), 'font-mono text-xs text-stone-500')}${td(App.esc(p.nombre), 'font-medium')}
          ${td(App.esc(p.categoria) || '—')}${td(App.esc(p.proveedor) || '—')}
          ${td(`${App.fmtNum(p.stock)} ${App.esc(p.unidad)}`)}${td(`${App.fmtNum(p.stock_minimo)} ${App.esc(p.unidad)}`)}
          ${td(App.fmtDinero(p.costo))}${td(App.fmtDinero(p.valor))}${td(App.badgeEstado(p.estado_stock))}
        </tr>`).join('') : App.filaVacia(cols.length, 'No hay productos activos.');
      return {
        titulo: 'Reporte de existencias',
        columnas: cols,
        filasCSV: r.data.map((p) => [p.codigo, p.nombre, p.categoria, p.proveedor, p.stock, p.stock_minimo, p.costo, p.valor, p.estado_stock]),
      };
    },

    async consumo() {
      const r = await App.api('/reportes/consumo' + App.qs({ desde: $('gs-rep-desde').value, hasta: $('gs-rep-hasta').value }));
      const cols = ['Código', 'Producto', 'Categoría', 'Entradas', 'Salidas (consumo)', 'Mermas', 'Usado en producción', 'Costo de salidas', 'Costo de mermas', 'Movimientos'];
      encabezado(cols);
      $('gs-rep-resumen').innerHTML = tarjeta('Período', `${r.desde} → ${r.hasta}`)
        + tarjeta('Movimientos', App.fmtNum(r.totales.movimientos))
        + tarjeta('Costo total consumido', App.fmtDinero(r.totales.costoSalidas), true)
        + tarjeta('Costo perdido en mermas', App.fmtDinero(r.totales.costoMermas));
      $('gs-rep-tbody').innerHTML = r.data.length ? r.data.map((f) => `
        <tr class="border-t border-stone-100">
          ${td(App.esc(f.codigo), 'font-mono text-xs text-stone-500')}${td(App.esc(f.producto), 'font-medium')}${td(App.esc(f.categoria) || '—')}
          ${td(`${App.fmtNum(f.entradas)} ${App.esc(f.unidad)}`, 'text-emerald-700')}
          ${td(`${App.fmtNum(f.salidas)} ${App.esc(f.unidad)}`, 'text-red-700')}
          ${td(`${App.fmtNum(f.mermas)} ${App.esc(f.unidad)}`, 'text-red-700')}
          ${td(f.produccion ? `${App.fmtNum(f.produccion)} ${App.esc(f.unidad)}` : '—', 'text-ocreOscuro')}
          ${td(App.fmtDinero(f.costoSalidas), 'font-semibold')}${td(App.fmtDinero(f.costoMermas), 'text-red-700')}
          ${td(App.fmtNum(f.movimientos))}
        </tr>`).join('') : App.filaVacia(cols.length, 'No hubo movimientos en ese período.', 'Prueba con un rango de fechas más amplio.');
      return {
        titulo: `Reporte de consumo (${r.desde} a ${r.hasta})`,
        columnas: cols,
        filasCSV: r.data.map((f) => [f.codigo, f.producto, f.categoria, f.entradas, f.salidas, f.mermas, f.produccion, f.costoSalidas, f.costoMermas, f.movimientos]),
      };
    },

    async mermas() {
      const r = await App.api('/reportes/mermas' + App.qs({ desde: $('gs-rep-desde').value, hasta: $('gs-rep-hasta').value }));
      const cols = ['Código', 'Producto', 'Categoría', 'Cantidad perdida', 'Valor perdido', 'Registros', 'Causas'];
      encabezado(cols);
      $('gs-rep-resumen').innerHTML = tarjeta('Período', `${r.desde} → ${r.hasta}`)
        + tarjeta('Valor total perdido', App.fmtDinero(r.totales.valor), true)
        + tarjeta('Registros de merma', App.fmtNum(r.totales.registros))
        + r.porCausa.filter((c) => c.veces > 0)
          .map((c) => tarjeta(`${c.causa} (${c.veces})`, App.fmtDinero(c.valor))).join('');
      $('gs-rep-tbody').innerHTML = r.data.length ? r.data.map((f) => `
        <tr class="border-t border-stone-100">
          ${td(App.esc(f.codigo), 'font-mono text-xs text-stone-500')}${td(App.esc(f.producto), 'font-medium')}${td(App.esc(f.categoria) || '—')}
          ${td(`${App.fmtNum(f.cantidad)} ${App.esc(f.unidad)}`, 'text-red-700')}
          ${td(App.fmtDinero(f.valor), 'font-semibold')}${td(App.fmtNum(f.veces))}${td(App.esc(f.causas), 'text-stone-500')}
        </tr>`).join('') : App.filaVacia(cols.length, 'No se registraron mermas en ese período.', 'Buena noticia: no hubo pérdidas de producto.');
      return {
        titulo: `Reporte de mermas (${r.desde} a ${r.hasta})`,
        columnas: cols,
        filasCSV: r.data.map((f) => [f.codigo, f.producto, f.categoria, f.cantidad, f.valor, f.veces, f.causas]),
      };
    },

    async rendimiento() {
      const r = await App.api('/reportes/rendimiento' + App.qs({ desde: $('gs-rep-desde').value, hasta: $('gs-rep-hasta').value }));
      const cols = ['Código', 'Materia prima', 'Producciones', 'Usado', 'Desperdicio', 'Aprovechamiento', 'Qué rinde', 'Costo usado', 'Costo desperdicio'];
      encabezado(cols);
      $('gs-rep-resumen').innerHTML = tarjeta('Período', `${r.desde} → ${r.hasta}`)
        + tarjeta('Aprovechamiento general', `${App.fmtNum(r.totales.aprovechamiento)} %`, true)
        + tarjeta('Producciones', App.fmtNum(r.totales.producciones))
        + tarjeta('Costo de materia prima', App.fmtDinero(r.totales.costoUsado))
        + tarjeta('Costo del desperdicio', App.fmtDinero(r.totales.costoDesperdicio));
      const rinde = (f) => f.resultados.map((x) => `${App.fmtNum(x.por_unidad)} ${App.esc(x.producto)} por ${App.esc(f.unidad)}`).join('<br>');
      $('gs-rep-tbody').innerHTML = r.data.length ? r.data.map((f) => `
        <tr class="border-t border-stone-100">
          ${td(App.esc(f.codigo), 'font-mono text-xs text-stone-500')}${td(App.esc(f.origen), 'font-medium')}${td(App.fmtNum(f.veces))}
          ${td(`${App.fmtNum(f.usado)} ${App.esc(f.unidad)}`)}${td(`${App.fmtNum(f.desperdicio)} ${App.esc(f.unidad)}`, 'text-red-700')}
          ${td(`${App.fmtNum(f.aprovechamiento)} %`, 'font-semibold')}${td(rinde(f), 'text-xs')}
          ${td(App.fmtDinero(f.costoUsado))}${td(App.fmtDinero(f.costoDesperdicio), 'text-red-700')}
        </tr>`).join('') : App.filaVacia(cols.length, 'No hubo producciones en ese período.', 'Cocina las registra en la sección Producción.');
      return {
        titulo: `Rendimiento de producción (${r.desde} a ${r.hasta})`,
        columnas: cols,
        filasCSV: r.data.map((f) => [f.codigo, f.origen, f.veces, f.usado, f.desperdicio, f.aprovechamiento,
          f.resultados.map((x) => `${x.por_unidad} ${x.producto}/${f.unidad}`).join(' | '), f.costoUsado, f.costoDesperdicio]),
      };
    },

    async kardex() {
      const id = $('gs-rep-producto').value;
      if (!id) throw new Error('Selecciona un producto.');
      const r = await App.api(`/reportes/kardex/${id}` + App.qs({ desde: $('gs-rep-desde').value, hasta: $('gs-rep-hasta').value }));
      const u = r.producto.unidad;
      const cols = ['Fecha', 'Tipo', 'Entrada', 'Salida', 'Saldo', 'Usuario', 'Referencia'];
      encabezado(cols);
      $('gs-rep-resumen').innerHTML = tarjeta('Producto', r.producto.nombre)
        + tarjeta('Saldo inicial', `${App.fmtNum(r.saldoInicial)} ${u}`)
        + tarjeta('Stock actual', `${App.fmtNum(r.producto.stock)} ${u}`, true);
      $('gs-rep-tbody').innerHTML = r.data.length ? r.data.map((m) => `
        <tr class="border-t border-stone-100">
          ${td(App.esc(m.fecha), 'whitespace-nowrap')}${td(App.badgeTipo(m.tipo, m.causa, m.clase))}
          ${td(m.entrada != null ? App.fmtNum(m.entrada) : '', 'text-emerald-700')}
          ${td(m.salida != null ? App.fmtNum(m.salida) : '', 'text-red-700')}
          ${td(`${App.fmtNum(m.saldo)} ${App.esc(u)}`, 'font-semibold')}
          ${td(App.esc(m.usuario))}${td(App.esc(m.referencia) || '—', 'text-stone-500')}
        </tr>`).join('') : App.filaVacia(cols.length, 'El producto no tuvo movimientos en ese período.');
      return {
        titulo: `Kardex · ${r.producto.codigo} ${r.producto.nombre} (${r.desde} a ${r.hasta})`,
        columnas: cols,
        filasCSV: r.data.map((m) => [m.fecha, m.es_merma ? `merma (${m.causa})` : m.tipo, m.entrada ?? '', m.salida ?? '', m.saldo, m.usuario, m.referencia ?? '']),
      };
    },
  };

  const generar = async () => {
    $('gs-rep-tbody').innerHTML = App.filasCargando($('gs-rep-thead').querySelectorAll('th').length || 7, 5);
    try {
      actual = await reportes[tab]();
    } catch (e) {
      actual = null;
      $('gs-rep-tbody').innerHTML = App.filaVacia(9, e.message);
      App.toast(e.message, 'error');
    }
  };

  const cambiarTab = (nueva) => {
    tab = nueva;
    document.querySelectorAll('.gs-tab').forEach((b) => {
      const activa = b.dataset.tab === tab;
      b.classList.toggle('border-ocre', activa);
      b.classList.toggle('text-negro', activa);
      b.setAttribute('aria-selected', activa);
      b.classList.toggle('border-transparent', !activa);
      b.classList.toggle('text-stone-400', !activa);
    });
    // Existencias es una foto del momento: no usa filtros
    $('gs-rep-filtros').classList.toggle('hidden', tab === 'existencias');
    $('gs-rep-campo-producto').classList.toggle('hidden', tab !== 'kardex');
    generar();
  };

  document.querySelectorAll('.gs-tab').forEach((b) => b.addEventListener('click', () => cambiarTab(b.dataset.tab)));
  $('gs-rep-form').addEventListener('submit', (e) => { e.preventDefault(); generar(); });

  $('gs-rep-csv').addEventListener('click', () => {
    if (!actual) return App.toast('Primero genera un reporte.', 'warn');
    App.descargarCSV(`goldtrack_${tab}_${App.fechaISO()}.csv`, actual.columnas, actual.filasCSV);
  });

  $('gs-rep-imprimir').addEventListener('click', () => {
    if (!actual) return App.toast('Primero genera un reporte.', 'warn');
    $('gs-rep-titulo-impresion').textContent = actual.titulo;
    $('gs-rep-fecha-impresion').textContent =
      `Generado el ${new Date().toLocaleString('es-SV')} por ${App.D.usuario.nombre} (${App.rolBonito(App.D.usuario.rol)})`;
    window.print();   // "Guardar como PDF" desde el diálogo de impresión
  });

  cambiarTab('existencias');
};
