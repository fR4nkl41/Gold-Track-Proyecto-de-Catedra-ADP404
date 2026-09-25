{{-- Reportes (Sprint 2, solo admin): existencias, consumo por período y kardex por producto --}}
<x-layouts.app title="Reportes" page="reportes" acceso="admin">

  <x-page-header title="Reportes" subtitle="Existencias, consumo por período, mermas, rendimiento de producción y kardex por producto.">
    <button id="gs-rep-csv" class="gs-btn-dorado">Exportar CSV</button>
    <button id="gs-rep-imprimir" class="gs-btn-primario">Imprimir / PDF</button>
  </x-page-header>

  {{-- Encabezado de marca que solo aparece al imprimir o guardar en PDF --}}
  <header class="hidden print:flex items-center gap-4 pb-3 mb-5 border-b-2 border-dorado">
    <img src="{{ asset('img/logo-goldstreet.png') }}" alt="Restaurante Gold Street" class="h-20 w-20 object-contain" />
    <div class="flex-1">
      <p class="text-[10px] tracking-[0.3em] text-ocre font-semibold">RESTAURANTE GOLD STREET · GOLDTRACK</p>
      <p id="gs-rep-titulo-impresion" class="font-display text-2xl font-bold text-negro leading-tight"></p>
      <p id="gs-rep-fecha-impresion" class="text-xs text-stone-500 mt-0.5"></p>
    </div>
  </header>

  {{-- Pestañas --}}
  <nav class="flex gap-1 mb-4 border-b border-stone-200 overflow-x-auto whitespace-nowrap print:hidden" role="tablist">
    <button data-tab="existencias" class="gs-tab px-4 py-2 text-sm font-semibold border-b-2 -mb-px">Existencias</button>
    <button data-tab="consumo" class="gs-tab px-4 py-2 text-sm font-semibold border-b-2 -mb-px">Consumo por período</button>
    <button data-tab="mermas" class="gs-tab px-4 py-2 text-sm font-semibold border-b-2 -mb-px">Mermas</button>
    <button data-tab="rendimiento" class="gs-tab px-4 py-2 text-sm font-semibold border-b-2 -mb-px">Rendimiento</button>
    <button data-tab="kardex" class="gs-tab px-4 py-2 text-sm font-semibold border-b-2 -mb-px">Kardex por producto</button>
  </nav>

  {{-- Filtros (fechas para consumo y kardex; producto solo para kardex) --}}
  <x-card id="gs-rep-filtros" class="mb-6 print:hidden" :padding="false">
    <form id="gs-rep-form" class="p-4 grid sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
      <x-field label="Producto" for="gs-rep-producto" id="gs-rep-campo-producto" class="lg:col-span-2">
        <select id="gs-rep-producto" class="gs-input"></select>
      </x-field>
      <x-field label="Desde" for="gs-rep-desde">
        <input id="gs-rep-desde" type="date" class="gs-input" />
      </x-field>
      <x-field label="Hasta" for="gs-rep-hasta">
        <input id="gs-rep-hasta" type="date" class="gs-input" />
      </x-field>
      <button type="submit" class="gs-btn-primario">Generar</button>
    </form>
  </x-card>

  {{-- Resumen del reporte activo --}}
  <section id="gs-rep-resumen" class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6"></section>

  <x-card :padding="false">
    <div class="overflow-x-auto">
      <table class="w-full text-sm min-w-[720px]">
        <thead id="gs-rep-thead" class="bg-negro text-left text-[11px] uppercase tracking-wider text-crema/80"></thead>
        <tbody id="gs-rep-tbody" class="tabular-nums"></tbody>
      </table>
    </div>
  </x-card>

  {{-- Pie del documento impreso --}}
  <p class="hidden print:block mt-6 pt-2 border-t border-stone-300 text-[10px] text-stone-500">
    GoldTrack v{{ config('app.version') }} · Documento generado desde el sistema de control de inventarios de Restaurante Gold Street.
  </p>

  @push('scripts')
    <script src="{{ asset('js/pages/reportes.js') }}"></script>
  @endpush
</x-layouts.app>
