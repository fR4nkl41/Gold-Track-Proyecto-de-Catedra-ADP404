/* Cuentas por pagar (fase 3, solo admin): saldos por factura y proveedor, y registro de abonos. */
App.init_cuentas = async () => {
  const $ = (id) => document.getElementById(id);
  let facturas = [];
  let actual = null;

  // "vencida hace 10 días" · "vence hoy" · "en 3 días"
  const plazo = (dias) => {
    if (dias == null) return '';
    if (dias < 0) return `<span class="block text-[11px] font-semibold text-red-700">venció hace ${-dias} día${dias === -1 ? '' : 's'}</span>`;
    if (dias === 0) return '<span class="block text-[11px] font-semibold text-amber-700">vence hoy</span>';
    return `<span class="block text-[11px] ${dias <= 7 ? 'font-semibold text-amber-700' : 'text-stone-400'}">en ${dias} día${dias === 1 ? '' : 's'}</span>`;
  };

  const cargar = async () => {
    $('gs-tabla-cxp').innerHTML = App.filasCargando(8);
    try {
      const r = await App.api('/cuentas-por-pagar' + App.qs({ estado: $('gs-cxp-f-estado').value, proveedor_id: $('gs-cxp-f-proveedor').value }));
      facturas = r.data;
      $('gs-cxp-pendiente').textContent = App.fmtDinero(r.totales.pendiente);
      $('gs-cxp-vencido').textContent = App.fmtDinero(r.totales.vencido);
      $('gs-cxp-semana').textContent = App.fmtDinero(r.totales.porVencer7);
      $('gs-cxp-pagado').textContent = App.fmtDinero(r.totales.pagadoMes);

      $('gs-tabla-cxp').innerHTML = facturas.length ? facturas.map((f) => `
        <tr class="border-t border-stone-100 hover:bg-crema/60">
          <td class="px-4 py-2.5 whitespace-nowrap">${App.esc(f.vence) || '—'}${f.saldo > 0 ? plazo(f.dias) : ''}</td>
          <td class="px-4 py-2.5"><span class="font-mono text-xs">${App.esc(f.numero)}</span><span class="block text-[11px] text-stone-400">${App.esc(f.fecha)}</span></td>
          <td class="px-4 py-2.5 font-medium">${App.esc(f.proveedor)}</td>
          <td class="px-4 py-2.5 text-right">${App.fmtDinero(f.total)}</td>
          <td class="px-4 py-2.5 text-right text-stone-500">${App.fmtDinero(f.pagado)}</td>
          <td class="px-4 py-2.5 text-right font-semibold">${App.fmtDinero(f.saldo)}</td>
          <td class="px-4 py-2.5">${App.badgePago(f.estado_pago)}</td>
          <td class="px-4 py-2.5 text-right whitespace-nowrap">
            ${f.saldo > 0 ? `<button data-pagar="${f.id}" class="gs-btn-mini">Registrar pago</button>` : ''}
            <a href="/facturas?ver=${f.id}" class="gs-btn-mini">Ver</a>
          </td>
        </tr>`).join('')
        : App.filaVacia(8, $('gs-cxp-f-estado').value === 'pagada' ? 'Todavía no hay facturas al crédito pagadas.' : 'No se le debe nada a los proveedores.',
          'Las facturas al crédito aparecen aquí cuando bodega las registra con condición "Crédito".');
      $('gs-tabla-cxp').querySelectorAll('[data-pagar]').forEach((b) => b.addEventListener('click', () => abrirPago(Number(b.dataset.pagar))));

      $('gs-cxp-proveedores').innerHTML = r.porProveedor.length ? r.porProveedor.map((p) => `
        <tr class="border-t border-stone-100">
          <td class="px-4 py-2.5"><a href="/proveedores/${p.proveedor_id}" class="font-medium hover:text-ocre">${App.esc(p.proveedor)}</a>
            <span class="block text-[11px] text-stone-400">${p.facturas} factura(s)${p.vencido > 0 ? ` · <span class="text-red-700 font-semibold">${App.fmtDinero(p.vencido)} vencido</span>` : ''}</span></td>
          <td class="px-4 py-2.5 text-right font-semibold">${App.fmtDinero(p.saldo)}</td>
        </tr>`).join('')
        : App.filaVacia(2, 'Sin saldos pendientes.');
    } catch (e) {
      $('gs-tabla-cxp').innerHTML = App.filaVacia(8, e.message);
    }
  };

  const abrirPago = (id) => {
    actual = facturas.find((f) => f.id === id);
    $('gs-form-pago').reset();
    $('gs-pago-titulo').textContent = `Pago a ${actual.proveedor}`;
    $('gs-pago-info').innerHTML = `Factura <b class="font-mono">${App.esc(actual.numero)}</b> del ${App.esc(actual.fecha)} ·
      total ${App.fmtDinero(actual.total)} · pagado ${App.fmtDinero(actual.pagado)}
      <span class="block mt-1">Saldo: <b class="font-display text-lg">${App.fmtDinero(actual.saldo)}</b></span>`;
    $('gs-pago-monto').value = actual.saldo;
    $('gs-pago-monto').max = actual.saldo;
    $('gs-pago-fecha').value = App.fechaISO();
    $('gs-pago-fecha').max = App.fechaISO();
    App.abrirModal('gs-modal-pago');
  };

  $('gs-form-pago').addEventListener('submit', async (e) => {
    e.preventDefault();
    try {
      const r = await App.api(`/facturas/${actual.id}/pagos`, {
        method: 'POST',
        body: {
          monto: Number($('gs-pago-monto').value),
          fecha: $('gs-pago-fecha').value,
          metodo: $('gs-pago-metodo').value,
          referencia: $('gs-pago-referencia').value.trim() || null,
        },
      });
      App.toast(r.message);
      App.cerrarModal('gs-modal-pago');
      await cargar();
    } catch (ex) {
      App.toast(ex.message, 'error');
    }
  });

  try {
    const prov = (await App.api('/proveedores')).data;
    $('gs-cxp-f-proveedor').innerHTML = '<option value="">Todos</option>' + prov.map((p) => `<option value="${p.id}">${App.esc(p.nombre)}</option>`).join('');
  } catch (_) { /* filtro solo con "Todos" */ }
  $('gs-cxp-filtros').addEventListener('submit', (e) => { e.preventDefault(); cargar(); });

  await cargar();
};
