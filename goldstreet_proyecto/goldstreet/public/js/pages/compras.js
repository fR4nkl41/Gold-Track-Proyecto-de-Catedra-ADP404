/* Compras (fase 3): lista sugerida por proveedor → órdenes de compra → se reciben con su factura.
 * Bodega trabaja sin precios; el admin ve el costo estimado. */
App.init_compras = async () => {
  const $ = (id) => document.getElementById(id);
  const esAdmin = App.D.usuario?.rol === 'admin';
  let sugerencia = [];
  let actual = null;     // orden abierta en el detalle

  const pillOrden = (e) => ({
    pendiente: '<span class="gs-pill bg-amber-100 text-amber-800">Pendiente</span>',
    recibida:  '<span class="gs-pill bg-emerald-50 text-emerald-700">Recibida</span>',
    cancelada: '<span class="gs-pill bg-stone-100 text-stone-500">Cancelada</span>',
  }[e] || '');

  // ---- Pestañas -----------------------------------------------------------------
  const cambiarTab = (tab) => {
    document.querySelectorAll('.gs-tab').forEach((b) => {
      const on = b.dataset.tab === tab;
      b.classList.toggle('border-ocre', on);
      b.classList.toggle('text-negro', on);
      b.classList.toggle('border-transparent', !on);
      b.classList.toggle('text-stone-400', !on);
      b.setAttribute('aria-selected', on);
    });
    $('gs-panel-sugerida').classList.toggle('hidden', tab !== 'sugerida');
    $('gs-panel-ordenes').classList.toggle('hidden', tab !== 'ordenes');
    if (tab === 'ordenes') cargarOrdenes();
    else cargarSugerencia();
  };
  document.querySelectorAll('.gs-tab').forEach((b) => b.addEventListener('click', () => cambiarTab(b.dataset.tab)));

  // ---- Lista sugerida -------------------------------------------------------------
  const cargarSugerencia = async () => {
    $('gs-sug-grupos').innerHTML = '<div class="gs-skeleton !h-40 rounded-xl"></div>';
    try {
      const r = await App.api('/compras/sugerencia');
      sugerencia = r.data;
      $('gs-sug-productos').textContent = App.fmtNum(r.totales.productos);
      $('gs-sug-proveedores').textContent = App.fmtNum(r.totales.proveedores);
      if (esAdmin) $('gs-sug-estimado').textContent = App.fmtDinero(r.totales.estimado);
      pintaSugerencia();

      $('gs-sug-preparar').innerHTML = r.porPreparar.length ? r.porPreparar.map((p) => `
        <tr class="border-t border-stone-100">
          <td class="px-4 py-2.5 font-medium">${App.esc(p.producto)}</td>
          <td class="px-4 py-2.5 text-right">${App.fmtNum(p.stock)} ${App.esc(p.unidad)}</td>
          <td class="px-4 py-2.5 text-right font-semibold">${App.fmtNum(p.sugerido)} ${App.esc(p.unidad)}</td>
          <td class="px-4 py-2.5 text-right"><a href="/produccion?nuevo=1" class="gs-btn-mini">Ir a Producción</a></td>
        </tr>`).join('')
        : App.filaVacia(4, 'Todas las porciones están sobre su mínimo.');
    } catch (e) {
      $('gs-sug-grupos').innerHTML = `<p class="text-sm text-red-700">${App.esc(e.message)}</p>`;
    }
  };

  const pintaSugerencia = () => {
    if (!sugerencia.length) {
      $('gs-sug-grupos').innerHTML = `<div class="bg-white border border-plato rounded-xl"><table class="w-full"><tbody>${
        App.filaVacia(1, 'No hay nada que comprar por ahora.', 'Todos los productos están sobre su stock mínimo o ya vienen en una orden pendiente.')}</tbody></table></div>`;
      return;
    }

    $('gs-sug-grupos').innerHTML = sugerencia.map((g, gi) => `
      <section class="bg-white border border-plato rounded-xl overflow-hidden" data-grupo="${gi}">
        <header class="px-5 py-4 border-b border-plato flex flex-wrap items-center justify-between gap-3">
          <div>
            <h2 class="font-display text-lg font-bold text-negro">${App.esc(g.proveedor)}</h2>
            <p class="text-xs text-stone-500">${g.productos.length} producto(s) por pedir</p>
          </div>
          <div class="flex flex-wrap items-end gap-2">
            <label class="text-xs text-stone-500">Entrega esperada
              <input type="date" data-entrega min="${App.fechaISO()}" class="gs-input !py-1.5 !w-40 block mt-0.5" /></label>
            <button type="button" data-generar="${gi}" class="gs-btn-primario">Generar orden</button>
          </div>
        </header>
        <div class="overflow-x-auto">
          <table class="w-full text-sm tabular-nums min-w-[640px]">
            <thead class="bg-plato/60 text-left text-[11px] uppercase tracking-wider text-stone-500">
              <tr>
                <th class="px-4 py-2.5 w-8"><span class="sr-only">Incluir</span></th>
                <th class="px-4 py-2.5 font-semibold">Producto</th>
                <th class="px-4 py-2.5 font-semibold text-right">Hay / mínimo</th>
                <th class="px-4 py-2.5 font-semibold text-right">En camino</th>
                <th class="px-4 py-2.5 font-semibold">Pedir</th>
                ${esAdmin ? '<th class="px-4 py-2.5 font-semibold text-right">Precio est.</th><th class="px-4 py-2.5 font-semibold text-right">Subtotal</th>' : ''}
              </tr>
            </thead>
            <tbody>${g.productos.map((p, pi) => `
              <tr class="border-t border-stone-100" data-pi="${pi}">
                <td class="px-4 py-2"><input type="checkbox" data-incluir checked class="h-4 w-4 accent-ocre" aria-label="Incluir ${App.esc(p.producto)}" /></td>
                <td class="px-4 py-2"><span class="font-medium">${App.esc(p.producto)}</span>
                  <span class="block text-[11px] text-stone-400">${p.estado_stock === 'agotado' ? 'Agotado' : 'Bajo mínimo'} · ideal ${App.fmtNum(p.objetivo)} ${App.esc(p.unidad)}</span></td>
                <td class="px-4 py-2 text-right whitespace-nowrap ${p.stock <= 0 ? 'text-red-700 font-semibold' : ''}">${App.fmtNum(p.stock)} / ${App.fmtNum(p.stock_minimo)} ${App.esc(p.unidad)}</td>
                <td class="px-4 py-2 text-right text-stone-500">${p.en_camino ? `${App.fmtNum(p.en_camino)} ${App.esc(p.unidad)}` : '—'}</td>
                <td class="px-4 py-2"><div class="flex items-center gap-2">
                  <input type="number" data-cantidad step="0.01" min="0.01" value="${p.sugerido}" class="gs-input !py-1.5 w-24" />
                  <span class="text-xs text-stone-500">${App.esc(p.unidad)}</span></div></td>
                ${esAdmin ? `<td class="px-4 py-2 text-right text-stone-500">${App.fmtDinero(p.precio)}</td><td data-subtotal class="px-4 py-2 text-right font-medium"></td>` : ''}
              </tr>`).join('')}
            </tbody>
            ${esAdmin ? `<tfoot><tr class="border-t-2 border-plato"><td colspan="6" class="px-4 py-3 text-right font-semibold">Total estimado</td>
              <td data-total class="px-4 py-3 text-right font-display text-lg font-bold"></td></tr></tfoot>` : ''}
          </table>
        </div>
      </section>`).join('');

    document.querySelectorAll('[data-grupo]').forEach((sec) => {
      const recalcular = () => {
        if (!esAdmin) return;
        const g = sugerencia[sec.dataset.grupo];
        let total = 0;
        sec.querySelectorAll('tbody tr').forEach((tr) => {
          const p = g.productos[tr.dataset.pi];
          const sub = tr.querySelector('[data-incluir]').checked ? (Number(tr.querySelector('[data-cantidad]').value) || 0) * p.precio : 0;
          total += sub;
          tr.querySelector('[data-subtotal]').textContent = App.fmtDinero(sub);
          tr.classList.toggle('opacity-50', !tr.querySelector('[data-incluir]').checked);
        });
        sec.querySelector('[data-total]').textContent = App.fmtDinero(total);
      };
      sec.querySelectorAll('input').forEach((i) => i.addEventListener('input', recalcular));
      recalcular();
    });
    document.querySelectorAll('[data-generar]').forEach((b) => b.addEventListener('click', () => generar(Number(b.dataset.generar), b)));
  };

  const generar = async (gi, boton) => {
    const g = sugerencia[gi];
    const sec = document.querySelector(`[data-grupo="${gi}"]`);
    const lineas = [...sec.querySelectorAll('tbody tr')]
      .filter((tr) => tr.querySelector('[data-incluir]').checked)
      .map((tr) => {
        const p = g.productos[tr.dataset.pi];
        return { producto_id: p.producto_id, cantidad: Number(tr.querySelector('[data-cantidad]').value), ...(esAdmin ? { precio_estimado: p.precio } : {}) };
      })
      .filter((l) => l.cantidad > 0);
    if (!lineas.length) return App.toast('Marca al menos un producto con cantidad mayor que cero.', 'warn');

    boton.disabled = true;
    try {
      const r = await App.api('/ordenes-compra', {
        method: 'POST',
        body: { proveedor_id: g.proveedor_id, fecha_entrega: sec.querySelector('[data-entrega]').value || null, lineas },
      });
      App.toast(r.message);
      await cargarSugerencia();
      verOrden(r.data.id);
    } catch (e) {
      App.toast(e.message, 'error');
      boton.disabled = false;
    }
  };

  // ---- Órdenes ----------------------------------------------------------------------
  const cols = esAdmin ? 8 : 7;

  const cargarOrdenes = async () => {
    const tb = $('gs-tabla-ordenes');
    tb.innerHTML = App.filasCargando(cols);
    try {
      const lista = (await App.api('/ordenes-compra' + App.qs({ estado: $('gs-oc-f-estado').value, proveedor_id: $('gs-oc-f-proveedor').value }))).data;
      tb.innerHTML = lista.length ? lista.map((o) => `
        <tr class="border-t border-stone-100 hover:bg-crema/60 ${o.estado === 'cancelada' ? 'opacity-60' : ''}">
          <td class="px-4 py-2.5 font-mono text-xs font-semibold">${App.esc(o.numero)}</td>
          <td class="px-4 py-2.5 font-medium">${App.esc(o.proveedor)}</td>
          <td class="px-4 py-2.5 whitespace-nowrap">${App.esc(o.fecha)}</td>
          <td class="px-4 py-2.5 whitespace-nowrap">${App.esc(o.fecha_entrega) || '—'}</td>
          <td class="px-4 py-2.5">${App.fmtNum(o.lineas)}</td>
          ${esAdmin ? `<td class="px-4 py-2.5 text-right font-semibold">${App.fmtDinero(o.total)}</td>` : ''}
          <td class="px-4 py-2.5">${pillOrden(o.estado)}${o.factura ? `<span class="block text-[11px] text-stone-400">Factura ${App.esc(o.factura.numero)}</span>` : ''}</td>
          <td class="px-4 py-2.5 text-right whitespace-nowrap">
            <button data-ver="${o.id}" class="gs-btn-mini">Ver</button>
            ${o.estado === 'pendiente' ? `<a href="/facturas?orden=${o.id}" class="gs-btn-mini">Recibir</a>` : ''}
          </td>
        </tr>`).join('')
        : App.filaVacia(cols, 'No hay órdenes con ese filtro.', 'Genera una desde la lista de compras sugerida.');
      tb.querySelectorAll('[data-ver]').forEach((b) => b.addEventListener('click', () => verOrden(Number(b.dataset.ver))));
    } catch (e) {
      tb.innerHTML = App.filaVacia(cols, e.message);
    }
  };

  const verOrden = async (id) => {
    try {
      actual = (await App.api('/ordenes-compra/' + id)).data;
    } catch (e) {
      return App.toast(e.message, 'error');
    }
    const recibida = actual.estado === 'recibida';
    $('gs-ocd-titulo').textContent = `Orden ${actual.numero} · ${actual.proveedor}`;
    $('gs-ocd-sub').textContent = `Creada el ${actual.fecha} por ${actual.usuario}`
      + (actual.fecha_entrega ? ` · entrega esperada ${actual.fecha_entrega}` : '')
      + (actual.factura ? ` · recibida con la factura ${actual.factura.numero}` : '') + ` · ${actual.estado}`;
    $('gs-ocd-thead').innerHTML = `<tr><th class="px-4 py-2.5 font-semibold">Producto</th><th class="px-4 py-2.5 font-semibold text-right">Pedido</th>
      ${recibida ? '<th class="px-4 py-2.5 font-semibold text-right">Recibido</th><th class="px-4 py-2.5 font-semibold text-right">Diferencia</th>' : ''}
      ${esAdmin ? '<th class="px-4 py-2.5 font-semibold text-right">Precio est.</th><th class="px-4 py-2.5 font-semibold text-right">Subtotal</th>' : ''}</tr>`;
    $('gs-ocd-lineas').innerHTML = actual.detalles.map((d) => `
      <tr class="border-t border-stone-100">
        <td class="px-4 py-2.5"><span class="font-medium">${App.esc(d.producto)}</span><span class="block text-[11px] font-mono text-stone-400">${App.esc(d.codigo)}</span></td>
        <td class="px-4 py-2.5 text-right whitespace-nowrap">${App.fmtNum(d.cantidad)} ${App.esc(d.unidad)}</td>
        ${recibida ? `<td class="px-4 py-2.5 text-right whitespace-nowrap">${App.fmtNum(d.cantidad_recibida)} ${App.esc(d.unidad)}</td>
          <td class="px-4 py-2.5 text-right font-semibold ${d.diferencia < 0 ? 'text-red-700' : d.diferencia > 0 ? 'text-amber-700' : 'text-emerald-700'}">
            ${d.diferencia === 0 ? 'Completo' : `${d.diferencia > 0 ? '+' : ''}${App.fmtNum(d.diferencia)}`}</td>` : ''}
        ${esAdmin ? `<td class="px-4 py-2.5 text-right">${App.fmtDinero(d.precio_estimado)}</td><td class="px-4 py-2.5 text-right">${App.fmtDinero(d.subtotal)}</td>` : ''}
      </tr>`).join('');
    $('gs-ocd-notas').textContent = actual.notas ? `Notas: ${actual.notas}` : '';
    $('gs-ocd-cancelar').classList.toggle('hidden', actual.estado !== 'pendiente');
    $('gs-ocd-recibir').classList.toggle('hidden', actual.estado !== 'pendiente');
    $('gs-ocd-recibir').href = `/facturas?orden=${actual.id}`;
    App.abrirModal('gs-modal-orden');
  };

  $('gs-ocd-cancelar').addEventListener('click', async () => {
    if (!(await App.confirmar({ titulo: `¿Cancelar la orden ${actual.numero}?`, mensaje: 'Sus productos vuelven a aparecer en la lista de compras sugerida.', boton: 'Cancelar orden' }))) return;
    try {
      App.toast((await App.api(`/ordenes-compra/${actual.id}/cancelar`, { method: 'POST' })).message);
      App.cerrarModal('gs-modal-orden');
      await cargarOrdenes();
    } catch (e) {
      App.toast(e.message, 'error');
    }
  });

  // Hoja de la orden para imprimir o guardar en PDF y mandar al proveedor (sin precios)
  $('gs-ocd-imprimir').addEventListener('click', () => {
    $('gs-oci-titulo').textContent = `${actual.numero} · ${actual.proveedor}`;
    $('gs-oci-sub').textContent = `Fecha ${actual.fecha}${actual.fecha_entrega ? ` · entregar el ${actual.fecha_entrega}` : ''} · solicitado por ${actual.usuario}`;
    $('gs-oci-thead').innerHTML = '<tr><th class="px-4 py-2">Código</th><th class="px-4 py-2">Producto</th><th class="px-4 py-2 text-right">Cantidad</th></tr>';
    $('gs-oci-lineas').innerHTML = actual.detalles.map((d) => `
      <tr class="border-t border-stone-200"><td class="px-4 py-2 font-mono text-xs">${App.esc(d.codigo)}</td>
      <td class="px-4 py-2">${App.esc(d.producto)}</td><td class="px-4 py-2 text-right">${App.fmtNum(d.cantidad)} ${App.esc(d.unidad)}</td></tr>`).join('');
    $('gs-oci-notas').textContent = actual.notas ? `Notas: ${actual.notas}` : '';
    document.body.classList.add('gs-imprimir-oc');
    window.print();
    document.body.classList.remove('gs-imprimir-oc');
  });

  // ---- Inicio ------------------------------------------------------------------------
  try {
    const prov = (await App.api('/proveedores')).data;
    $('gs-oc-f-proveedor').innerHTML = '<option value="">Todos</option>' + prov.map((p) => `<option value="${p.id}">${App.esc(p.nombre)}</option>`).join('');
  } catch (_) { /* el filtro queda solo con "Todos" */ }
  $('gs-oc-filtros').addEventListener('submit', (e) => { e.preventDefault(); cargarOrdenes(); });

  cambiarTab(new URLSearchParams(location.search).get('tab') === 'ordenes' ? 'ordenes' : 'sugerida');
};
