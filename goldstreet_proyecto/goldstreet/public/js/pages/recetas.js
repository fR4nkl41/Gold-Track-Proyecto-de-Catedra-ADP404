/* Recetas y costo por plato (fase 3). Cocina consulta la ficha técnica; el admin ve costos
 * (food cost = % del precio de venta que se va en ingredientes) y administra el menú. */
App.init_recetas = async () => {
  const $ = (id) => document.getElementById(id);
  const esAdmin = App.D.usuario?.rol === 'admin';
  let platos = [];
  let limites = { ideal: 30, limite: 40 };

  // Semáforo de food cost: verde ≤ 30 %, amarillo ≤ 40 %, rojo más
  const pillFC = (fc) => {
    const cls = fc <= limites.ideal ? 'bg-emerald-50 text-emerald-700' : fc <= limites.limite ? 'bg-amber-100 text-amber-800' : 'bg-red-50 text-red-700';
    return `<span class="gs-pill ${cls}">Food cost ${App.fmtNum(fc)} %</span>`;
  };

  const cargar = async () => {
    $('gs-rec-lista').innerHTML = '<div class="gs-skeleton !h-48 rounded-xl"></div><div class="gs-skeleton !h-48 rounded-xl"></div>';
    try {
      // El admin también ve los platos no disponibles (para reactivarlos)
      const [r, rp] = await Promise.all([App.api('/platos' + (esAdmin ? '?todos=1' : '')), App.api('/productos')]);
      platos = r.data;
      App.D.productos = rp.data;
      if (esAdmin && r.totales) {
        limites = { ideal: r.totales.ideal, limite: r.totales.limite };
        $('gs-rec-stat-platos').textContent = App.fmtNum(r.totales.platos);
        $('gs-rec-stat-fc').textContent = `${App.fmtNum(r.totales.foodCostMedio)} %`;
        $('gs-rec-stat-alto').textContent = App.fmtNum(r.totales.sobreLimite);
      }
      const cats = [...new Set(platos.map((p) => p.categoria).filter(Boolean))].sort();
      const sel = $('gs-rec-categoria');
      const actual = sel.value;
      sel.innerHTML = '<option value="">Todas</option>' + cats.map((c) => `<option>${App.esc(c)}</option>`).join('');
      sel.value = actual;
      $('gs-plato-categorias').innerHTML = cats.map((c) => `<option value="${App.esc(c)}"></option>`).join('');
      pinta();
    } catch (e) {
      $('gs-rec-lista').innerHTML = `<p class="text-sm text-red-700">${App.esc(e.message)}</p>`;
    }
  };

  const pinta = () => {
    const q = $('gs-rec-buscar').value.trim().toLowerCase();
    const cat = $('gs-rec-categoria').value;
    const lista = platos.filter((p) => (!cat || p.categoria === cat)
      && (!q || p.nombre.toLowerCase().includes(q) || p.ingredientes.some((i) => (i.producto || '').toLowerCase().includes(q))));

    if (!lista.length) {
      $('gs-rec-lista').innerHTML = `<div class="lg:col-span-2 bg-white border border-plato rounded-xl"><table class="w-full"><tbody>${
        App.filaVacia(1, platos.length ? 'Ningún plato coincide con la búsqueda.' : 'Aún no hay platos en el menú.',
          esAdmin ? 'Agrega el primero con "+ Nuevo plato".' : 'El administrador registra las recetas del menú.')}</tbody></table></div>`;
      return;
    }

    $('gs-rec-lista').innerHTML = lista.map((p) => `
      <article class="bg-white border border-plato rounded-xl overflow-hidden flex flex-col">
        <header class="px-5 py-4 border-b border-plato flex items-start justify-between gap-3">
          <div>
            <p class="text-[11px] uppercase tracking-wider text-stone-400">${App.esc(p.categoria || 'Sin categoría')}</p>
            <h2 class="font-display text-lg font-bold text-negro leading-tight">${App.esc(p.nombre)}
              ${p.activo ? '' : '<span class="gs-pill bg-stone-100 text-stone-500 align-middle">No disponible</span>'}</h2>
            ${p.descripcion ? `<p class="text-xs text-stone-500 mt-0.5">${App.esc(p.descripcion)}</p>` : ''}
          </div>
          ${esAdmin ? `<p class="font-display text-2xl font-bold text-ocre tabular-nums whitespace-nowrap">${App.fmtDinero(p.precio_venta)}</p>` : ''}
        </header>
        ${esAdmin ? `
        <div class="px-5 py-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-sm bg-crema/60 border-b border-plato">
          <span>Costo <b class="tabular-nums">${App.fmtDinero(p.costo)}</b></span>
          <span>Margen <b class="tabular-nums text-emerald-700">${App.fmtDinero(p.margen)}</b></span>
          ${pillFC(p.food_cost)}
        </div>` : ''}
        <table class="w-full text-sm tabular-nums">
          <tbody>${p.ingredientes.map((i) => `
            <tr class="border-t border-stone-100 first:border-0">
              <td class="px-5 py-2">${App.esc(i.producto)}</td>
              <td class="px-3 py-2 text-right whitespace-nowrap">${App.fmtNum(i.cantidad)} ${App.esc(i.unidad)}</td>
              <td class="px-3 py-2 text-right text-xs whitespace-nowrap ${i.stock < i.cantidad ? 'text-red-700 font-semibold' : 'text-stone-400'}">hay ${App.fmtNum(i.stock)}</td>
              ${esAdmin ? `<td class="px-5 py-2 text-right text-stone-500">${App.fmtDinero(i.costo)}</td>` : ''}
            </tr>`).join('')}
          </tbody>
        </table>
        <footer class="mt-auto px-5 py-3 border-t border-plato flex items-center justify-between gap-3">
          <span class="text-sm ${p.porciones_posibles > 0 ? 'text-stone-600' : 'text-red-700 font-semibold'}">
            ${p.porciones_posibles > 0 ? `Alcanza para <b>${App.fmtNum(p.porciones_posibles)}</b> platos con el stock actual` : 'Sin stock suficiente para prepararlo'}
          </span>
          ${esAdmin ? `<span class="flex gap-1.5 shrink-0"><button data-editar="${p.id}" class="gs-btn-mini">Editar</button><button data-eliminar="${p.id}" class="gs-btn-mini-peligro">Eliminar</button></span>` : ''}
        </footer>
      </article>`).join('');

    document.querySelectorAll('[data-editar]').forEach((b) => b.addEventListener('click', () => abrir(Number(b.dataset.editar))));
    document.querySelectorAll('[data-eliminar]').forEach((b) => b.addEventListener('click', () => eliminar(Number(b.dataset.eliminar))));
  };

  // ---- Formulario (admin) -----------------------------------------------------
  const filas = $('gs-plato-ingredientes');
  const producto = (id) => App.D.productos.find((p) => p.id === Number(id));

  const recalcular = () => {
    let costo = 0;
    filas.querySelectorAll('tr').forEach((tr) => {
      const p = producto(tr.querySelector('select').value);
      const cant = Number(tr.querySelector('input').value) || 0;
      const c = p ? cant * p.costo : 0;
      costo += c;
      tr.querySelector('[data-unidad]').textContent = p?.unidad || '';
      tr.querySelector('[data-costo]').textContent = App.fmtDinero(c);
    });
    const precio = Number($('gs-plato-precio').value) || 0;
    const fc = precio > 0 ? Math.round((costo / precio) * 1000) / 10 : 0;
    $('gs-plato-resumen').innerHTML = `<div class="flex flex-wrap items-center gap-x-5 gap-y-2">
      <span>Costo del plato <b class="tabular-nums">${App.fmtDinero(costo)}</b></span>
      <span>Margen <b class="tabular-nums ${precio - costo >= 0 ? 'text-emerald-700' : 'text-red-700'}">${App.fmtDinero(precio - costo)}</b></span>
      ${precio > 0 ? pillFC(fc) : '<span class="text-stone-400">Escribe el precio para ver el food cost</span>'}</div>`;
  };

  const agregarFila = (ing = null) => {
    const tr = document.createElement('tr');
    tr.className = 'border-t border-stone-100';
    tr.innerHTML = `
      <td class="px-3 py-2"><select required class="gs-input">${App.opcionesProductos(App.D.productos, { vacio: 'Ingrediente…' })}</select></td>
      <td class="px-3 py-2"><div class="flex items-center gap-2"><input type="number" step="0.001" min="0.001" required class="gs-input w-28" /><span data-unidad class="text-xs text-stone-500 w-8"></span></div></td>
      <td data-costo class="px-3 py-2 text-right text-stone-600">$0.00</td>
      <td class="px-3 py-2 text-right"><button type="button" class="gs-btn-mini-peligro">Quitar</button></td>`;
    if (ing) {
      tr.querySelector('select').value = ing.producto_id;
      tr.querySelector('input').value = ing.cantidad;
    }
    tr.querySelectorAll('select, input').forEach((el) => el.addEventListener('input', recalcular));
    tr.querySelector('button').addEventListener('click', () => { if (filas.children.length > 1) tr.remove(); recalcular(); });
    filas.appendChild(tr);
    return tr;
  };

  const abrir = (id) => {
    const p = id ? platos.find((x) => x.id === id) : null;
    $('gs-form-plato').reset();
    $('gs-plato-titulo').textContent = p ? 'Editar plato' : 'Nuevo plato';
    $('gs-plato-id').value = p ? p.id : '';
    $('gs-plato-nombre').value = p ? p.nombre : '';
    $('gs-plato-precio').value = p ? p.precio_venta : '';
    $('gs-plato-categoria').value = p ? (p.categoria || '') : '';
    $('gs-plato-descripcion').value = p ? (p.descripcion || '') : '';
    $('gs-plato-activo').checked = p ? p.activo : true;
    filas.innerHTML = '';
    (p ? p.ingredientes : [null]).forEach((i) => agregarFila(i));
    recalcular();
    App.abrirModal('gs-modal-plato');
  };

  const eliminar = async (id) => {
    const p = platos.find((x) => x.id === id);
    if (!p || !(await App.confirmar({ titulo: `¿Eliminar "${p.nombre}" del menú?`, mensaje: 'Se borra el plato con su receta. Los productos del inventario no cambian.', boton: 'Eliminar' }))) return;
    try {
      App.toast((await App.api('/platos/' + id, { method: 'DELETE' })).message);
      await cargar();
    } catch (e) {
      App.toast(e.message, 'error');
    }
  };

  $('gs-plato-precio').addEventListener('input', recalcular);
  $('gs-plato-agregar').addEventListener('click', () => agregarFila().querySelector('select').focus());
  $('gs-btn-nuevo-plato')?.addEventListener('click', () => abrir(null));
  $('gs-rec-buscar').addEventListener('input', pinta);
  $('gs-rec-categoria').addEventListener('change', pinta);

  $('gs-form-plato').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = $('gs-plato-id').value;
    const cuerpo = {
      nombre: $('gs-plato-nombre').value.trim(),
      precio_venta: Number($('gs-plato-precio').value),
      categoria: $('gs-plato-categoria').value.trim() || null,
      descripcion: $('gs-plato-descripcion').value.trim() || null,
      activo: $('gs-plato-activo').checked,
      ingredientes: [...filas.querySelectorAll('tr')].map((tr) => ({
        producto_id: Number(tr.querySelector('select').value),
        cantidad: Number(tr.querySelector('input').value),
      })),
    };
    try {
      const r = id
        ? await App.api('/platos/' + id, { method: 'PUT', body: cuerpo })
        : await App.api('/platos', { method: 'POST', body: cuerpo });
      App.toast(r.message);
      App.cerrarModal('gs-modal-plato');
      await cargar();
    } catch (ex) {
      App.toast(ex.message, 'error');
    }
  });

  await cargar();
};
