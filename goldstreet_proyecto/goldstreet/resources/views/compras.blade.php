{{-- Lista de compras sugerida y órdenes de compra (fase 3, admin y bodega; precios solo admin) --}}
<x-layouts.app title="Compras" page="compras" acceso="admin,bodega">

  @push('head')
    <style>
      /* Al imprimir una orden solo sale la hoja de la orden */
      #gs-oc-impresion { display: none; }
      @media print {
        body.gs-imprimir-oc main > *:not(#gs-oc-impresion) { display: none !important; }
        body.gs-imprimir-oc #gs-oc-impresion { display: block; }
      }
    </style>
  @endpush

  <x-page-header title="Compras" subtitle="Lo que hay que pedir según el stock mínimo de cada producto, y las órdenes enviadas a los proveedores." />

  <nav class="flex gap-1 mb-6 border-b border-stone-200 overflow-x-auto whitespace-nowrap print:hidden" role="tablist">
    <button data-tab="sugerida" class="gs-tab px-4 py-2 text-sm font-semibold border-b-2 -mb-px">Lista de compras sugerida</button>
    <button data-tab="ordenes" class="gs-tab px-4 py-2 text-sm font-semibold border-b-2 -mb-px">Órdenes de compra</button>
  </nav>

  {{-- ---- Lista sugerida ---- --}}
  <section id="gs-panel-sugerida">
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
      <x-stat-card id="gs-sug-productos" label="Productos por pedir" />
      <x-stat-card id="gs-sug-proveedores" label="Proveedores" />
      <x-stat-card id="gs-sug-estimado" data-roles="admin" label="Costo estimado" tono="destacado" valor="$0.00" detalle="con el último precio de compra" />
    </div>
    <p class="text-sm text-stone-500 mb-4">
      Entran los productos que están en su stock mínimo o debajo. La cantidad sugerida repone hasta el stock ideal
      (o el doble del mínimo) y descuenta lo que ya viene en órdenes pendientes. Ajusta las cantidades antes de generar la orden.
    </p>
    <div id="gs-sug-grupos" class="space-y-6"></div>

    <x-card title="Por preparar en cocina" subtitle="Porciones bajo su mínimo: no se compran, se producen" class="mt-6" :padding="false">
      <x-data-table tbody="gs-sug-preparar" variante="clara" :columnas="['Porción', ['Hay', 'text-right'], ['Preparar', 'text-right'], ['', 'text-right']]" />
    </x-card>
  </section>

  {{-- ---- Órdenes ---- --}}
  <section id="gs-panel-ordenes" class="hidden">
    <x-card class="mb-6" :padding="false">
      <form id="gs-oc-filtros" class="p-4 grid sm:grid-cols-3 lg:grid-cols-4 gap-3 items-end">
        <x-field label="Estado" for="gs-oc-f-estado">
          <select id="gs-oc-f-estado" class="gs-input">
            <option value="">Todas</option>
            <option value="pendiente">Pendientes</option>
            <option value="recibida">Recibidas</option>
            <option value="cancelada">Canceladas</option>
          </select>
        </x-field>
        <x-field label="Proveedor" for="gs-oc-f-proveedor" class="lg:col-span-2">
          <select id="gs-oc-f-proveedor" class="gs-input"><option value="">Todos</option></select>
        </x-field>
        <button type="submit" class="gs-btn-dorado">Filtrar</button>
      </form>
    </x-card>
    <x-card :padding="false">
      <div class="overflow-x-auto">
        <table class="w-full text-sm tabular-nums min-w-[760px]">
          <thead class="bg-negro text-left text-[11px] uppercase tracking-wider text-crema/80">
            <tr>
              <th class="px-4 py-3 font-semibold">Orden</th>
              <th class="px-4 py-3 font-semibold">Proveedor</th>
              <th class="px-4 py-3 font-semibold">Fecha</th>
              <th class="px-4 py-3 font-semibold">Entrega</th>
              <th class="px-4 py-3 font-semibold">Productos</th>
              <th data-roles="admin" class="px-4 py-3 font-semibold text-right">Estimado</th>
              <th class="px-4 py-3 font-semibold">Estado</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody id="gs-tabla-ordenes"></tbody>
        </table>
      </div>
    </x-card>
  </section>

  {{-- Hoja para imprimir o mandar al proveedor --}}
  <section id="gs-oc-impresion">
    <header class="flex items-center gap-4 pb-3 mb-5 border-b-2 border-dorado">
      <img src="{{ asset('img/logo-goldstreet.png') }}" alt="Restaurante Gold Street" class="h-20 w-20 object-contain" />
      <div class="flex-1">
        <p class="text-[10px] tracking-[0.3em] text-ocre font-semibold">RESTAURANTE GOLD STREET · ORDEN DE COMPRA</p>
        <p id="gs-oci-titulo" class="font-display text-2xl font-bold text-negro leading-tight"></p>
        <p id="gs-oci-sub" class="text-xs text-stone-500 mt-0.5"></p>
      </div>
    </header>
    <table class="w-full text-sm tabular-nums">
      <thead class="bg-negro text-left text-[11px] uppercase tracking-wider text-crema/80" id="gs-oci-thead"></thead>
      <tbody id="gs-oci-lineas"></tbody>
    </table>
    <p id="gs-oci-notas" class="text-sm text-stone-600 mt-4"></p>
    <p class="mt-10 text-sm">Recibido por: ______________________________ &nbsp; Fecha: ______________</p>
  </section>

  <x-slot:modales>
    {{-- Detalle de la orden --}}
    <div id="gs-modal-orden" class="gs-modal hidden fixed inset-0 z-50 bg-negro/60 flex items-center justify-center p-4 print:hidden"
         role="dialog" aria-modal="true" aria-labelledby="gs-ocd-titulo">
      <div class="bg-white rounded-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden shadow-2xl">
        <header class="bg-negro px-6 py-4 flex items-center justify-between gap-3">
          <div>
            <h3 id="gs-ocd-titulo" class="font-display text-xl font-bold text-dorado"></h3>
            <p id="gs-ocd-sub" class="text-xs text-crema/60"></p>
          </div>
          <button type="button" data-cerrar-modal class="p-1 rounded text-crema/60 hover:text-dorado" aria-label="Cerrar">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
          </button>
        </header>
        <div class="p-6 overflow-y-auto">
          <div class="overflow-x-auto border border-plato rounded-xl">
            <table class="w-full text-sm tabular-nums">
              <thead id="gs-ocd-thead" class="bg-plato/60 text-left text-[11px] uppercase tracking-wider text-stone-500"></thead>
              <tbody id="gs-ocd-lineas"></tbody>
            </table>
          </div>
          <p id="gs-ocd-notas" class="text-sm text-stone-500 mt-3"></p>
        </div>
        <footer class="flex flex-wrap justify-between gap-2 px-6 py-4 bg-crema border-t border-plato">
          <div class="flex gap-2">
            <button type="button" id="gs-ocd-cancelar" class="gs-btn-merma">Cancelar orden</button>
            <button type="button" id="gs-ocd-imprimir" class="gs-btn-neutro">Imprimir</button>
          </div>
          <a id="gs-ocd-recibir" href="#" class="gs-btn-primario">Recibir con factura</a>
        </footer>
      </div>
    </div>
  </x-slot:modales>

  @push('scripts')
    <script src="{{ asset('js/pages/compras.js') }}"></script>
  @endpush
</x-layouts.app>
