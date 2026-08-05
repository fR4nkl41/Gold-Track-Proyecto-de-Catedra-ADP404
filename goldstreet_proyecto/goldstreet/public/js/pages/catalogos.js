/* Catálogos (solo admin): CRUD de categorías y proveedores.
 * Ambos comparten la misma mecánica; cada uno se describe en `catalogos`. */
App.init_catalogos = async () => {
  const $ = (id) => document.getElementById(id);

  const catalogos = {
    categorias: {
      singular: 'categoría',
      ruta: '/categorias',
      tbody: 'gs-tabla-categorias',
      modal: 'gs-modal-categoria',
      form: 'gs-form-categoria',
      titulo: 'gs-cat-titulo',
      prefijo: 'gs-cat-',
      campos: ['nombre', 'descripcion'],
      columnas: (c) => [
        `<span class="font-medium">${App.esc(c.nombre)}</span>`,
        `<span class="text-stone-500">${App.esc(c.descripcion) || '—'}</span>`,
        App.fmtNum(c.productos_count),
      ],
    },
    proveedores: {
      singular: 'proveedor',
      ruta: '/proveedores',
      tbody: 'gs-tabla-proveedores',
      modal: 'gs-modal-proveedor',
      form: 'gs-form-proveedor',
      titulo: 'gs-prov-titulo',
      prefijo: 'gs-prov-',
      campos: ['nombre', 'contacto', 'telefono', 'correo', 'direccion'],
      columnas: (p) => [
        `<span class="font-medium">${App.esc(p.nombre)}</span>`
          + [p.correo, p.direccion].filter(Boolean).map((t) => `<span class="block text-xs text-stone-400">${App.esc(t)}</span>`).join(''),
        App.esc(p.contacto) || '—',
        App.esc(p.telefono) || '—',
        App.fmtNum(p.productos_count),
      ],
    },
  };

  const pinta = (clave) => {
    const cfg = catalogos[clave];
    const filas = App.D[clave];
    const tb = $(cfg.tbody);
    const ncol = cfg.columnas({}).length + 1;
    tb.innerHTML = filas.length ? filas.map((f) => `
      <tr class="border-t border-stone-100">
        ${cfg.columnas(f).map((c) => `<td class="px-4 py-2.5">${c}</td>`).join('')}
        <td class="px-4 py-2.5 text-right whitespace-nowrap">
          ${clave === 'proveedores' ? `<a href="/proveedores/${f.id}" class="gs-btn-mini">Ficha</a>` : ''}
          <button data-editar="${f.id}" class="gs-btn-mini">Editar</button>
          <button data-eliminar="${f.id}" class="gs-btn-mini-peligro">Eliminar</button>
        </td>
      </tr>`).join('')
      : App.filaVacia(ncol, `Aún no hay ${clave}.`, `Agrega el primero con el botón de esta sección.`);

    tb.querySelectorAll('[data-editar]').forEach((b) =>
      b.addEventListener('click', () => abrir(clave, Number(b.dataset.editar))));
    tb.querySelectorAll('[data-eliminar]').forEach((b) =>
      b.addEventListener('click', () => eliminar(clave, Number(b.dataset.eliminar))));
  };

  const cargar = async (clave) => {
    try {
      App.D[clave] = (await App.api(catalogos[clave].ruta)).data;
      pinta(clave);
    } catch (e) {
      App.toast(e.message, 'error');
    }
  };

  const abrir = (clave, id) => {
    const cfg = catalogos[clave];
    const item = id ? App.D[clave].find((x) => x.id === id) : null;
    $(cfg.titulo).textContent = `${item ? 'Editar' : 'Nuevo/a'} ${cfg.singular}`;
    $(cfg.prefijo + 'id').value = item ? item.id : '';
    cfg.campos.forEach((c) => { $(cfg.prefijo + c).value = item ? (item[c] ?? '') : ''; });
    App.abrirModal(cfg.modal);
  };

  const eliminar = async (clave, id) => {
    const cfg = catalogos[clave];
    const item = App.D[clave].find((x) => x.id === id);
    if (!item || !(await App.confirmar({
      titulo: `¿Eliminar ${cfg.singular} "${item.nombre}"?`,
      mensaje: 'Esta acción no se puede deshacer. Si tiene productos asignados, el sistema no lo permitirá.',
      boton: 'Eliminar',
    }))) return;
    try {
      const r = await App.api(`${cfg.ruta}/${id}`, { method: 'DELETE' });
      App.toast(r.message);
      await cargar(clave);
    } catch (e) {
      App.toast(e.message, 'error');
    }
  };

  Object.entries(catalogos).forEach(([clave, cfg]) => {
    $(cfg.form).addEventListener('submit', async (e) => {
      e.preventDefault();
      const id = $(cfg.prefijo + 'id').value;
      const cuerpo = Object.fromEntries(cfg.campos.map((c) => [c, $(cfg.prefijo + c).value.trim() || null]));
      try {
        const r = id
          ? await App.api(`${cfg.ruta}/${id}`, { method: 'PUT', body: cuerpo })
          : await App.api(cfg.ruta, { method: 'POST', body: cuerpo });
        App.toast(r.message);
        App.cerrarModal(cfg.modal);
        await cargar(clave);
      } catch (ex) {
        App.toast(ex.message, 'error');
      }
    });
  });

  $('gs-btn-nueva-cat').addEventListener('click', () => abrir('categorias', null));
  $('gs-btn-nuevo-prov').addEventListener('click', () => abrir('proveedores', null));

  await Promise.all([cargar('categorias'), cargar('proveedores')]);
};
