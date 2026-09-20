{{--
  <x-topbar :menu="true"> : encabezado superior con el usuario en sesión y "Salir".
  menu=true muestra el botón ☰ que abre <x-sidebar> en pantallas pequeñas y tablets.
  Nombre, inicial y rol los rellena core.js (App.renderShell) desde la sesión guardada.
--}}
@props(['menu' => true])

<header class="sticky top-0 z-20 bg-white/95 backdrop-blur border-b border-plato print:hidden">
  <div class="px-4 py-3 flex items-center gap-3">
    @if ($menu)
      <button id="gs-menu-btn" type="button" class="lg:hidden p-2 -ml-2 rounded-md text-negro hover:bg-plato" aria-label="Abrir menú">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
          <path d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
      <img src="{{ asset('img/logo-sm.png') }}" alt="" class="lg:hidden h-9 w-9 object-contain" />
    @endif

    <p id="gs-fecha-hoy" class="hidden md:block text-sm text-stone-500 capitalize"></p>

    <div class="ml-auto flex items-center gap-3">
      <span id="gs-user-rol" class="text-[10px] font-bold uppercase tracking-wider bg-negro text-dorado px-2 py-0.5 rounded-full"></span>
      <span id="gs-user-nombre" class="hidden sm:inline text-sm font-medium text-stone-700"></span>
      <span id="gs-user-inicial" class="hidden sm:grid h-8 w-8 place-items-center rounded-full bg-dorado text-negro font-bold text-sm" aria-hidden="true"></span>
      <button id="gs-salir" type="button" class="gs-btn-neutro !py-1.5">Salir</button>
    </div>
  </div>
</header>
