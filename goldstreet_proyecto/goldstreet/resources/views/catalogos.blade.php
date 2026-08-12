{{-- Catálogos (solo admin): categorías y proveedores --}}
<x-layouts.app title="Catálogos" page="catalogos" acceso="admin">

  <x-page-header title="Catálogos" subtitle="Categorías de productos y proveedores." />

  <section class="grid xl:grid-cols-2 gap-6">
    <x-card title="Categorías" :padding="false">
      <x-slot:acciones>
        <button id="gs-btn-nueva-cat" class="gs-btn-primario !py-1.5">+ Nueva</button>
      </x-slot:acciones>
      <x-data-table tbody="gs-tabla-categorias" variante="clara"
                    :columnas="['Nombre', 'Descripción', 'Productos', ['', 'text-right']]" />
    </x-card>

    <x-card title="Proveedores" :padding="false">
      <x-slot:acciones>
        <button id="gs-btn-nuevo-prov" class="gs-btn-primario !py-1.5">+ Nuevo</button>
      </x-slot:acciones>
      <x-data-table tbody="gs-tabla-proveedores" variante="clara" min-width="560px"
                    :columnas="['Nombre', 'Contacto', 'Teléfono', 'Productos', ['', 'text-right']]" />
    </x-card>
  </section>

  <x-slot:modales>
    <x-modal id="gs-modal-categoria" form="gs-form-categoria" title="Nueva categoría" title-id="gs-cat-titulo">
      <input type="hidden" id="gs-cat-id" value="" />
      <x-field label="Nombre" for="gs-cat-nombre">
        <input id="gs-cat-nombre" required maxlength="80" class="gs-input" />
      </x-field>
      <x-field label="Descripción (opcional)" for="gs-cat-descripcion">
        <input id="gs-cat-descripcion" maxlength="255" class="gs-input" />
      </x-field>
    </x-modal>

    <x-modal id="gs-modal-proveedor" form="gs-form-proveedor" title="Nuevo proveedor" title-id="gs-prov-titulo">
      <input type="hidden" id="gs-prov-id" value="" />
      <x-field label="Nombre" for="gs-prov-nombre">
        <input id="gs-prov-nombre" required maxlength="120" class="gs-input" />
      </x-field>
      <x-field label="Persona de contacto" for="gs-prov-contacto">
        <input id="gs-prov-contacto" maxlength="120" class="gs-input" />
      </x-field>
      <div class="grid grid-cols-2 gap-4">
        <x-field label="Teléfono" for="gs-prov-telefono">
          <input id="gs-prov-telefono" maxlength="30" class="gs-input" />
        </x-field>
        <x-field label="Correo" for="gs-prov-correo">
          <input id="gs-prov-correo" type="email" maxlength="120" class="gs-input" />
        </x-field>
      </div>
      <x-field label="Dirección" for="gs-prov-direccion">
        <input id="gs-prov-direccion" maxlength="150" class="gs-input" />
      </x-field>
    </x-modal>
  </x-slot:modales>

  @push('scripts')
    <script src="{{ asset('js/pages/catalogos.js') }}"></script>
  @endpush
</x-layouts.app>
