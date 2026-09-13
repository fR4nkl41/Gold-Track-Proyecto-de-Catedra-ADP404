{{-- Facturas de compra (fase 2): bodega y admin registran; cada línea entra al inventario. Precios: solo admin --}}
<x-layouts.app title="Facturas" page="facturas" acceso="admin,bodega">

  <x-page-header title="Facturas de compra" subtitle="Lo que llega de los proveedores. Al registrar una factura, sus productos entran solos al inventario.">
    <button id="gs-btn-nueva-factura" class="gs-btn-primario">+ Registrar factura</button>
  </x-page-header>

  <x-card class="mb-6" :padding="false">
    <form id="gs-fac-filtros" class="p-4 grid sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
      <x-field label="Proveedor" for="gs-fac-f-proveedor" class="lg:col-span-2">
        <select id="gs-fac-f-proveedor" class="gs-input"><option value="">Todos los proveedores</option></select>
      </x-field>
      <x-field label="Número" for="gs-fac-f-buscar">
        <input id="gs-fac-f-buscar" maxlength="40" placeholder="Ej. 00981" class="gs-input" />
      </x-field>
      <x-field label="Desde" for="gs-fac-f-desde">
        <input id="gs-fac-f-desde" type="date" class="gs-input" />
      </x-field>
      <x-field label="Hasta" for="gs-fac-f-hasta">
        <input id="gs-fac-f-hasta" type="date" class="gs-input" />
      </x-field>
      <div class="flex gap-2">
        <button type="submit" class="gs-btn-dorado flex-1">Filtrar</button>
        <button type="button" id="gs-fac-f-limpiar" class="gs-btn-neutro">Limpiar</button>
      </div>
    </form>
  </x-card>

  {{-- Resumen de compras del filtro (solo admin: incluye montos) --}}
  <section data-roles="admin" class="grid grid-cols-2 gap-4 mb-6 max-w-xl">
    <x-stat-card id="gs-fac-stat-n" label="Facturas registradas" />
    <x-stat-card id="gs-fac-stat-monto" label="Monto comprado" tono="destacado" valor="$0.00" detalle="facturas del filtro, sin anuladas" />
  </section>

  <x-card :padding="false">
    <div class="overflow-x-auto">
      <table class="w-full text-sm tabular-nums min-w-[720px]">
        <thead class="bg-negro text-left text-[11px] uppercase tracking-wider text-crema/80">
          <tr>
            <th class="px-4 py-3 font-semibold">Fecha</th>
            <th class="px-4 py-3 font-semibold">Número</th>
            <th class="px-4 py-3 font-semibold">Proveedor</th>
            <th class="px-4 py-3 font-semibold">Productos</th>
            <th data-roles="admin" class="px-4 py-3 font-semibold text-right">Total</th>
            <th data-roles="admin" class="px-4 py-3 font-semibold">Pago</th>
            <th class="px-4 py-3 font-semibold">Registró</th>
            <th class="px-4 py-3 font-semibold">Estado</th>
            <th class="px-4 py-3 font-semibold text-right"></th>
          </tr>
        </thead>
        <tbody id="gs-tabla-facturas"></tbody>
      </table>
    </div>
  </x-card>

  <x-slot:modales>
    {{-- Registrar factura --}}
    <x-modal id="gs-modal-factura" form="gs-form-factura" title="Registrar factura" title-id="gs-fac-titulo" boton="Guardar y agregar al inventario" ancho="4xl">
      <div id="gs-fac-orden-aviso" class="hidden rounded-lg border border-dorado bg-doradoClaro/40 px-4 py-3 text-sm text-stone-700"></div>
      <div class="grid sm:grid-cols-3 gap-4">
        <x-field label="Proveedor" for="gs-fac-proveedor">
          <select id="gs-fac-proveedor" required class="gs-input"></select>
        </x-field>
        <x-field label="Número de factura" for="gs-fac-numero">
          <input id="gs-fac-numero" required maxlength="40" placeholder="Ej. 00981" class="gs-input" />
        </x-field>
        <x-field label="Fecha de la factura" for="gs-fac-fecha">
          <input id="gs-fac-fecha" type="date" required class="gs-input" />
        </x-field>
        <x-field label="Condición de pago" for="gs-fac-condicion">
          <select id="gs-fac-condicion" class="gs-input">
            <option value="contado">Contado (se pagó al recibir)</option>
            <option value="credito">Crédito (se paga después)</option>
          </select>
        </x-field>
        <x-field label="Vence el pago" for="gs-fac-vence" id="gs-fac-campo-vence" class="hidden" ayuda="Pasa a Cuentas por pagar con esta fecha.">
          <div class="flex gap-2">
            <input id="gs-fac-vence" type="date" class="gs-input" />
            <select id="gs-fac-dias" class="gs-input !w-28" aria-label="Plazo de crédito">
              <option value="">Plazo…</option><option value="8">8 días</option><option value="15">15 días</option><option value="30">30 días</option>
            </select>
          </div>
        </x-field>
      </div>

      <div class="border border-plato rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-sm tabular-nums min-w-[620px]">
            <thead class="bg-plato/60 text-left text-[11px] uppercase tracking-wider text-stone-500">
              <tr>
                <th class="px-3 py-2 font-semibold w-[42%]">Producto</th>
                <th class="px-3 py-2 font-semibold">Cantidad</th>
                <th class="px-3 py-2 font-semibold">Precio unitario</th>
                <th class="px-3 py-2 font-semibold text-right">Subtotal</th>
                <th class="px-3 py-2"></th>
              </tr>
            </thead>
            <tbody id="gs-fac-lineas"></tbody>
          </table>
        </div>
        <div class="flex items-center justify-between gap-3 px-3 py-3 bg-crema border-t border-plato">
          <button type="button" id="gs-fac-agregar" class="gs-btn-neutro !py-1.5">+ Agregar producto</button>
          <p class="text-sm">Total de la factura: <span id="gs-fac-total" class="font-display text-xl font-bold text-negro tabular-nums">$0.00</span></p>
        </div>
      </div>

      <p class="text-xs text-stone-500">
        Registra la cantidad en la unidad del inventario (por ejemplo, una caja de lomo de 59 lb se anota como 59 lb).
        Al guardar, cada producto entra como ENTRADA y su costo se actualiza con el precio de esta factura.
      </p>
    </x-modal>

    {{-- Anular factura (admin) --}}
    <x-modal id="gs-modal-anular" form="gs-form-anular" title="Anular factura" title-id="gs-anular-titulo" boton="Anular factura">
      <p class="text-sm text-stone-600">
        La factura no se borra: se registran salidas que revierten lo que ingresó, y queda marcada como anulada.
        Si parte de lo comprado ya se usó, el sistema no lo permitirá.
      </p>
      <x-field label="Motivo" for="gs-anular-motivo">
        <input id="gs-anular-motivo" required maxlength="150" placeholder="Ej. factura duplicada, productos devueltos…" class="gs-input" />
      </x-field>
    </x-modal>

    {{-- Detalle (solo lectura) --}}
    <div id="gs-modal-detalle" class="gs-modal hidden fixed inset-0 z-50 bg-negro/60 flex items-center justify-center p-4 print:hidden"
         role="dialog" aria-modal="true" aria-labelledby="gs-det-titulo">
      <div class="bg-white rounded-2xl w-full max-w-3xl max-h-[90vh] flex flex-col overflow-hidden shadow-2xl">
        <header class="bg-negro px-6 py-4 flex items-center justify-between gap-3">
          <div>
            <h3 id="gs-det-titulo" class="font-display text-xl font-bold text-dorado"></h3>
            <p id="gs-det-sub" class="text-xs text-crema/60"></p>
          </div>
          <button type="button" data-cerrar-modal class="p-1 rounded text-crema/60 hover:text-dorado" aria-label="Cerrar">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
          </button>
        </header>
        <div class="p-6 space-y-4 overflow-y-auto">
          <div id="gs-det-anulada" class="hidden text-sm bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3"></div>
          <div id="gs-det-pago" class="text-sm text-stone-600"></div>
          <div class="overflow-x-auto border border-plato rounded-xl">
            <table class="w-full text-sm tabular-nums">
              <thead id="gs-det-thead" class="bg-plato/60 text-left text-[11px] uppercase tracking-wider text-stone-500"></thead>
              <tbody id="gs-det-lineas"></tbody>
            </table>
          </div>
          <div id="gs-det-pagos" data-roles="admin"></div>
        </div>
        <footer class="flex justify-between gap-2 px-6 py-4 bg-crema border-t border-plato">
          <button type="button" id="gs-det-anular" data-roles="admin" class="gs-btn-merma">Anular factura</button>
          <button type="button" data-cerrar-modal class="gs-btn-neutro ml-auto">Cerrar</button>
        </footer>
      </div>
    </div>
  </x-slot:modales>

  @push('scripts')
    <script src="{{ asset('js/pages/facturas.js') }}"></script>
  @endpush
</x-layouts.app>
