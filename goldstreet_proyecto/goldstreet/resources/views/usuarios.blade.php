{{-- Usuarios y roles (solo admin): alta, edición, cambio de contraseña y activación --}}
<x-layouts.app title="Usuarios" page="usuarios" acceso="admin">

  <x-page-header title="Usuarios" subtitle="Cuentas de acceso y roles del sistema (admin · bodega · cocina).">
    <button id="gs-btn-nuevo-usuario" class="gs-btn-primario">+ Nuevo usuario</button>
  </x-page-header>

  <x-card :padding="false">
    <x-data-table tbody="gs-tabla-usuarios" min-width="720px"
                  :columnas="['Nombre', 'Correo', 'Rol', 'Estado', 'Creado', ['Acciones', 'text-right']]" />
  </x-card>

  <x-slot:modales>
    <x-modal id="gs-modal-usuario" form="gs-form-usuario" title="Nuevo usuario" title-id="gs-usuario-titulo">
      <input type="hidden" id="gs-usr-id" value="" />
      <x-field label="Nombre completo" for="gs-usr-nombre">
        <input id="gs-usr-nombre" required maxlength="100" class="gs-input" />
      </x-field>
      <x-field label="Correo" for="gs-usr-correo">
        <input id="gs-usr-correo" type="email" required maxlength="120" class="gs-input" />
      </x-field>
      <x-field label="Contraseña" for="gs-usr-contrasena" ayuda="Mínimo 6 caracteres." ayuda-id="gs-usr-contrasena-ayuda">
        <input id="gs-usr-contrasena" type="password" minlength="6" autocomplete="new-password" class="gs-input" />
      </x-field>
      <x-field label="Rol" for="gs-usr-rol">
        <select id="gs-usr-rol" required class="gs-input">
          <option value="admin">Administrador</option>
          <option value="bodega">Encargado de Bodega</option>
          <option value="cocina">Chef de Cocina</option>
        </select>
      </x-field>
      <label class="flex items-center gap-2 text-sm text-stone-600">
        <input id="gs-usr-activo" type="checkbox" class="h-4 w-4 accent-ocre" checked />
        Cuenta activa (puede iniciar sesión)
      </label>
    </x-modal>
  </x-slot:modales>

  @push('scripts')
    <script src="{{ asset('js/pages/usuarios.js') }}"></script>
  @endpush
</x-layouts.app>
