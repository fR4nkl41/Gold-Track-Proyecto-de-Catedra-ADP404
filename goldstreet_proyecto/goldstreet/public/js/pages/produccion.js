/* Producción / porcionado (fase 2): historial con aprovechamiento y registro con varios resultados.
 * Todos consultan; admin y cocina registran (la API también lo valida). */
App.init_produccion = async () => {
  const $ = (id) => document.getElementById(id);
  const puedeRegistrar = ['admin', 'cocina'].includes(App.D.usuario?.rol);

  try {
    App.D.productos = (await App.api('/productos')).data;
  } catch (e) {
    App.toast(e.message, 'error');
    return;
  }

  const producto = (id) => App.D.productos.find((p) => p.id === Number(id));

  $('gs-prod-f-origen').innerHTML = '<option value="">Todas</option>'
    + App.D.productos.map((p) => `<option value="${p.id}">${App.esc(p.nombre)}</option>`).join('');

  // Barra de aprovechamiento: verde ≥ 95 %, amarilla ≥ 85 %, roja debajo
  const barra = (pct) => {
    const color = pct >= 95 ? 'bg-emerald-600' : pct >= 85 ? 'bg-amber-500' : 'bg-red-600';
    return `<div class="flex items-center gap-2"><div class="h-1.5 w-20 rounded-full bg-stone-200 overflow-hidden">
      <div class="h-full ${color}" style="width:${Math.min(100, pct)}%"></div></div>
      <span class="font-semibold">${App.fmtNum(pct)} %</span></div>`;
  };

  // ---- Historial -------------------------------------------------------------
  const filtros = () => ({
    producto_origen_id: $('gs-prod-f-origen').value,
    desde: $('gs-prod-f-desde').value,
    hasta: $('gs-prod-f-hasta').value,
  });

  const cargar = async () => {
    const tb = $('gs-tabla-producciones');
    tb.innerHTML = App.filasCargando(7);
    try {
      const lista = (await App.api('/producciones' + App.qs(filtros()))).data;
      tb.innerHTML = lista.length ? lista.map((p) => `
        <tr class="border-t border-stone-100">
          <td class="px-4 py-2.5 whitespace-nowrap">${App.esc(p.fecha)}</td>
          <td class="px-4 py-2.5"><span class="font-medium">${App.esc(p.origen)}</span>
            ${p.notas ? `<span class="block text-[11px] text-stone-400">${App.esc(p.notas)}</span>` : ''}</td>
          <td class="px-4 py-2.5 whitespace-nowrap">${App.fmtNum(p.cantidad_origen)} ${App.esc(p.unidad_origen)}</td>
          <td class="px-4 py-2.5 whitespace-nowrap ${p.desperdicio > 0 ? 'text-red-700' : 'text-stone-400'}">${App.fmtNum(p.desperdicio)} ${App.esc(p.unidad_origen)}</td>
          <td class="px-4 py-2.5">${barra(p.aprovechamiento)}</td>
          <td class="px-4 py-2.5">${p.resultados.map((r) => `<span class="block">${App.fmtNum(r.cantidad)} ${App.esc(r.producto)}</span>`).join('')}</td>
          <td class="px-4 py-2.5 text-stone-500">${App.esc(p.usuario)}</td>
        </tr>`).join('')
        : App.filaVacia(7, 'Aún no hay producciones registradas.',
          puedeRegistrar ? 'Cuando se parta o se cocine algo, regístralo con "+ Registrar producción".' : 'Cocina las registra al partir o cocinar.');
    } catch (e) {
      tb.innerHTML = App.filaVacia(7, e.message);
    }
  };

  // ---- Registro ----------------------------------------------------------------
  const selOrigen = $('gs-prod-origen');
  const contResultados = $('gs-prod-resultados');

  const calcular = () => {
    const origen = producto(selOrigen.value);
    const usado = Number($('gs-prod-cantidad').value) || 0;
    const desp = Number($('gs-prod-desperdicio').value) || 0;
    document.querySelectorAll('[data-unidad-origen]').forEach((el) => { el.textContent = origen?.unidad || ''; });

    const caja = $('gs-prod-aprov');
    if (!origen || usado <= 0) {
      caja.textContent = 'Escribe la cantidad usada para ver el aprovechamiento.';
      return;
    }
    const pct = Math.round(((usado - desp) / usado) * 1000) / 10;
    const rinde = [...contResultados.children].map((fila) => {
      const p = producto(fila.querySelector('select').value);
      const cant = Number(fila.querySelector('input').value) || 0;
      return p && cant > 0 ? `${App.fmtNum(Math.round((cant / usado) * 100) / 100)} ${App.esc(p.nombre)} por ${App.esc(origen.unidad)}` : null;
    }).filter(Boolean);
    const sinStock = usado > origen.stock
      ? `<p class="text-red-700 mt-1">Solo hay ${App.fmtNum(origen.stock)} ${App.esc(origen.unidad)} de ${App.esc(origen.nombre)}.</p>` : '';

    caja.innerHTML = `
      <div class="flex flex-wrap items-center justify-between gap-2">
        <span>Aprovechado: <b>${App.fmtNum(usado - desp)} ${App.esc(origen.unidad)}</b></span>
        ${barra(pct)}
      </div>
      ${rinde.length ? `<p class="text-xs text-stone-500 mt-1">Rinde ${rinde.join(' · ')}</p>` : ''}
      ${sinStock}`;
  };

  const agregarResultado = () => {
    const fila = document.createElement('div');
    fila.className = 'flex items-center gap-2';
    fila.innerHTML = `
      <select required class="gs-input flex-1">${App.opcionesProductos(App.D.productos, { excluir: Number(selOrigen.value), vacio: 'Porción u orden que salió…' })}</select>
      <input type="number" step="0.01" min="0.01" required placeholder="Cant." class="gs-input w-28" />
      <button type="button" class="gs-btn-mini-peligro" aria-label="Quitar resultado">Quitar</button>`;
    fila.querySelectorAll('select, input').forEach((el) => el.addEventListener('input', calcular));
    fila.querySelector('button').addEventListener('click', () => {
      if (contResultados.children.length > 1) fila.remove();
      calcular();
    });
    contResultados.appendChild(fila);
    return fila;
  };

  const abrirRegistro = () => {
    $('gs-form-produccion').reset();
    selOrigen.innerHTML = App.opcionesProductos(App.D.productos, { conStock: true, vacio: 'Selecciona la materia prima…' });
    contResultados.innerHTML = '';
    agregarResultado();
    calcular();
    App.abrirModal('gs-modal-produccion');
  };

  selOrigen.addEventListener('change', () => {
    // La materia prima no puede ser también un resultado: se refrescan las opciones
    contResultados.querySelectorAll('select').forEach((s) => {
      const elegido = s.value;
      s.innerHTML = App.opcionesProductos(App.D.productos, { excluir: Number(selOrigen.value), vacio: 'Porción u orden que salió…' });
      if (elegido !== selOrigen.value) s.value = elegido;
    });
    calcular();
  });
  $('gs-prod-cantidad').addEventListener('input', calcular);
  $('gs-prod-desperdicio').addEventListener('input', calcular);
  $('gs-prod-agregar').addEventListener('click', () => agregarResultado().querySelector('select').focus());
  $('gs-btn-nueva-prod')?.addEventListener('click', abrirRegistro);

  $('gs-form-produccion').addEventListener('submit', async (e) => {
    e.preventDefault();
    const cuerpo = {
      producto_origen_id: Number(selOrigen.value),
      cantidad_origen: Number($('gs-prod-cantidad').value),
      desperdicio: Number($('gs-prod-desperdicio').value) || 0,
      notas: $('gs-prod-notas').value.trim() || null,
      resultados: [...contResultados.children].map((fila) => ({
        producto_id: Number(fila.querySelector('select').value),
        cantidad: Number(fila.querySelector('input').value),
      })),
    };
    try {
      let r;
      try {
        r = await App.api('/producciones', { method: 'POST', body: cuerpo });
      } catch (ex) {
        // Rendimiento poco común: el servidor pide confirmar antes de crear las porciones
        if (!ex.data?.requiere_confirmacion) throw ex;
        const ok = await App.confirmar({ titulo: 'Rendimiento poco común', mensaje: ex.data.message, boton: 'Sí, es correcto' });
        if (!ok) return;
        r = await App.api('/producciones', { method: 'POST', body: { ...cuerpo, confirmar_rendimiento: true } });
      }
      App.toast(r.message);
      if (r.alerta) App.toast(r.alerta, 'warn');
      App.cerrarModal('gs-modal-produccion');
      App.D.productos = (await App.api('/productos')).data;   // stock actualizado para la próxima
      await cargar();
    } catch (ex) {
      App.toast(ex.message, 'error');
    }
  });

  $('gs-prod-filtros').addEventListener('submit', (e) => { e.preventDefault(); cargar(); });
  $('gs-prod-f-limpiar').addEventListener('click', () => { $('gs-prod-filtros').reset(); cargar(); });

  await cargar();
  if (puedeRegistrar && new URLSearchParams(location.search).has('nuevo')) abrirRegistro();
};
