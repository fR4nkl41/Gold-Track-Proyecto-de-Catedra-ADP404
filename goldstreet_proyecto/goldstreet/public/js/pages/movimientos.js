/* Movimientos: kardex filtrable por tipo, producto y fechas + registro de entradas, salidas y mermas (RF03).
 * En la API una merma es una SALIDA con "causa"; en la UI se ofrece como un tipo aparte. */
App.init_movimientos = async () => {
  const rol = App.D.usuario?.rol;

  // La UI ofrece solo lo que el rol puede; el backend también lo aplica (403).
  const tiposPermitidos = {
    admin:  ['entrada', 'salida', 'merma'],
    bodega: ['entrada', 'salida', 'merma'],
    cocina: ['salida', 'merma'],
  }[rol] || [];

  const ETIQUETAS = {
    entrada: 'Entrada (ingreso a bodega)',
    salida:  'Salida (consumo / venta)',
    merma:   'Merma (pérdida: vencido, dañado...)',
  };

  const selTipo = document.getElementById('gs-mov-tipo');
  const campoCausa = document.getElementById('gs-mov-campo-causa');
  selTipo.innerHTML = tiposPermitidos.map((t) => `<option value="${t}">${ETIQUETAS[t]}</option>`).join('');
  document.getElementById('gs-btn-nuevo-mov').disabled = tiposPermitidos.length === 0;
  document.getElementById('gs-btn-merma').disabled = !tiposPermitidos.includes('merma');

  const actualizarCausa = () => {
    const esMerma = selTipo.value === 'merma';
    campoCausa.classList.toggle('hidden', !esMerma);
    document.getElementById('gs-mov-titulo').textContent = esMerma ? 'Registrar merma' : 'Registrar movimiento';
  };
  selTipo.addEventListener('change', actualizarCausa);

  const filtros = () => ({
    tipo: document.getElementById('gs-f-tipo').value,
    producto_id: document.getElementById('gs-f-producto').value,
    desde: document.getElementById('gs-f-desde').value,
    hasta: document.getElementById('gs-f-hasta').value,
    limite: 500,
  });

  const pinta = () => {
    document.getElementById('gs-tabla-movimientos').innerHTML = App.D.movimientos.length
      ? App.D.movimientos.map((m) => App.filaMovimiento(m, true)).join('')
      : App.filaVacia(6, 'No hay movimientos con esos filtros.', 'Cambia el tipo, el producto o las fechas, o pulsa Limpiar.');
    document.getElementById('gs-mov-contador').textContent =
      `${App.D.movimientos.length} movimiento(s) encontrados`;
  };

  const cargar = async () => {
    document.getElementById('gs-tabla-movimientos').innerHTML = App.filasCargando(6);
    try {
      const [rm, rp] = await Promise.all([
        App.api('/movimientos' + App.qs(filtros())),
        App.api('/productos'),
      ]);
      App.D.movimientos = rm.data;
      App.D.productos = rp.data;
      pinta();
    } catch (e) {
      App.toast(e.message, 'error');
    }
  };

  await cargar();

  // Productos en el filtro (se llena una sola vez; conserva la selección)
  document.getElementById('gs-f-producto').innerHTML = '<option value="">Todos los productos</option>'
    + App.D.productos.map((p) => `<option value="${p.id}">${App.esc(p.nombre)}</option>`).join('');

  document.getElementById('gs-form-filtros').addEventListener('submit', (e) => { e.preventDefault(); cargar(); });
  document.getElementById('gs-f-limpiar').addEventListener('click', () => {
    document.getElementById('gs-form-filtros').reset();
    cargar();
  });

  const abrirRegistro = (tipo = null) => {
    const selProd = document.getElementById('gs-mov-producto');
    selProd.innerHTML = App.D.productos.filter((p) => p.estado === 'activo')
      .map((p) => `<option value="${p.id}">${App.esc(p.nombre)} — stock: ${App.fmtNum(p.stock)} ${App.esc(p.unidad)}</option>`).join('');
    selTipo.value = tipo && tiposPermitidos.includes(tipo) ? tipo : tiposPermitidos[0];
    document.getElementById('gs-mov-causa').value = 'vencido';
    document.getElementById('gs-mov-cantidad').value = '';
    document.getElementById('gs-mov-referencia').value = '';
    actualizarCausa();
    App.abrirModal('gs-modal-movimiento');
  };

  document.getElementById('gs-btn-nuevo-mov').addEventListener('click', () => abrirRegistro());
  document.getElementById('gs-btn-merma').addEventListener('click', () => abrirRegistro('merma'));

  document.getElementById('gs-form-movimiento').addEventListener('submit', async (e) => {
    e.preventDefault();
    const esMerma = selTipo.value === 'merma';
    const cuerpo = {
      producto_id: Number(document.getElementById('gs-mov-producto').value),
      tipo: esMerma ? 'salida' : selTipo.value,
      causa: esMerma ? document.getElementById('gs-mov-causa').value : null,
      cantidad: Number(document.getElementById('gs-mov-cantidad').value),
      referencia: document.getElementById('gs-mov-referencia').value || null,
    };
    try {
      const r = await App.api('/movimientos', { method: 'POST', body: cuerpo });
      App.toast(r.message, 'ok');
      if (r.alerta) App.toast(r.alerta, 'warn');   // alerta automática (RF05)
      App.cerrarModal('gs-modal-movimiento');
      await cargar();
    } catch (ex) {
      App.toast(ex.message, 'error');
    }
  });

  // Acceso rápido desde el dashboard: /movimientos?nuevo=entrada|salida|merma
  const nuevo = new URLSearchParams(location.search).get('nuevo');
  if (nuevo && tiposPermitidos.length) abrirRegistro(nuevo);
};
