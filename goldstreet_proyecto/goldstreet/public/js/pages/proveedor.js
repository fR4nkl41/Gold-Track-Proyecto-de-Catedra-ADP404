/* Ficha del proveedor (fase 2, solo admin): /proveedores/{id} */
App.init_proveedor = async () => {
  const $ = (id) => document.getElementById(id);
  const id = Number(location.pathname.split('/').pop());

  let r;
  try {
    r = await App.api(`/proveedores/${id}/ficha`);
  } catch (e) {
    App.toast(e.message, 'error');
    document.querySelector('main h1').textContent = 'Proveedor no encontrado';
    return;
  }

  const p = r.proveedor;
  document.title = `${p.nombre} · GoldTrack`;
  document.querySelector('main h1').textContent = p.nombre;
  document.querySelector('main h1 + p').textContent = `${App.fmtNum(r.totales.productos)} producto(s) comprados · ${App.fmtNum(r.totales.facturas)} factura(s)`;

  const dato = (etiqueta, valor) => `<dt class="text-stone-500">${etiqueta}</dt><dd class="font-medium break-words">${valor ? App.esc(valor) : '<span class="text-stone-400">—</span>'}</dd>`;
  $('gs-pv-contacto').innerHTML = dato('Contacto', p.contacto) + dato('Teléfono', p.telefono)
    + dato('Correo', p.correo) + dato('Dirección', p.direccion);

  $('gs-pv-total').textContent = App.fmtDinero(r.totales.total);
  $('gs-pv-mes').textContent = App.fmtDinero(r.totales.mes);
  $('gs-pv-facturas').textContent = App.fmtNum(r.totales.facturas);
  $('gs-pv-ultima').textContent = r.totales.ultima_compra || '—';

  // ↑ subió (rojo: cuesta más) · ↓ bajó (verde) respecto a la compra anterior
  const tendencia = (ultimo, anterior) => {
    if (anterior == null || ultimo === anterior) return '';
    const sube = ultimo > anterior;
    const pct = Math.round(Math.abs((ultimo - anterior) / anterior) * 1000) / 10;
    return `<span class="ml-1 text-[11px] font-semibold ${sube ? 'text-red-700' : 'text-emerald-700'}" title="Antes ${App.fmtDinero(anterior)}">${sube ? '↑' : '↓'} ${pct}%</span>`;
  };

  $('gs-pv-productos').innerHTML = r.productos.length ? r.productos.map((x) => `
    <tr class="border-t border-stone-100">
      <td class="px-4 py-2.5"><span class="font-medium">${App.esc(x.producto)}</span><span class="block text-[11px] font-mono text-stone-400">${App.esc(x.codigo)}</span></td>
      <td class="px-4 py-2.5 text-right">${App.fmtNum(x.compras)}</td>
      <td class="px-4 py-2.5 text-right whitespace-nowrap">${App.fmtNum(x.cantidad)} ${App.esc(x.unidad)}</td>
      <td class="px-4 py-2.5 text-right whitespace-nowrap font-semibold">${App.fmtDinero(x.ultimo_precio)}${tendencia(x.ultimo_precio, x.precio_anterior)}</td>
      <td class="px-4 py-2.5 text-right">${App.fmtDinero(x.precio_promedio)}</td>
      <td class="px-4 py-2.5 text-right whitespace-nowrap text-stone-500">${App.fmtDinero(x.precio_min)} – ${App.fmtDinero(x.precio_max)}</td>
      <td class="px-4 py-2.5 text-right">${App.fmtDinero(x.monto)}</td>
      <td class="px-4 py-2.5 whitespace-nowrap">${App.esc(x.ultima_compra)}</td>
    </tr>`).join('')
    : App.filaVacia(8, 'Todavía no hay compras registradas a este proveedor.', 'Aparecerán aquí cuando bodega registre sus facturas.');

  $('gs-pv-facturas-tabla').innerHTML = r.facturas.length ? r.facturas.map((f) => `
    <tr class="border-t border-stone-100 ${f.estado === 'anulada' ? 'opacity-60' : ''}">
      <td class="px-4 py-2.5 whitespace-nowrap">${App.esc(f.fecha)}</td>
      <td class="px-4 py-2.5 font-mono text-xs">${App.esc(f.numero)}</td>
      <td class="px-4 py-2.5 text-right">${App.fmtNum(f.lineas)}</td>
      <td class="px-4 py-2.5 text-right font-semibold">${App.fmtDinero(f.total)}</td>
      <td class="px-4 py-2.5">${f.estado === 'anulada'
        ? '<span class="gs-pill bg-red-50 text-red-700">Anulada</span>'
        : '<span class="gs-pill bg-emerald-50 text-emerald-700">Registrada</span>'}</td>
      <td class="px-4 py-2.5 text-right"><a href="/facturas?ver=${f.id}" class="gs-btn-mini">Ver</a></td>
    </tr>`).join('')
    : App.filaVacia(6, 'Sin facturas.');

  $('gs-pv-asignados').innerHTML = r.asignados.length
    ? r.asignados.map((a) => `<li class="flex justify-between gap-2"><span>${App.esc(a.nombre)}</span><span class="font-mono text-[11px] text-stone-400">${App.esc(a.codigo)}</span></li>`).join('')
    : '<li class="text-stone-400">Ningún producto lo tiene como proveedor principal.</li>';
};
