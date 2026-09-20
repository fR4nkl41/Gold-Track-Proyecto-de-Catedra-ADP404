/* Inventario: listado con filtros (RF06) y alta/edición/baja de productos (RF02, solo admin) */
App.init_inventario = async () => {
  const esAdmin = App.D.usuario?.rol === 'admin';

  try {
    const [rp, rc, rprov] = await Promise.all([
      App.api('/productos'),
      App.api('/categorias'),
      App.api('/proveedores'),
    ]);
    App.D.productos = rp.data;
    App.D.categorias = rc.data;
    App.D.proveedores = rprov.data;
  } catch (e) {
    App.toast(e.message, 'error');
    return;
  }

  const selCat = document.getElementById('gs-filtro-cat');
  selCat.innerHTML = `<option value="all">Todas las categorías</option>`
    + App.D.categorias.map((c) => `<option value="${c.id}">${App.esc(c.nombre)}</option>`).join('');

  const pinta = () => {
    const cat = selCat.value || 'all';
    const est = document.getElementById('gs-filtro-estado').value || 'all';
    const q = (document.getElementById('gs-filtro-buscar').value || '').toLowerCase();

    const lista = App.D.productos.filter((p) => {
      const okCat = cat === 'all' || String(p.categoria_id) === cat;
      const okEst = est === 'all' || p.estado_stock === est;
      const okQ = !q || p.nombre.toLowerCase().includes(q) || p.codigo.toLowerCase().includes(q);
      return okCat && okEst && okQ;
    });

    document.getElementById('gs-contador').textContent =
      `${lista.length} de ${App.D.productos.length} productos`;

    const tb = document.getElementById('gs-tabla-productos');
    tb.innerHTML = lista.length ? lista.map((p) => `
      <tr class="border-t border-stone-100 hover:bg-crema/60">
        <td class="px-4 py-2.5 font-mono text-xs text-stone-500">${App.esc(p.codigo)}</td>
        <td class="px-4 py-2.5">
          <span class="font-medium">${App.esc(p.nombre)}</span>
          <span class="block text-xs text-stone-400">${App.esc(p.categoria || 'Sin categoría')} · ${App.esc(p.proveedor || 'Sin proveedor')}</span>
        </td>
        <td class="px-4 py-2.5">${App.fmtNum(p.stock)} ${App.esc(p.unidad)}</td>
        <td class="px-4 py-2.5">${App.fmtNum(p.stock_minimo)} ${App.esc(p.unidad)}</td>
        <td class="px-4 py-2.5">${App.fmtDinero(p.costo)}</td>
        <td class="px-4 py-2.5">${App.fmtDinero(p.valor)}</td>
        <td class="px-4 py-2.5">${App.badgeEstado(p.estado_stock)}</td>
        <td class="px-4 py-2.5 text-right whitespace-nowrap">
          ${esAdmin ? `
            <button data-editar="${p.id}" class="gs-btn-mini">Editar</button>
            <button data-baja="${p.id}" class="gs-btn-mini-peligro">Dar de baja</button>` : ''}
        </td>
      </tr>`).join('')
      : App.D.productos.length
        ? App.filaVacia(8, 'No hay productos con esos filtros.', 'Prueba con otra categoría, otro estado o borra la búsqueda.')
        : App.filaVacia(8, 'Aún no hay productos en el inventario.', esAdmin ? 'Agrega el primero con "Nuevo producto".' : 'El administrador debe registrarlos.');

    tb.querySelectorAll('[data-editar]').forEach((b) =>
      b.addEventListener('click', () => App.abrirModalProducto(Number(b.dataset.editar))));
    tb.querySelectorAll('[data-baja]').forEach((b) =>
      b.addEventListener('click', () => App.darBaja(Number(b.dataset.baja))));
  };

  selCat.addEventListener('change', pinta);
  document.getElementById('gs-filtro-estado').addEventListener('change', pinta);
  document.getElementById('gs-filtro-buscar').addEventListener('input', pinta);
  document.getElementById('gs-btn-nuevo')?.addEventListener('click', () => App.abrirModalProducto(null));
  document.getElementById('gs-form-producto').addEventListener('submit', App.guardarProducto);

  pinta();

  // Acceso rápido desde el dashboard: /inventario?nuevo=1
  if (esAdmin && new URLSearchParams(location.search).has('nuevo')) App.abrirModalProducto(null);
};

