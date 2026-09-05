{{-- Login (Anexo A): solo el layout base, sin sidebar ni topbar. Logo sobre negro + formulario en crema --}}
<x-layouts.base title="Acceso al sistema" page="login"
                body-class="min-h-screen bg-crema text-stone-800 font-sans antialiased">

  <div class="min-h-screen grid lg:grid-cols-2">
    {{-- Marca --}}
    <section class="bg-negro bg-[radial-gradient(120%_90%_at_30%_20%,#3A2C1F_0%,#1C1713_60%)] text-crema
                    flex flex-col items-center justify-center gap-5 px-6 py-10 lg:py-16 text-center">
      <img src="{{ asset('img/logo-goldstreet.png') }}" alt="Restaurante Gold Street"
           class="w-40 sm:w-52 lg:w-80 h-auto drop-shadow-xl" />
      <p class="text-[11px] tracking-[0.3em] text-dorado">AMBIENTE · SABOR · ATENCIÓN</p>
      <p class="hidden lg:block text-sm text-crema/60">GoldTrack · Sistema de control de inventarios</p>
    </section>

    {{-- Formulario --}}
    <section class="flex flex-col items-center justify-center px-4 py-10">
      <main class="w-full max-w-sm">
        <h1 class="font-display text-3xl font-bold text-negro">Bienvenido</h1>
        <p class="text-sm text-stone-500 mt-1 mb-6">Ingresa con tu cuenta del restaurante para continuar.</p>

        <div id="gs-login-error" role="alert" class="hidden mb-4 text-sm bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3"></div>

        {{-- Ayudas de la demo (selector de cuenta y contraseñas): solo en desarrollo, APP_ENV=local --}}
        @php $demo = app()->environment('local'); @endphp

        <form id="gs-form-login" class="space-y-4">
          @if ($demo)
            <x-field label="Cuenta demo" for="gs-rol" ayuda="Solo rellena el correo. El rol lo define la cuenta, no esta lista.">
              <select id="gs-rol" class="gs-input !py-2.5">
                <option value="admin">Administrador</option>
                <option value="bodega">Encargado de Bodega</option>
                <option value="cocina">Chef de Cocina</option>
              </select>
            </x-field>
          @endif
          <x-field label="Correo" for="gs-correo">
            <input id="gs-correo" type="email" required @if($demo) value="admin@goldstreet.com" @endif autofocus
                   autocomplete="username" placeholder="tu.correo@goldstreet.com" class="gs-input !py-2.5" />
          </x-field>
          <x-field label="Contraseña" for="gs-contrasena">
            <input id="gs-contrasena" type="password" required autocomplete="current-password" class="gs-input !py-2.5" />
          </x-field>
          <button type="submit" id="gs-login-btn" class="gs-btn-primario w-full !py-3">Iniciar sesión</button>
        </form>

        @if ($demo)
          <p class="text-xs text-stone-500 mt-6 pt-4 border-t border-plato leading-relaxed">
            <span class="font-semibold text-stone-600">Credenciales demo:</span> admin123 · bodega123 · cocina123 (según la cuenta elegida).
          </p>
        @else
          <p class="text-xs text-stone-500 mt-6 pt-4 border-t border-plato leading-relaxed">
            ¿Olvidaste tu contraseña? Pídele al administrador que la restablezca desde Usuarios.
          </p>
        @endif
      </main>

      <x-footer class="mt-10 !border-0" />
    </section>
  </div>

  @push('scripts')
    <script src="{{ asset('js/pages/login.js') }}"></script>
  @endpush
</x-layouts.base>
