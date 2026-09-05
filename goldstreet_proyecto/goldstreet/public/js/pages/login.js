/* Login (RF01): valida credenciales contra /api/login y guarda el token Sanctum */
App.init_login = () => {
  App.clearSesion();

  const form = document.getElementById('gs-form-login');
  const err = document.getElementById('gs-login-error');
  const selRol = document.getElementById('gs-rol');
  const correo = document.getElementById('gs-correo');
  const pass = document.getElementById('gs-contrasena');

  const DEMO = {
    admin:  'admin@goldstreet.com',
    bodega: 'bodega@goldstreet.com',
    cocina: 'cocina@goldstreet.com',
  };
  // El selector de cuentas demo solo existe con APP_ENV=local
  selRol?.addEventListener('change', () => { correo.value = DEMO[selRol.value] || ''; });

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    err.classList.add('hidden');
    const btn = document.getElementById('gs-login-btn');
    btn.disabled = true;
    btn.textContent = 'Ingresando…';
    try {
      const r = await App.api('/login', {
        method: 'POST',
        body: { correo: correo.value, contrasena: pass.value },
      });
      App.guardarSesion(r.token, r.usuario);
      window.location.href = '/dashboard';
    } catch (ex) {
      err.textContent = ex.message;
      err.classList.remove('hidden');
      btn.disabled = false;
      btn.textContent = 'Iniciar sesión';
    }
  });
};