App.abrirModalProducto = (id) => {
  const p = id ? App.D.productos.find((x) => x.id === id) : null;
  document.getElementById('gs-modal-titulo').textContent = p ? 'Editar producto' : 'Nuevo producto';
  document.getElementById('gs-prod-id').value = p ? p.id : '';
  document.getElementById('gs-prod-codigo').value = p ? p.codigo : '';
  document.getElementById('gs-prod-nombre').value = p ? p.nombre : '';
  // Categoría y proveedor son obligatorios (diccionario de datos 3.4)
  document.getElementById('gs-prod-categoria').innerHTML =
    `<option value="" disabled ${p ? '' : 'selected'}>Selecciona una categoría…</option>` + App.D.categorias.map((c) =>
      `<option value="${c.id}" ${p && p.categoria_id === c.id ? 'selected' : ''}>${App.esc(c.nombre)}</option>`).join('');
  document.getElementById('gs-prod-proveedor').innerHTML =
    `<option value="" disabled ${p ? '' : 'selected'}>Selecciona un proveedor…</option>` + App.D.proveedores.map((v) =>
      `<option value="${v.id}" ${p && p.proveedor_id === v.id ? 'selected' : ''}>${App.esc(v.nombre)}</option>`).join('');
  document.getElementById('gs-prod-unidad').value = p ? p.unidad : 'kg';
  document.getElementById('gs-prod-costo').value = p ? p.costo : '';
  // Al editar, el stock es solo lectura: se mueve con entradas/salidas (trigger)
  const inStock = document.getElementById('gs-prod-stock');
  inStock.value = p ? p.stock : 0;
  inStock.disabled = !!p;
  document.querySelector('label[for="gs-prod-stock"]').textContent = p ? 'Stock actual' : 'Stock inicial';
  document.getElementById('gs-prod-stock-ayuda').classList.toggle('hidden', !p);
  document.getElementById('gs-prod-minimo').value = p ? p.stock_minimo : 0;
  document.getElementById('gs-prod-maximo').value = p && p.stock_maximo != null ? p.stock_maximo : '';
  document.getElementById('gs-prod-preparado').checked = !!(p && p.es_preparado);
  App.abrirModal('gs-modal-producto');
};

App.guardarProducto = async (e) => {
  e.preventDefault();
  const id = document.getElementById('gs-prod-id').value;
  const cuerpo = {
    codigo: document.getElementById('gs-prod-codigo').value.trim(),
    nombre: document.getElementById('gs-prod-nombre').value.trim(),
    categoria_id: document.getElementById('gs-prod-categoria').value || null,
    proveedor_id: document.getElementById('gs-prod-proveedor').value || null,
    unidad: document.getElementById('gs-prod-unidad').value,
    // Solo al crear: el backend lo registra como ENTRADA "Stock inicial"
    ...(id ? {} : { stock: Number(document.getElementById('gs-prod-stock').value) }),
    stock_minimo: Number(document.getElementById('gs-prod-minimo').value),
    stock_maximo: document.getElementById('gs-prod-maximo').value === '' ? null : Number(document.getElementById('gs-prod-maximo').value),
    es_preparado: document.getElementById('gs-prod-preparado').checked,
    costo: Number(document.getElementById('gs-prod-costo').value),
  };
  try {
    if (id) await App.api('/productos/' + id, { method: 'PUT', body: cuerpo });
    else await App.api('/productos', { method: 'POST', body: cuerpo });
    App.toast('Producto guardado correctamente.');
    location.replace('/inventario');
  } catch (ex) {
    App.toast(ex.message, 'error');
  }
};

App.darBaja = async (id) => {
  const p = App.D.productos.find((x) => x.id === id);
  if (!p || !(await App.confirmar({
    titulo: `¿Dar de baja "${p.nombre}"?`,
    mensaje: 'Dejará de aparecer en el inventario, pero su historial de movimientos se conserva.',
    boton: 'Dar de baja',
  }))) return;
  try {
    await App.api('/productos/' + id, { method: 'DELETE' });
    App.toast('Producto dado de baja.');
    location.replace('/inventario');
  } catch (ex) {
    App.toast(ex.message, 'error');
  }
};
