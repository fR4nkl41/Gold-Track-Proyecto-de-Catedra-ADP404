/* Usuarios y roles (solo admin): alta, edición, cambio de contraseña y activación */
App.init_usuarios = async () => {
  const $ = (id) => document.getElementById(id);

  const badgeRol = (r) => {
    // Mismo código de color que la maqueta: admin dorado · bodega ocre · cocina negro
    const colores = { admin: 'bg-doradoClaro text-amber-900', bodega: 'bg-orange-100 text-ocreOscuro', cocina: 'bg-negro text-dorado' };
    return `<span class="gs-pill ${colores[r] || ''}">${App.esc(App.rolBonito(r))}</span>`;
  };

  const pinta = () => {
    const tb = $('gs-tabla-usuarios');
    tb.innerHTML = App.D.usuarios.length ? App.D.usuarios.map((u) => `
      <tr class="border-t border-stone-100 ${u.activo ? '' : 'opacity-60'}">
        <td class="px-4 py-2.5 font-medium">${App.esc(u.nombre)}${u.id === App.D.usuario.id ? ' <span class="text-xs text-stone-400">(tú)</span>' : ''}</td>
        <td class="px-4 py-2.5">${App.esc(u.correo)}</td>
        <td class="px-4 py-2.5">${badgeRol(u.rol)}</td>
        <td class="px-4 py-2.5">${u.activo
          ? '<span class="gs-pill bg-emerald-50 text-emerald-700">Activo</span>'
          : '<span class="gs-pill bg-stone-100 text-stone-500">Inactivo</span>'}</td>
        <td class="px-4 py-2.5 text-stone-500">${App.esc(u.creado) || '—'}</td>
        <td class="px-4 py-2.5 text-right whitespace-nowrap">
          <button data-editar="${u.id}" class="gs-btn-mini">Editar</button>
          ${u.id !== App.D.usuario.id ? `<button data-estado="${u.id}" class="${u.activo ? 'gs-btn-mini-peligro' : 'gs-btn-mini'}">${u.activo ? 'Desactivar' : 'Activar'}</button>` : ''}
        </td>
      </tr>`).join('')
      : App.filaVacia(6, 'No hay usuarios registrados.');

    tb.querySelectorAll('[data-editar]').forEach((b) =>
      b.addEventListener('click', () => abrir(Number(b.dataset.editar))));
    tb.querySelectorAll('[data-estado]').forEach((b) =>
      b.addEventListener('click', () => cambiarEstado(Number(b.dataset.estado))));
  };

  const cargar = async () => {
    try {
      App.D.usuarios = (await App.api('/usuarios')).data;
      pinta();
    } catch (e) {
      App.toast(e.message, 'error');
    }
  };

  const abrir = (id) => {
    const u = id ? App.D.usuarios.find((x) => x.id === id) : null;
    const esYo = u && u.id === App.D.usuario.id;
    $('gs-usuario-titulo').textContent = u ? 'Editar usuario' : 'Nuevo usuario';
    $('gs-usr-id').value = u ? u.id : '';
    $('gs-usr-nombre').value = u ? u.nombre : '';
    $('gs-usr-correo').value = u ? u.correo : '';
    $('gs-usr-contrasena').value = '';
    $('gs-usr-contrasena').required = !u;
    $('gs-usr-contrasena-ayuda').textContent = u ? 'Déjala vacía para no cambiarla. Mínimo 6 caracteres.' : 'Mínimo 6 caracteres.';
    $('gs-usr-rol').value = u ? u.rol : 'bodega';
    $('gs-usr-activo').checked = u ? u.activo : true;
    // El admin no puede quitarse su propio rol ni desactivarse (también lo valida la API)
    $('gs-usr-rol').disabled = !!esYo;
    $('gs-usr-activo').disabled = !!esYo;
    App.abrirModal('gs-modal-usuario');
  };

  const guardar = async (cuerpo, id) => {
    const r = id
      ? await App.api('/usuarios/' + id, { method: 'PUT', body: cuerpo })
      : await App.api('/usuarios', { method: 'POST', body: cuerpo });
    App.toast(r.message);
  };

  const cambiarEstado = async (id) => {
    const u = App.D.usuarios.find((x) => x.id === id);
    const accion = u?.activo ? 'Desactivar' : 'Activar';
    if (!u || !(await App.confirmar({
      titulo: `¿${accion} la cuenta de ${u.nombre}?`,
      mensaje: u.activo
        ? 'No podrá iniciar sesión y se cerrarán sus sesiones abiertas. Su historial se conserva.'
        : 'Podrá volver a iniciar sesión con su correo y contraseña.',
      boton: accion,
    }))) return;
    try {
      await guardar({ nombre: u.nombre, correo: u.correo, rol: u.rol, activo: !u.activo }, id);
      await cargar();
    } catch (e) {
      App.toast(e.message, 'error');
    }
  };

  $('gs-btn-nuevo-usuario').addEventListener('click', () => abrir(null));

  $('gs-form-usuario').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = $('gs-usr-id').value;
    const cuerpo = {
      nombre: $('gs-usr-nombre').value.trim(),
      correo: $('gs-usr-correo').value.trim(),
      rol: $('gs-usr-rol').value,
      activo: $('gs-usr-activo').checked,
      ...($('gs-usr-contrasena').value ? { contrasena: $('gs-usr-contrasena').value } : {}),
    };
    try {
      await guardar(cuerpo, id);
      App.cerrarModal('gs-modal-usuario');
      await cargar();
    } catch (ex) {
      App.toast(ex.message, 'error');
    }
  });

  await cargar();
};
