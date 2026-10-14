{{-- Movimientos: kardex con filtros por tipo y fechas (filtrarMovimientos, 4.3) + registro (RF03 · RF04) y mermas --}}
<x-layouts.app title="Movimientos" page="movimientos">

  <x-page-header title="Movimientos" subtitle="Kardex de entradas, salidas y mermas (RF04). El stock lo actualiza el trigger trg_mov_stock.">
    <button id="gs-btn-merma" class="gs-btn-merma">Registrar merma</button>
    <button id="gs-btn-nuevo-mov" class="gs-btn-primario">Registrar movimiento</button>
  </x-page-header>

  <div class="mb-4 text-xs text-stone-500 bg-white border border-plato rounded-lg px-4 py-3">
    <span class="font-semibold text-ocre">Matriz de permisos:</span>
    Bodega registra entradas y salidas · Cocina registra salidas · Administrador todo ·
    Cualquier rol registra mermas (producto vencido, dañado o mal preparado).
    El backend también la aplica (HTTP 403 aunque se use Postman).
  </div>

  <x-card class="mb-6" :padding="false">
    <form id="gs-form-filtros" class="p-4 grid sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
      <x-field label="Tipo" for="gs-f-tipo">
        <select id="gs-f-tipo" class="gs-input">
          <option value="">Todos</option>
          <option value="entrada">Entradas</option>
          <option value="salida">Salidas (consumo)</option>
          <option value="merma">Mermas</option>
          <option value="compra">Compras (facturas)</option>
          <option value="produccion">Producción</option>
        </select>
      </x-field>
      <x-field label="Producto" for="gs-f-producto" class="lg:col-span-2">
        <select id="gs-f-producto" class="gs-input"><option value="">Todos los productos</option></select>
      </x-field>
      <x-field label="Desde" for="gs-f-desde">
        <input id="gs-f-desde" type="date" class="gs-input" />
      </x-field>
      <x-field label="Hasta" for="gs-f-hasta">
        <input id="gs-f-hasta" type="date" class="gs-input" />
      </x-field>
      <div class="flex gap-2">
        <button type="submit" class="gs-btn-dorado flex-1">Filtrar</button>
        <button type="button" id="gs-f-limpiar" class="gs-btn-neutro">Limpiar</button>
      </div>
    </form>
    <p id="gs-mov-contador" class="px-4 pb-3 text-xs text-stone-400"></p>
  </x-card>

  <x-card :padding="false">
    <x-data-table tbody="gs-tabla-movimientos" min-width="760px"
                  :columnas="['Fecha', 'Tipo', 'Producto', 'Cantidad', 'Usuario', 'Referencia']" />
  </x-card>

  <x-slot:modales>
    <x-modal id="gs-modal-movimiento" form="gs-form-movimiento" title="Registrar movimiento" title-id="gs-mov-titulo" boton="Registrar">
      <p class="text-xs text-stone-500">
        La cantidad se aplica en base de datos mediante el trigger trg_mov_stock (sección 4.2 del documento).
      </p>
      <x-field label="Producto" for="gs-mov-producto">
        <select id="gs-mov-producto" required class="gs-input"></select>
      </x-field>
      <x-field label="Tipo" for="gs-mov-tipo">
        <select id="gs-mov-tipo" required class="gs-input"></select>
      </x-field>
      <x-field label="Causa de la merma" for="gs-mov-causa" id="gs-mov-campo-causa" class="hidden"
               ayuda="Empaque dañado: bolsa rota, lata golpeada, caja mojada. Desperdicio: recortes y sobras de la preparación. La merma descuenta stock igual que una salida, pero se reporta como pérdida.">
        <select id="gs-mov-causa" class="gs-input">
          @foreach (\App\Models\MovimientoInventario::CAUSAS as $clave => $etiqueta)
            <option value="{{ $clave }}">{{ $etiqueta }}</option>
          @endforeach
        </select>
      </x-field>
      <x-field label="Cantidad" for="gs-mov-cantidad">
        <input id="gs-mov-cantidad" type="number" step="0.01" min="0.01" required class="gs-input" />
      </x-field>
      <x-field label="Referencia (factura, orden o motivo)" for="gs-mov-referencia">
        <input id="gs-mov-referencia" maxlength="120" placeholder="Factura #123 / consumo de cocina / detalle de la merma..." class="gs-input" />
      </x-field>
    </x-modal>
  </x-slot:modales>

  @push('scripts')
    <script src="{{ asset('js/pages/movimientos.js') }}"></script>
  @endpush
</x-layouts.app>
