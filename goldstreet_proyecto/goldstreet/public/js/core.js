/* ============================================================
 * GoldTrack · Núcleo del frontend (core.js)
 * Sistema de Control de Inventarios — Restaurante Gold Street
 *
 * Lo cargan TODAS las vistas (<x-layouts.base>). Cada vista agrega
 * su propio script en public/js/pages/{pagina}.js que define
 * App.init_{pagina}(); el router del final lo ejecuta.
 * ============================================================ */

/* ------------------------------------------------------------
 * 0) Identidad visual (logo de Gold Street)
 * Paleta: ocre #C2621A · dorado #E3A21A · negro #1C1713 · crema #FBF6EE
 * Definida en <x-head> (tailwind.config); el logo está en public/img/.
 * App.COLORES la repite para lo que se dibuja en JS (gráficos).
 * ------------------------------------------------------------ */

/* ------------------------------------------------------------
 * 1) Constantes de la API
 * Ruta relativa: funciona igual en 127.0.0.1, localhost u otro host/puerto.
 * ------------------------------------------------------------ */
const API_URL = '/api';

/* ------------------------------------------------------------
 * 2) Estado global en memoria
 * Depuración en consola:  App.D.productos.length
 * ------------------------------------------------------------ */
const App = {
  D: {
    productos: [],
    movimientos: [],
    categorias: [],
    proveedores: [],
    usuarios: [],
    usuario: null,
    resumen: null,
  },
  COLORES: { ocre: '#C2621A', dorado: '#E3A21A', negro: '#1C1713', rojo: '#B91C1C' },
};

/* ------------------------------------------------------------
 * 3) Utilidades
 * ------------------------------------------------------------ */
App.$ = (sel) => document.querySelector(sel);

/* Escapa texto antes de meterlo en innerHTML (evita XSS con nombres, motivos...) */
App.esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({
  '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
}[c]));

App.fmtDinero = (n) => '$' + Number(n || 0).toLocaleString('es-SV', {
  minimumFractionDigits: 2, maximumFractionDigits: 2,
});

App.fmtNum = (n) => Number(n || 0).toLocaleString('es-SV', { maximumFractionDigits: 2 });

App.rolBonito = (r) => ({ admin: 'Administrador', bodega: 'Bodega', cocina: 'Cocina' }[r] || r);

