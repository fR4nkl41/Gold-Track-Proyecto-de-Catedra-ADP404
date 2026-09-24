{{-- Ficha del proveedor (fase 2, solo admin): contacto, productos que se le compran, precios y facturas --}}
<x-layouts.app title="Ficha de proveedor" page="proveedor" seccion="catalogos" acceso="admin">

  <a href="/catalogos" class="inline-flex items-center gap-1 text-sm font-semibold text-ocre hover:underline mb-3 print:hidden">← Catálogos</a>

  <x-page-header title="Proveedor" subtitle="Cargando…">
    <a href="/facturas" class="gs-btn-neutro">Ver todas las facturas</a>
  </x-page-header>

  <section class="grid lg:grid-cols-3 gap-6 mb-6">
    <x-card title="Contacto" class="lg:col-span-1">
      <dl id="gs-pv-contacto" class="grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm"></dl>
    </x-card>
    <div class="lg:col-span-2 grid grid-cols-2 gap-4 content-start">
      <x-stat-card id="gs-pv-total" label="Total comprado" tono="destacado" valor="$0.00" detalle="facturas registradas" />
      <x-stat-card id="gs-pv-mes" label="Comprado este mes" tono="dorado" valor="$0.00" />
      <x-stat-card id="gs-pv-facturas" label="Facturas" />
      <x-stat-card id="gs-pv-ultima" label="Última compra" valor="—" />
    </div>
  </section>

  <x-card title="Productos que se le compran" subtitle="Precios tomados de sus facturas (sin las anuladas). La flecha compara con la compra anterior." :padding="false" class="mb-6">
    <x-data-table tbody="gs-pv-productos" variante="clara" min-width="820px"
                  :columnas="['Producto', ['Compras', 'text-right'], ['Cantidad', 'text-right'], ['Último precio', 'text-right'], ['Promedio', 'text-right'], ['Mín – Máx', 'text-right'], ['Total', 'text-right'], 'Última compra']" />
  </x-card>

  <section class="grid lg:grid-cols-3 gap-6">
    <x-card title="Facturas recientes" :padding="false" class="lg:col-span-2">
      <x-data-table tbody="gs-pv-facturas-tabla" variante="clara" min-width="520px"
                    :columnas="['Fecha', 'Número', ['Productos', 'text-right'], ['Total', 'text-right'], 'Estado', ['', 'text-right']]" />
    </x-card>
    <x-card title="Asignados en el catálogo" subtitle="productos cuyo proveedor principal es este">
      <ul id="gs-pv-asignados" class="space-y-1.5 text-sm"></ul>
    </x-card>
  </section>

  @push('scripts')
    <script src="{{ asset('js/pages/proveedor.js') }}"></script>
  @endpush
</x-layouts.app>
