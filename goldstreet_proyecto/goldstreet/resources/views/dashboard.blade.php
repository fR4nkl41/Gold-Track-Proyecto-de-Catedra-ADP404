{{-- Dashboard (Anexo B): indicadores, gráfico semanal, inventario reciente, críticos y últimos movimientos --}}
<x-layouts.app title="Dashboard" page="dashboard">

  <x-page-header title="Dashboard" subtitle="Resumen general del inventario del restaurante." />

  {{-- Accesos rápidos según el rol (cada botón se muestra solo a quien puede usarlo) --}}
  <section class="flex flex-wrap gap-2 mb-6">
    <a href="/movimientos?nuevo=entrada" data-roles="admin,bodega" class="gs-btn-primario">+ Registrar entrada</a>
    <a href="/movimientos?nuevo=salida"  class="gs-btn-negro">− Registrar salida</a>
    <a href="/movimientos?nuevo=merma"   class="gs-btn-merma">Registrar merma</a>
    <a href="/compras"                   data-roles="admin,bodega" class="gs-btn-secundario">Lista de compras</a>
    <a href="/facturas?nuevo=1"          data-roles="admin,bodega" class="gs-btn-secundario">Registrar factura</a>
    <a href="/produccion?nuevo=1"        data-roles="admin,cocina" class="gs-btn-secundario">Registrar producción</a>
    <a href="/inventario?nuevo=1"        data-roles="admin"        class="gs-btn-secundario">Nuevo producto</a>
    <a href="/reportes"                  data-roles="admin"        class="gs-btn-secundario">Ver reportes</a>
    <a href="/inventario" class="gs-btn-neutro">Ver inventario</a>
  </section>

  {{-- Aviso de pagos vencidos o por vencer (solo admin; lo llena dashboard.js) --}}
  <a id="gs-aviso-pagos" href="/cuentas-por-pagar" data-roles="admin"
     class="hidden mb-6 flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 hover:bg-red-100"></a>

  <section class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4 mb-8">
    <x-stat-card id="gs-stat-productos" label="Productos activos" />
    <x-stat-card id="gs-stat-valor" label="Valor del inventario" tono="dorado" valor="$0.00" detalle="a costo" />
    <x-stat-card id="gs-stat-alertas" label="Alertas de stock" tono="aviso" />
    <x-stat-card id="gs-stat-entradas" label="Entradas hoy" tono="verde" />
    <x-stat-card id="gs-stat-salidas" label="Salidas hoy" />
    <x-stat-card id="gs-stat-mermas" label="Mermas del mes" tono="destacado" valor="$0.00" detalle="pérdida a costo" />
  </section>

  <section class="grid lg:grid-cols-5 gap-6 mb-8">
    <x-card title="Consumo semanal" subtitle="Valor en $ de entradas, salidas y mermas · últimos 7 días" class="lg:col-span-3">
      <div class="relative h-64">
        <canvas id="gs-grafico-semanal" aria-label="Gráfico de entradas, salidas y mermas de los últimos 7 días"></canvas>
      </div>
    </x-card>

    <x-card title="Inventario reciente" subtitle="productos con movimiento más reciente" :padding="false" class="lg:col-span-2">
      <x-data-table tbody="gs-recientes" variante="clara" :columnas="['Producto', 'Stock', 'Estado']" />
    </x-card>
  </section>

  <section class="grid lg:grid-cols-2 gap-6">
    <x-card title="Productos críticos" subtitle="stock igual o menor al mínimo, o agotado" :padding="false">
      <x-data-table tbody="gs-criticos" variante="clara"
                    :columnas="['Producto', 'Categoría', 'Stock', 'Mínimo', 'Estado']" />
    </x-card>

    <x-card title="Últimos movimientos" :padding="false">
      <x-slot:acciones>
        <a href="/movimientos" class="text-xs font-semibold text-ocre hover:underline">Ver todos</a>
      </x-slot:acciones>
      <x-data-table tbody="gs-ultimos" variante="clara"
                    :columnas="['Fecha', 'Tipo', 'Producto', 'Cant.', 'Usuario']" />
    </x-card>
  </section>

  @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.js"></script>
    <script src="{{ asset('js/pages/dashboard.js') }}"></script>
  @endpush
</x-layouts.app>