/* Fecha local en formato YYYY-MM-DD (para <input type="date">) */
App.fechaISO = (d = new Date()) => {
  const z = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${z(d.getMonth() + 1)}-${z(d.getDate())}`;
};

App.haceDias = (n) => { const d = new Date(); d.setDate(d.getDate() - n); return App.fechaISO(d); };

/* Arma "?a=1&b=2" ignorando valores vacíos */
App.qs = (params) => {
  const s = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== '' && v != null)).toString();
  return s ? '?' + s : '';
};

App.guardarSesion = (token, usuario) => {
  localStorage.setItem('gs_token', token);
  localStorage.setItem('gs_usuario', JSON.stringify(usuario));
  App.D.usuario = usuario;
};

App.cargarSesion = () => {
  const raw = localStorage.getItem('gs_usuario');
  App.D.usuario = raw ? JSON.parse(raw) : null;
  return App.D.usuario;
};

App.clearSesion = () => {
  localStorage.removeItem('gs_token');
  localStorage.removeItem('gs_usuario');
};

/* Fetch con token Bearer + manejo de errores en español */
App.api = async (path, { method = 'GET', body = null } = {}) => {
  const token = localStorage.getItem('gs_token');
  const res = await fetch(API_URL + path, {
    method,
    headers: {
      'Accept': 'application/json',
      ...(body ? { 'Content-Type': 'application/json' } : {}),
      ...(token ? { 'Authorization': 'Bearer ' + token } : {}),
    },
    body: body ? JSON.stringify(body) : null,
  });

  if (res.status === 401 && !path.includes('/login')) {
    App.clearSesion();
    window.location.href = '/login';
    throw new Error('Sesión expirada. Inicia sesión de nuevo.');
  }

  if (res.status === 429) {
    throw new Error('Demasiados intentos. Espera un minuto y vuelve a intentarlo.');
  }

  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    const msg = data.errors
      ? Object.values(data.errors).flat().join(' · ')
      : data.message;
    const err = new Error(msg || ('Error ' + res.status));
    err.status = res.status;
    err.data = data;   // p. ej. requiere_confirmacion en producción
    throw err;
  }
  return data;
};

/* Toast simple (ok | error | warn) */
App.toast = (msg, tipo = 'ok') => {
  const colores = {
    ok: 'bg-emerald-600',
    error: 'bg-red-700',
    warn: 'bg-amber-500 text-stone-900',
  };
  // Contenedor único: varios toasts se apilan en vez de encimarse
  let pila = document.getElementById('gs-toasts');
  if (!pila) {
    pila = document.createElement('div');
    pila.id = 'gs-toasts';
    pila.className = 'fixed bottom-5 right-5 z-[60] flex flex-col items-end gap-2 print:hidden';
    document.body.appendChild(pila);
  }
  const div = document.createElement('div');
  div.className = `px-4 py-3 rounded-lg shadow-xl text-white ${colores[tipo] || colores.ok} font-medium max-w-sm`;
  div.textContent = msg;
  pila.appendChild(div);
  setTimeout(() => div.remove(), 4500);
};

/* Estado del stock (RF05/RF06): semáforo verde · amarillo · rojo */
App.badgeEstado = (estado) => {
  const map = {
    optimo:  '<span class="gs-pill bg-emerald-50 text-emerald-700">Óptimo</span>',
    bajo:    '<span class="gs-pill bg-amber-100 text-amber-800">Bajo mínimo</span>',
    agotado: '<span class="gs-pill bg-red-50 text-red-700">Agotado</span>',
  };
  return map[estado] || App.esc(estado);
};

/* Entrada · Compra · Salida · Merma · Producción · Anulación
 * (una merma es una salida con causa; "clase" viene de MovimientoInventario::clase) */
App.badgeTipo = (tipo, causa = null, clase = null) => {
  if (causa) return `<span class="gs-pill bg-red-50 text-red-700">Merma · ${App.esc(causa)}</span>`;
  if (clase === 'compra') return '<span class="gs-pill bg-emerald-50 text-emerald-700">Compra</span>';
  if (clase === 'produccion') return `<span class="gs-pill bg-orange-100 text-ocreOscuro">Producción ${tipo === 'entrada' ? '↑' : '↓'}</span>`;
  if (clase === 'anulacion') return '<span class="gs-pill bg-stone-100 text-stone-500">Anulación</span>';
  if (tipo === 'entrada') return '<span class="gs-pill bg-emerald-50 text-emerald-700">Entrada</span>';
  return '<span class="gs-pill bg-stone-100 text-stone-700">Salida</span>';
};

/* Cuentas por pagar: contado · pendiente · vencida · pagada · anulada */
App.badgePago = (estado) => ({
  contado:   '<span class="gs-pill bg-stone-100 text-stone-600">Contado</span>',
  pendiente: '<span class="gs-pill bg-amber-100 text-amber-800">Por pagar</span>',
  vencida:   '<span class="gs-pill bg-red-50 text-red-700">Vencida</span>',
  pagada:    '<span class="gs-pill bg-emerald-50 text-emerald-700">Pagada</span>',
  anulada:   '<span class="gs-pill bg-stone-100 text-stone-400">Anulada</span>',
}[estado] || '');

/* Causas de merma (mismas claves que MovimientoInventario::CAUSAS) */
App.CAUSAS = {
  vencido: 'Vencido', danado: 'Dañado', mal_preparado: 'Mal preparado',
  empaque: 'Empaque dañado', desperdicio: 'Desperdicio', otro: 'Otro',
};

/* Mensaje cuando una tabla no tiene datos: qué pasa y, si aplica, qué hacer */
App.filaVacia = (colspan, texto, sugerencia = '') => `
  <tr><td colspan="${colspan}" class="px-4 py-10">
    <div class="flex flex-col items-center gap-2 text-center">
      <svg class="h-9 w-9 text-dorado" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8M12 13v8" />
      </svg>
      <p class="font-medium text-stone-600">${App.esc(texto)}</p>
      ${sugerencia ? `<p class="text-xs text-stone-400 max-w-sm">${App.esc(sugerencia)}</p>` : ''}
    </div>
  </td></tr>`;

/* Filas grises animadas mientras se espera la API */
App.filasCargando = (colspan, filas = 3) => Array.from({ length: filas }, (_, i) => `
  <tr class="border-t border-stone-100">${Array.from({ length: colspan }, () =>
    `<td class="px-4 py-3"><span class="gs-skeleton" style="width:${[70, 50, 85][i % 3]}%"></span></td>`).join('')}</tr>`).join('');

/* Confirmación con el estilo de la app (<x-confirmar>). Devuelve una promesa true/false. */
App.confirmar = ({ titulo, mensaje = '', boton = 'Confirmar' }) => new Promise((resolve) => {
  const caja = document.getElementById('gs-confirmar');
  if (!caja) return resolve(window.confirm(titulo));
  const si = document.getElementById('gs-confirmar-si');
  const no = document.getElementById('gs-confirmar-no');
  document.getElementById('gs-confirmar-titulo').textContent = titulo;
  document.getElementById('gs-confirmar-mensaje').textContent = mensaje;
  si.textContent = boton;
  const cerrar = (valor) => {
    caja.classList.add('hidden');
    si.removeEventListener('click', alSi);
    no.removeEventListener('click', alNo);
    document.removeEventListener('keydown', alTecla);
    resolve(valor);
  };
  const alSi = () => cerrar(true);
  const alNo = () => cerrar(false);
  const alTecla = (e) => { if (e.key === 'Escape') cerrar(false); };
  si.addEventListener('click', alSi);
  no.addEventListener('click', alNo);
  document.addEventListener('keydown', alTecla);
  caja.classList.remove('hidden');
  no.focus();
});

/* conReferencia=true agrega la columna Referencia (pantalla de Movimientos) */
App.filaMovimiento = (m, conReferencia = false) => `
  <tr class="border-t border-stone-100">
    <td class="px-4 py-2.5 whitespace-nowrap">${App.esc(m.fecha)}</td>
    <td class="px-4 py-2.5">${App.badgeTipo(m.tipo, m.causa, m.clase)}</td>
    <td class="px-4 py-2.5">${App.esc(m.producto)}</td>
    <td class="px-4 py-2.5">${App.fmtNum(m.cantidad)} ${App.esc(m.unidad)}</td>
    <td class="px-4 py-2.5">${App.esc(m.usuario)}</td>
    ${conReferencia ? `<td class="px-4 py-2.5 text-stone-500">${App.esc(m.referencia) || '—'}</td>` : ''}
  </tr>`;

/* <option> de productos activos para selects (con stock visible si se pide) */
App.opcionesProductos = (productos, { conStock = false, excluir = null, vacio = 'Selecciona un producto…' } = {}) =>
  `<option value="">${App.esc(vacio)}</option>` + productos
    .filter((p) => p.estado === 'activo' && p.id !== excluir)
    .map((p) => `<option value="${p.id}">${App.esc(p.nombre)}${conStock ? ` — stock: ${App.fmtNum(p.stock)} ${App.esc(p.unidad)}` : ` (${App.esc(p.unidad)})`}</option>`)
    .join('');

/* Modales (<x-modal>): al abrir, el foco va al primer campo */
App.abrirModal = (id) => {
  const m = document.getElementById(id);
  m.classList.remove('hidden');
  m.querySelector('input:not([type=hidden]):not(:disabled), select:not(:disabled), textarea')?.focus();
};
App.cerrarModal = (id) => document.getElementById(id).classList.add('hidden');

/* Descarga un CSV (con BOM para que Excel respete tildes y ñ) */
App.descargarCSV = (nombre, columnas, filas) => {
  const celda = (v) => {
    const s = String(v ?? '');
    return /[",\n;]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
  };
  const lineas = [columnas.map(celda).join(','), ...filas.map((f) => f.map(celda).join(','))];
  const blob = new Blob(['﻿' + lineas.join('\r\n')], { type: 'text/csv;charset=utf-8' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = nombre;
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(a.href), 1000);
};

/* ------------------------------------------------------------
 * 4) Shell compartido: guardia de sesión, permisos por rol,
 *    datos del usuario en <x-topbar> y menú de <x-sidebar>.
 *    Devuelve false si la página no se debe inicializar.
 * ------------------------------------------------------------ */
App.renderShell = () => {
  const u = App.cargarSesion();
  if (!u || !localStorage.getItem('gs_token')) {
    window.location.href = '/login';
    return false;
  }

  // Página restringida (<x-layouts.app acceso="admin">)
  const acceso = document.body.dataset.acceso;
  if (acceso && !acceso.split(',').includes(u.rol)) {
    window.location.href = '/dashboard';
    return false;
  }

  // Elementos con data-roles: se quitan si el rol no aplica, se muestran si sí
  document.querySelectorAll('[data-roles]').forEach((el) => {
    if (el.dataset.roles.split(',').includes(u.rol)) el.removeAttribute('data-roles');
    else el.remove();
  });

  const nombre = document.getElementById('gs-user-nombre');
  if (nombre) nombre.textContent = u.nombre;
  const inicial = document.getElementById('gs-user-inicial');
  if (inicial) inicial.textContent = (u.nombre || '?').trim().charAt(0).toUpperCase();
  const rol = document.getElementById('gs-user-rol');
  if (rol) rol.textContent = App.rolBonito(u.rol);
  const hoy = document.getElementById('gs-fecha-hoy');
  if (hoy) hoy.textContent = new Date().toLocaleDateString('es-SV', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });

  document.getElementById('gs-salir')?.addEventListener('click', async () => {
    try { await App.api('/logout', { method: 'POST' }); } catch (_) { /* noop */ }
    App.clearSesion();
    window.location.href = '/login';
  });

  // Menú lateral en celular/tablet
  const sidebar = document.getElementById('gs-sidebar');
  const fondo = document.getElementById('gs-sidebar-fondo');
  const toggleMenu = (abrir) => {
    sidebar?.classList.toggle('-translate-x-full', !abrir);
    fondo?.classList.toggle('hidden', !abrir);
  };
  document.getElementById('gs-menu-btn')?.addEventListener('click', () => toggleMenu(true));
  document.getElementById('gs-menu-cerrar')?.addEventListener('click', () => toggleMenu(false));
  fondo?.addEventListener('click', () => toggleMenu(false));

  return true;
};

/* ------------------------------------------------------------
 * 5) Router de páginas (usa data-page en <body>)
 * ------------------------------------------------------------ */
document.addEventListener('DOMContentLoaded', () => {
  // Cualquier botón data-cerrar-modal cierra su <x-modal>; también clic en el fondo y Esc
  document.querySelectorAll('[data-cerrar-modal]').forEach((b) =>
    b.addEventListener('click', () => b.closest('.gs-modal').classList.add('hidden')));
  document.querySelectorAll('.gs-modal').forEach((m) =>
    m.addEventListener('mousedown', (e) => { if (e.target === m) m.classList.add('hidden'); }));
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') document.querySelectorAll('.gs-modal:not(.hidden)').forEach((m) => m.classList.add('hidden'));
  });

  const page = document.body.dataset.page;
  if (page !== 'login' && !App.renderShell()) return;
  if (page && typeof App['init_' + page] === 'function') {
    App['init_' + page]();
  }
});

window.App = App;
