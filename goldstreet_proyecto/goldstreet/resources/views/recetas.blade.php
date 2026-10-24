{{-- Recetas y costo por plato (fase 3): cocina ve la ficha técnica; el admin ve costo, margen y food cost --}}
<x-layouts.app title="Recetas" page="recetas">

  <x-page-header title="Recetas y costos" subtitle="Qué lleva cada plato del menú y cuánto cuesta prepararlo con los precios actuales.">
    <button id="gs-btn-nuevo-plato" data-roles="admin" class="gs-btn-primario">+ Nuevo plato</button>
  </x-page-header>

  <section data-roles="admin" class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
    <x-stat-card id="gs-rec-stat-platos" label="Platos en el menú" />
    <x-stat-card id="gs-rec-stat-fc" label="Food cost promedio" tono="destacado" valor="0 %" detalle="% del precio que se va en ingredientes" />
    <x-stat-card id="gs-rec-stat-alto" label="Platos sobre 40 %" tono="rojo" detalle="conviene revisar precio o receta" />
  </section>

  <x-card class="mb-6" :padding="false">
    <div class="p-4 grid sm:grid-cols-3 gap-3 items-end">
      <x-field label="Buscar" for="gs-rec-buscar" class="sm:col-span-2">
        <input id="gs-rec-buscar" type="search" placeholder="Nombre del plato o ingrediente…" class="gs-input" />
      </x-field>
      <x-field label="Categoría" for="gs-rec-categoria">
        <select id="gs-rec-categoria" class="gs-input"><option value="">Todas</option></select>
      </x-field>
    </div>
  </x-card>

  <section id="gs-rec-lista" class="grid lg:grid-cols-2 gap-6"></section>

  <x-slot:modales>
    <x-modal id="gs-modal-plato" form="gs-form-plato" title="Nuevo plato" title-id="gs-plato-titulo" boton="Guardar plato" ancho="2xl">
      <input type="hidden" id="gs-plato-id" value="" />
      <div class="grid sm:grid-cols-3 gap-4">
        <x-field label="Nombre del plato" for="gs-plato-nombre" class="sm:col-span-2">
          <input id="gs-plato-nombre" required maxlength="120" class="gs-input" />
        </x-field>
        <x-field label="Precio de venta ($)" for="gs-plato-precio">
          <input id="gs-plato-precio" type="number" step="0.01" min="0.01" required class="gs-input" />
        </x-field>
        <x-field label="Categoría" for="gs-plato-categoria">
          <input id="gs-plato-categoria" maxlength="60" list="gs-plato-categorias" placeholder="Platos fuertes" class="gs-input" />
          <datalist id="gs-plato-categorias"></datalist>
        </x-field>
        <x-field label="Descripción (opcional)" for="gs-plato-descripcion" class="sm:col-span-2">
          <input id="gs-plato-descripcion" maxlength="255" class="gs-input" />
        </x-field>
      </div>

      <div>
        <p class="gs-label">Ingredientes por plato</p>
        <div class="border border-plato rounded-xl overflow-hidden">
          <table class="w-full text-sm tabular-nums">
            <thead class="bg-plato/60 text-left text-[11px] uppercase tracking-wider text-stone-500">
              <tr><th class="px-3 py-2 font-semibold">Producto</th><th class="px-3 py-2 font-semibold">Cantidad</th><th class="px-3 py-2 font-semibold text-right">Costo</th><th class="px-3 py-2"></th></tr>
            </thead>
            <tbody id="gs-plato-ingredientes"></tbody>
          </table>
          <div class="px-3 py-2 bg-crema border-t border-plato">
            <button type="button" id="gs-plato-agregar" class="gs-btn-neutro !py-1.5">+ Agregar ingrediente</button>
          </div>
        </div>
        <p class="text-[11px] text-stone-400 mt-1">La cantidad va en la unidad del producto: 0.25 kg de pechuga, 0.03 L de aceite, 1 und de orden de puyazo.</p>
      </div>

      <div id="gs-plato-resumen" class="rounded-lg bg-crema border border-plato px-4 py-3 text-sm"></div>

      <label class="flex items-center gap-2 text-sm text-stone-600">
        <input id="gs-plato-activo" type="checkbox" class="h-4 w-4 accent-ocre" checked /> Disponible en el menú
      </label>
    </x-modal>
  </x-slot:modales>

  @push('scripts')
    <script src="{{ asset('js/pages/recetas.js') }}"></script>
  @endpush
</x-layouts.app>
