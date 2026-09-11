{{--
  <x-modal id="gs-modal-producto" form="gs-form-producto" title="Nuevo producto" boton="Guardar" ancho="2xl">
    ...campos...
  </x-modal>
  title-id permite cambiar el título desde JS (ej. "Nuevo" / "Editar").
  Cualquier botón con data-cerrar-modal lo cierra (core.js); también Esc y clic fuera.
--}}
@props(['id', 'form', 'title', 'titleId' => null, 'boton' => 'Guardar', 'ancho' => 'md'])

@php
  $anchos = ['md' => 'max-w-md', 'lg' => 'max-w-lg', '2xl' => 'max-w-2xl', '4xl' => 'max-w-4xl'];
@endphp

<div id="{{ $id }}" class="gs-modal hidden fixed inset-0 z-50 bg-negro/60 flex items-center justify-center p-4 print:hidden"
     role="dialog" aria-modal="true" @if($titleId) aria-labelledby="{{ $titleId }}" @endif>
  <form id="{{ $form }}"
        class="bg-white rounded-2xl w-full {{ $anchos[$ancho] ?? 'max-w-md' }} max-h-[90vh] flex flex-col overflow-hidden shadow-2xl">
    <header class="bg-negro px-6 py-4 flex items-center justify-between gap-3">
      <h3 @if($titleId) id="{{ $titleId }}" @endif class="font-display text-xl font-bold text-dorado">{{ $title }}</h3>
      <button type="button" data-cerrar-modal class="p-1 rounded text-crema/60 hover:text-dorado" aria-label="Cerrar">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18" /></svg>
      </button>
    </header>

    <div class="p-6 space-y-4 overflow-y-auto">
      {{ $slot }}
    </div>

    <footer class="flex justify-end gap-2 px-6 py-4 bg-crema border-t border-plato">
      <button type="button" data-cerrar-modal class="gs-btn-neutro">Cancelar</button>
      <button type="submit" class="gs-btn-primario">{{ $boton }}</button>
    </footer>
  </form>
</div>
