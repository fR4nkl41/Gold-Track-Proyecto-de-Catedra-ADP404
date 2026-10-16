{{-- Cuentas por pagar (fase 3, solo admin): facturas al crédito, abonos, vencidas y por vencer --}}
<x-layouts.app title="Cuentas por pagar" page="cuentas" acceso="admin">

  <x-page-header title="Cuentas por pagar" subtitle="Lo que se le debe a cada proveedor por facturas al crédito. Las de contado se pagan al recibirlas." />

  <section class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <x-stat-card id="gs-cxp-pendiente" label="Total por pagar" tono="destacado" valor="$0.00" />
    <x-stat-card id="gs-cxp-vencido" label="Vencido" tono="rojo" valor="$0.00" />
    <x-stat-card id="gs-cxp-semana" label="Vence en 7 días" tono="aviso" valor="$0.00" />
    <x-stat-card id="gs-cxp-pagado" label="Pagado este mes" tono="verde" valor="$0.00" />
  </section>

  <section class="grid lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-4">
      <x-card :padding="false">
        <form id="gs-cxp-filtros" class="p-4 grid sm:grid-cols-3 gap-3 items-end">
          <x-field label="Mostrar" for="gs-cxp-f-estado">
            <select id="gs-cxp-f-estado" class="gs-input">
              <option value="pendiente">Por pagar</option>
              <option value="vencida">Solo vencidas</option>
              <option value="pagada">Pagadas</option>
              <option value="todas">Todas al crédito</option>
            </select>
          </x-field>
          <x-field label="Proveedor" for="gs-cxp-f-proveedor">
            <select id="gs-cxp-f-proveedor" class="gs-input"><option value="">Todos</option></select>
          </x-field>
          <button type="submit" class="gs-btn-dorado">Filtrar</button>
        </form>
      </x-card>

      <x-card :padding="false">
        <x-data-table tbody="gs-tabla-cxp" min-width="760px"
                      :columnas="['Vence', 'Factura', 'Proveedor', ['Total', 'text-right'], ['Pagado', 'text-right'], ['Saldo', 'text-right'], 'Estado', ['', 'text-right']]" />
      </x-card>
    </div>

    <x-card title="Por proveedor" subtitle="saldo pendiente" :padding="false">
      <x-data-table tbody="gs-cxp-proveedores" variante="clara" :columnas="['Proveedor', ['Saldo', 'text-right']]" />
    </x-card>
  </section>

  <x-slot:modales>
    <x-modal id="gs-modal-pago" form="gs-form-pago" title="Registrar pago" title-id="gs-pago-titulo" boton="Registrar pago">
      <div id="gs-pago-info" class="rounded-lg bg-crema border border-plato px-4 py-3 text-sm"></div>
      <div class="grid grid-cols-2 gap-4">
        <x-field label="Monto ($)" for="gs-pago-monto">
          <input id="gs-pago-monto" type="number" step="0.01" min="0.01" required class="gs-input" />
        </x-field>
        <x-field label="Fecha del pago" for="gs-pago-fecha">
          <input id="gs-pago-fecha" type="date" required class="gs-input" />
        </x-field>
        <x-field label="Método" for="gs-pago-metodo">
          <select id="gs-pago-metodo" class="gs-input">
            <option value="transferencia">Transferencia</option>
            <option value="efectivo">Efectivo</option>
            <option value="cheque">Cheque</option>
            <option value="otro">Otro</option>
          </select>
        </x-field>
        <x-field label="Referencia (opcional)" for="gs-pago-referencia">
          <input id="gs-pago-referencia" maxlength="60" placeholder="N.º de transferencia o cheque" class="gs-input" />
        </x-field>
      </div>
    </x-modal>
  </x-slot:modales>

  @push('scripts')
    <script src="{{ asset('js/pages/cuentas.js') }}"></script>
  @endpush
</x-layouts.app>
