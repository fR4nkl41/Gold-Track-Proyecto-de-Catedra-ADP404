{{-- Producción / porcionado (fase 2): materia prima → porciones + desperdicio, como en el cuaderno de cocina --}}
<x-layouts.app title="Producción" page="produccion">

  <x-page-header title="Producción" subtitle="Porcionado y cocción: cuánta materia prima se usó, qué salió y cuánto se desperdició.">
    <button id="gs-btn-nueva-prod" data-roles="admin,cocina" class="gs-btn-primario">+ Registrar producción</button>
  </x-page-header>

  <x-card class="mb-6" :padding="false">
    <form id="gs-prod-filtros" class="p-4 grid sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
      <x-field label="Materia prima" for="gs-prod-f-origen" class="lg:col-span-2">
        <select id="gs-prod-f-origen" class="gs-input"><option value="">Todas</option></select>
      </x-field>
      <x-field label="Desde" for="gs-prod-f-desde">
        <input id="gs-prod-f-desde" type="date" class="gs-input" />
      </x-field>
      <x-field label="Hasta" for="gs-prod-f-hasta">
        <input id="gs-prod-f-hasta" type="date" class="gs-input" />
      </x-field>
      <div class="flex gap-2">
        <button type="submit" class="gs-btn-dorado flex-1">Filtrar</button>
        <button type="button" id="gs-prod-f-limpiar" class="gs-btn-neutro">Limpiar</button>
      </div>
    </form>
  </x-card>

  <x-card :padding="false">
    <x-data-table tbody="gs-tabla-producciones" min-width="860px"
                  :columnas="['Fecha', 'Materia prima', 'Usado', 'Desperdicio', 'Aprovechamiento', 'Salió', 'Registró']" />
  </x-card>

  <x-slot:modales>
    <x-modal id="gs-modal-produccion" form="gs-form-produccion" title="Registrar producción" boton="Registrar producción" ancho="2xl">
      <x-field label="Materia prima" for="gs-prod-origen" ayuda="Lo que se partió o se cocinó (ej. puyazo, lomo rollizo, frijoles).">
        <select id="gs-prod-origen" required class="gs-input"></select>
      </x-field>
      <div class="grid grid-cols-2 gap-4">
        <x-field label="Cantidad usada" for="gs-prod-cantidad">
          <div class="flex items-center gap-2">
            <input id="gs-prod-cantidad" type="number" step="0.01" min="0.01" required class="gs-input" />
            <span data-unidad-origen class="text-sm text-stone-500 w-8"></span>
          </div>
        </x-field>
        <x-field label="Desperdicio" for="gs-prod-desperdicio" ayuda="Recortes, grasa, huesos. Se registra como merma.">
          <div class="flex items-center gap-2">
            <input id="gs-prod-desperdicio" type="number" step="0.01" min="0" value="0" class="gs-input" />
            <span data-unidad-origen class="text-sm text-stone-500 w-8"></span>
          </div>
        </x-field>
      </div>

      <div id="gs-prod-aprov" class="rounded-lg bg-crema border border-plato px-4 py-3 text-sm text-stone-600">
        Escribe la cantidad usada para ver el aprovechamiento.
      </div>

      <div>
        <p class="gs-label">¿Qué salió?</p>
        <div id="gs-prod-resultados" class="space-y-2"></div>
        <button type="button" id="gs-prod-agregar" class="gs-btn-neutro !py-1.5 mt-2">+ Agregar otro resultado</button>
        <p class="text-[11px] text-stone-400 mt-1">Ej. 6 parrilladas de 8 oz y 3 puntas. Si falta un producto (porción), pídele al administrador que lo cree en Inventario.</p>
      </div>

      <x-field label="Notas (opcional)" for="gs-prod-notas">
        <input id="gs-prod-notas" maxlength="150" placeholder="Ej. cocción para chicharrones, pedido de Bocadeli…" class="gs-input" />
      </x-field>
    </x-modal>
  </x-slot:modales>

  @push('scripts')
    <script src="{{ asset('js/pages/produccion.js') }}"></script>
  @endpush
</x-layouts.app>
