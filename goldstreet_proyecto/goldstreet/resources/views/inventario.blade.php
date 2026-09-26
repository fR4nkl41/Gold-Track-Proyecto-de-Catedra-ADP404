{{-- Inventario (Anexo C): catálogo con filtros (RF06) y alta/edición/baja (RF02, solo admin) --}}
<x-layouts.app title="Inventario" page="inventario">

  <x-page-header title="Inventario" subtitle="Catálogo de productos, stock y alertas (RF05 · RF06).">
    <button id="gs-btn-nuevo" data-roles="admin" class="gs-btn-primario">+ Nuevo producto</button>
  </x-page-header>

  <x-card class="mb-6" :padding="false">
    <div class="p-4 grid md:grid-cols-4 gap-3">
      <select id="gs-filtro-cat" class="gs-input !py-2.5" aria-label="Filtrar por categoría"></select>
      <select id="gs-filtro-estado" class="gs-input !py-2.5" aria-label="Filtrar por estado de stock">
        <option value="all">Todos los estados</option>
        <option value="optimo">Óptimo</option>
        <option value="bajo">Bajo mínimo</option>
        <option value="agotado">Agotado</option>
      </select>
      <input id="gs-filtro-buscar" type="search" placeholder="Buscar por nombre o código..." class="gs-input !py-2.5" />
      <div class="flex items-center">
        <span id="gs-contador" class="text-xs text-stone-400"></span>
      </div>
    </div>
  </x-card>

  <x-card :padding="false">
    <x-data-table tbody="gs-tabla-productos" min-width="860px"
                  :columnas="['Código', 'Producto', 'Stock', 'Mínimo', 'Costo', 'Valor', 'Estado', ['Acciones', 'text-right']]" />
  </x-card>

  <x-slot:modales>
    <x-modal id="gs-modal-producto" form="gs-form-producto" title="Nuevo producto" title-id="gs-modal-titulo" ancho="2xl">
      <input type="hidden" id="gs-prod-id" value="" />
      <div class="grid md:grid-cols-2 gap-4">
        <x-field label="Código" for="gs-prod-codigo">
          <input id="gs-prod-codigo" required maxlength="30" class="gs-input" />
        </x-field>
        <x-field label="Nombre" for="gs-prod-nombre">
          <input id="gs-prod-nombre" required maxlength="120" class="gs-input" />
        </x-field>
        <x-field label="Categoría" for="gs-prod-categoria">
          <select id="gs-prod-categoria" required class="gs-input"></select>
        </x-field>
        <x-field label="Proveedor" for="gs-prod-proveedor">
          <select id="gs-prod-proveedor" required class="gs-input"></select>
        </x-field>
        <x-field label="Unidad de medida" for="gs-prod-unidad">
          <select id="gs-prod-unidad" required class="gs-input">
            @foreach (['kg' => 'kg · kilogramo', 'lb' => 'lb · libra', 'L' => 'L · litro', 'und' => 'und · unidad', 'caja' => 'caja'] as $valor => $texto)
              <option value="{{ $valor }}">{{ $texto }}</option>
            @endforeach
          </select>
        </x-field>
        <x-field label="Costo unitario ($)" for="gs-prod-costo">
          <input id="gs-prod-costo" type="number" step="0.01" min="0" required class="gs-input" />
        </x-field>
        <x-field label="Stock inicial" for="gs-prod-stock" ayuda="El stock solo cambia con movimientos de entrada/salida." ayuda-id="gs-prod-stock-ayuda">
          <input id="gs-prod-stock" type="number" step="0.01" min="0" required class="gs-input" />
        </x-field>
        <x-field label="Stock mínimo (alerta)" for="gs-prod-minimo">
          <input id="gs-prod-minimo" type="number" step="0.01" min="0" required class="gs-input" />
        </x-field>
        <x-field label="Stock ideal (opcional)" for="gs-prod-maximo" ayuda="Hasta dónde repone la lista de compras. Vacío = el doble del mínimo.">
          <input id="gs-prod-maximo" type="number" step="0.01" min="0" class="gs-input" />
        </x-field>
        <label class="flex items-start gap-2 text-sm text-stone-600 md:mt-7">
          <input id="gs-prod-preparado" type="checkbox" class="h-4 w-4 mt-0.5 accent-ocre" />
          <span>Se prepara en cocina <span class="block text-[11px] text-stone-400">Porciones u órdenes que salen de Producción: no aparecen en la lista de compras.</span></span>
        </label>
      </div>
    </x-modal>
  </x-slot:modales>

  @push('scripts')
    <script src="{{ asset('js/pages/inventario.js') }}"></script>
  @endpush
</x-layouts.app>
