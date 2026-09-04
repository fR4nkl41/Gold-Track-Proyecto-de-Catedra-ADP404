/* Facturas de compra (fase 2): listado con filtros, registro con líneas dinámicas, detalle y anulación.
 * Bodega registra y consulta sin precios; el admin ve montos y puede anular. */
App.init_facturas = async () => {
  const $ = (id) => document.getElementById(id);
  const esAdmin = App.D.usuario?.rol === 'admin';
  let facturas = [];
  let actual = null;   // factura abierta en el detalle

  try {
    const [rp, rprov] = await Promise.all([App.api('/productos'), App.api('/proveedores')]);
    App.D.productos = rp.data;
    App.D.proveedores = rprov.data;
  } catch (e) {
    App.toast(e.message, 'error');
    return;
  }

  const opcionesProv = App.D.proveedores.map((p) => `<option value="${p.id}">${App.esc(p.nombre)}</option>`).join('');
  $('gs-fac-f-proveedor').innerHTML = '<option value="">Todos los proveedores</option>' + opcionesProv;
  $('gs-fac-proveedor').innerHTML = '<option value="">Selecciona el proveedor…</option>' + opcionesProv;

  const estadoPill = (e) => e === 'anulada'
    ? '<span class="gs-pill bg-red-50 text-red-700">Anulada</span>'
    : '<span class="gs-pill bg-emerald-50 text-emerald-700">Registrada</span>';

  // ---- Listado ---------------------------------------------------------------
  const filtros = () => ({
    proveedor_id: $('gs-fac-f-proveedor').value,
    buscar: $('gs-fac-f-buscar').value.trim(),
    desde: $('gs-fac-f-desde').value,
    hasta: $('gs-fac-f-hasta').value,
  });

  const columnas = esAdmin ? 9 : 7;

  const cargar = async () => {
    $('gs-tabla-facturas').innerHTML = App.filasCargando(columnas);
    try {
      const r = await App.api('/facturas' + App.qs(filtros()));
      facturas = r.data;
      if (esAdmin) {
        $('gs-fac-stat-n').textContent = App.fmtNum(r.totales.facturas);
        $('gs-fac-stat-monto').textContent = App.fmtDinero(r.totales.monto);
      }
      pinta();
    } catch (e) {
      $('gs-tabla-facturas').innerHTML = App.filaVacia(columnas, e.message);
    }
  };

  const pinta = () => {
    const tb = $('gs-tabla-facturas');
    tb.innerHTML = facturas.length ? facturas.map((f) => `
      <tr class="border-t border-stone-100 hover:bg-crema/60 ${f.estado === 'anulada' ? 'opacity-60' : ''}">
        <td class="px-4 py-2.5 whitespace-nowrap">${App.esc(f.fecha)}</td>
        <td class="px-4 py-2.5 font-mono text-xs">${App.esc(f.numero)}</td>
        <td class="px-4 py-2.5 font-medium">${App.esc(f.proveedor)}</td>
        <td class="px-4 py-2.5">${App.fmtNum(f.lineas)}</td>
        ${esAdmin ? `<td class="px-4 py-2.5 text-right font-semibold">${App.fmtDinero(f.total)}</td>
          <td class="px-4 py-2.5">${App.badgePago(f.estado_pago)}${f.estado_pago === 'pendiente' || f.estado_pago === 'vencida' ? `<span class="block text-[11px] text-stone-400">saldo ${App.fmtDinero(f.saldo)}</span>` : ''}</td>` : ''}
        <td class="px-4 py-2.5 text-stone-500">${App.esc(f.usuario)}</td>
        <td class="px-4 py-2.5">${estadoPill(f.estado)}</td>
        <td class="px-4 py-2.5 text-right"><button data-ver="${f.id}" class="gs-btn-mini">Ver</button></td>
      </tr>`).join('')
      : App.filaVacia(columnas, 'No hay facturas con esos filtros.', 'Registra la primera con "+ Registrar factura" cuando llegue un pedido.');
    tb.querySelectorAll('[data-ver]').forEach((b) => b.addEventListener('click', () => verDetalle(Number(b.dataset.ver))));
  };

  // ---- Registro con líneas dinámicas ------------------------------------------
  const lineas = $('gs-fac-lineas');

  const unidadDe = (id) => App.D.productos.find((p) => p.id === Number(id))?.unidad || '';

  const recalcular = () => {
    let total = 0;
    lineas.querySelectorAll('tr').forEach((tr) => {
      const cant = Number(tr.querySelector('[data-cantidad]').value) || 0;
      const precio = Number(tr.querySelector('[data-precio]').value) || 0;
      const sub = Math.round(cant * precio * 100) / 100;
      total += sub;
      tr.querySelector('[data-subtotal]').textContent = App.fmtDinero(sub);
      tr.querySelector('[data-unidad]').textContent = unidadDe(tr.querySelector('[data-producto]').value);
    });
    $('gs-fac-total').textContent = App.fmtDinero(total);
  };

  const agregarLinea = (inicial = null) => {
    const tr = document.createElement('tr');
    tr.className = 'border-t border-stone-100';
    tr.innerHTML = `
      <td class="px-3 py-2"><select data-producto required class="gs-input">${App.opcionesProductos(App.D.productos)}</select></td>
      <td class="px-3 py-2"><div class="flex items-center gap-2">
        <input data-cantidad type="number" step="0.01" min="0.01" required class="gs-input w-28" />
        <span data-unidad class="text-xs text-stone-500 w-8"></span></div></td>
      <td class="px-3 py-2"><div class="flex items-center gap-1"><span class="text-stone-400">$</span>
        <input data-precio type="number" step="0.01" min="0" required class="gs-input w-28" /></div></td>
      <td data-subtotal class="px-3 py-2 text-right font-medium">$0.00</td>
      <td class="px-3 py-2 text-right"><button type="button" data-quitar class="gs-btn-mini-peligro" aria-label="Quitar línea">Quitar</button></td>`;
    if (inicial) {
      tr.querySelector('[data-producto]').value = inicial.producto_id;
      tr.querySelector('[data-cantidad]').value = inicial.cantidad;
      if (inicial.precio != null) tr.querySelector('[data-precio]').value = inicial.precio;
    }
    tr.querySelectorAll('input, select').forEach((el) => el.addEventListener('input', recalcular));
    tr.querySelector('[data-quitar]').addEventListener('click', () => {
      if (lineas.children.length > 1) tr.remove();
      recalcular();
    });
    lineas.appendChild(tr);
    recalcular();
    return tr;
  };

  // Condición de pago: al crédito se pide el vencimiento (botones de plazo rápido)
  let ordenId = null;
  const actualizarCondicion = () => {
    const credito = $('gs-fac-condicion').value === 'credito';
    $('gs-fac-campo-vence').classList.toggle('hidden', !credito);
    $('gs-fac-vence').required = credito;
    $('gs-fac-vence').min = $('gs-fac-fecha').value;
  };
  $('gs-fac-condicion').addEventListener('change', actualizarCondicion);
  $('gs-fac-fecha').addEventListener('change', actualizarCondicion);
  $('gs-fac-dias').addEventListener('change', () => {
    const dias = Number($('gs-fac-dias').value);
    if (!dias) return;
    const d = new Date(($('gs-fac-fecha').value || App.fechaISO()) + 'T12:00:00');
    d.setDate(d.getDate() + dias);
    $('gs-fac-vence').value = App.fechaISO(d);
  });

  const abrirRegistro = (orden = null) => {
    $('gs-form-factura').reset();
    $('gs-fac-fecha').value = App.fechaISO();
    $('gs-fac-fecha').max = App.fechaISO();
    ordenId = orden ? orden.id : null;
    $('gs-fac-titulo').textContent = orden ? `Recibir ${orden.numero}` : 'Registrar factura';
    $('gs-fac-proveedor').disabled = !!orden;
    const aviso = $('gs-fac-orden-aviso');
    aviso.classList.toggle('hidden', !orden);
    lineas.innerHTML = '';
    if (orden) {
      $('gs-fac-proveedor').value = orden.proveedor_id;
      aviso.innerHTML = `Recibiendo la orden <b>${App.esc(orden.numero)}</b> de ${App.esc(orden.proveedor)}.
        Cambia las cantidades a lo que <b>realmente llegó</b> y quita lo que no vino: el sistema guarda la diferencia.`;
      orden.detalles.forEach((d) => agregarLinea({ producto_id: d.producto_id, cantidad: d.cantidad, precio: d.precio_estimado }));
    } else {
      agregarLinea();
    }
    actualizarCondicion();
    App.abrirModal('gs-modal-factura');
  };

  $('gs-fac-agregar').addEventListener('click', () => agregarLinea().querySelector('select').focus());
  $('gs-btn-nueva-factura').addEventListener('click', () => abrirRegistro());

  $('gs-form-factura').addEventListener('submit', async (e) => {
    e.preventDefault();
    const cuerpo = {
      proveedor_id: Number($('gs-fac-proveedor').value),
      numero: $('gs-fac-numero').value.trim(),
      fecha: $('gs-fac-fecha').value,
      condicion: $('gs-fac-condicion').value,
      fecha_vencimiento: $('gs-fac-condicion').value === 'credito' ? $('gs-fac-vence').value : null,
      orden_compra_id: ordenId,
      lineas: [...lineas.querySelectorAll('tr')].map((tr) => ({
        producto_id: Number(tr.querySelector('[data-producto]').value),
        cantidad: Number(tr.querySelector('[data-cantidad]').value),
        precio_unitario: Number(tr.querySelector('[data-precio]').value),
      })),
    };
    const btn = e.submitter;
    if (btn) btn.disabled = true;
    try {
      const r = await App.api('/facturas', { method: 'POST', body: cuerpo });
      App.toast(r.message);
      App.cerrarModal('gs-modal-factura');
      if (ordenId) history.replaceState(null, '', '/facturas');
      ordenId = null;
      await cargar();
    } catch (ex) {
      App.toast(ex.message, 'error');
    } finally {
      if (btn) btn.disabled = false;
    }
  });

  // ---- Detalle y anulación ----------------------------------------------------
  const verDetalle = async (id) => {
    try {
      actual = (await App.api('/facturas/' + id)).data;
    } catch (e) {
      return App.toast(e.message, 'error');
    }
    $('gs-det-titulo').textContent = `Factura ${actual.numero}`;
    $('gs-det-sub').textContent = `${actual.proveedor} · ${actual.fecha} · registrada por ${actual.usuario} el ${actual.registrada}`;

    const aviso = $('gs-det-anulada');
    aviso.classList.toggle('hidden', actual.estado !== 'anulada');
    if (actual.anulacion) {
      aviso.textContent = `Anulada el ${actual.anulacion.fecha} por ${actual.anulacion.por}. Motivo: ${actual.anulacion.motivo}`;
    }

    $('gs-det-thead').innerHTML = `<tr>
      <th class="px-4 py-2.5 font-semibold">Producto</th><th class="px-4 py-2.5 font-semibold text-right">Cantidad</th>
      ${esAdmin ? '<th class="px-4 py-2.5 font-semibold text-right">Precio unit.</th><th class="px-4 py-2.5 font-semibold text-right">Subtotal</th>' : ''}</tr>`;
    $('gs-det-lineas').innerHTML = actual.detalles.map((d) => `
      <tr class="border-t border-stone-100">
        <td class="px-4 py-2.5"><span class="font-medium">${App.esc(d.producto)}</span><span class="block text-[11px] font-mono text-stone-400">${App.esc(d.codigo)}</span></td>
        <td class="px-4 py-2.5 text-right">${App.fmtNum(d.cantidad)} ${App.esc(d.unidad)}</td>
        ${esAdmin ? `<td class="px-4 py-2.5 text-right">${App.fmtDinero(d.precio_unitario)}</td><td class="px-4 py-2.5 text-right font-semibold">${App.fmtDinero(d.subtotal)}</td>` : ''}
      </tr>`).join('')
      + (esAdmin ? `<tr class="border-t-2 border-plato"><td colspan="3" class="px-4 py-3 text-right font-semibold">Total</td>
          <td class="px-4 py-3 text-right font-display text-lg font-bold">${App.fmtDinero(actual.total)}</td></tr>` : '');

    $('gs-det-pago').innerHTML = [
      actual.condicion === 'credito' ? `Al crédito · vence el <b>${App.esc(actual.vence)}</b>` : 'De contado',
      actual.orden ? `Recibe la orden <b>${App.esc(actual.orden)}</b>` : '',
      esAdmin && actual.estado !== 'anulada' ? `${App.badgePago(actual.estado_pago)}${actual.saldo > 0 ? ` saldo <b>${App.fmtDinero(actual.saldo)}</b>` : ''}` : '',
    ].filter(Boolean).join(' · ');
    const pagos = $('gs-det-pagos');
    if (pagos) {
      pagos.innerHTML = actual.pagos && actual.pagos.length ? `
        <p class="gs-label">Pagos registrados</p>
        <ul class="text-sm divide-y divide-stone-100 border border-plato rounded-xl">${actual.pagos.map((p) => `
          <li class="px-4 py-2 flex justify-between gap-3"><span>${App.esc(p.fecha)} · ${App.esc(p.metodo)}${p.referencia ? ` · ${App.esc(p.referencia)}` : ''}
            <span class="text-stone-400">(${App.esc(p.usuario)})</span></span><b class="tabular-nums">${App.fmtDinero(p.monto)}</b></li>`).join('')}</ul>` : '';
    }

    $('gs-det-anular')?.classList.toggle('hidden', actual.estado === 'anulada' || (actual.pagos && actual.pagos.length > 0));
    App.abrirModal('gs-modal-detalle');
  };

  $('gs-det-anular')?.addEventListener('click', () => {
    App.cerrarModal('gs-modal-detalle');
    $('gs-anular-titulo').textContent = `Anular factura ${actual.numero}`;
    $('gs-anular-motivo').value = '';
    App.abrirModal('gs-modal-anular');
  });

  $('gs-form-anular').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const r = await App.api(`/facturas/${actual.id}/anular`, { method: 'POST', body: { motivo: $('gs-anular-motivo').value.trim() } });
      App.toast(r.message);
      App.cerrarModal('gs-modal-anular');
      await cargar();
    } catch (ex) {
      App.toast(ex.message, 'error');
    }
  });

  // ---- Filtros y accesos directos ----------------------------------------------
  $('gs-fac-filtros').addEventListener('submit', (e) => { e.preventDefault(); cargar(); });
  $('gs-fac-f-limpiar').addEventListener('click', () => { $('gs-fac-filtros').reset(); cargar(); });

  await cargar();

  // /facturas?nuevo=1 (dashboard) · /facturas?ver=ID (ficha del proveedor)
  const qs = new URLSearchParams(location.search);
  if (qs.has('nuevo')) abrirRegistro();
  if (qs.get('ver')) verDetalle(Number(qs.get('ver')));
  // /facturas?orden=ID (Compras → "Recibir"): la factura se llena con la orden
  if (qs.get('orden')) {
    try {
      const orden = (await App.api('/ordenes-compra/' + Number(qs.get('orden')))).data;
      if (orden.estado === 'pendiente') abrirRegistro(orden);
      else App.toast(`La orden ${orden.numero} ya está ${orden.estado}.`, 'warn');
    } catch (e) {
      App.toast(e.message, 'error');
    }
  }
};
