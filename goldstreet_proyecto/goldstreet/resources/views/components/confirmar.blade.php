{{--
  <x-confirmar /> : diálogo de confirmación con el estilo de la app (reemplaza confirm() del navegador).
  Va una sola vez en <x-layouts.app>; se usa desde JS:
    if (!(await App.confirmar({ titulo: '¿Dar de baja?', mensaje: '...', boton: 'Dar de baja' }))) return;
--}}
<div id="gs-confirmar" class="hidden fixed inset-0 z-[55] bg-negro/60 flex items-center justify-center p-4 print:hidden"
     role="alertdialog" aria-modal="true" aria-labelledby="gs-confirmar-titulo" aria-describedby="gs-confirmar-mensaje">
  <div class="bg-white rounded-2xl w-full max-w-sm shadow-2xl overflow-hidden">
    <div class="p-6 flex gap-4">
      <span class="shrink-0 h-10 w-10 rounded-full bg-red-50 text-red-700 grid place-items-center" aria-hidden="true">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z" /></svg>
      </span>
      <div>
        <h3 id="gs-confirmar-titulo" class="font-display text-lg font-bold text-negro"></h3>
        <p id="gs-confirmar-mensaje" class="text-sm text-stone-600 mt-1"></p>
      </div>
    </div>
    <div class="flex justify-end gap-2 px-6 py-4 bg-crema border-t border-plato">
      <button type="button" id="gs-confirmar-no" class="gs-btn-neutro">Cancelar</button>
      <button type="button" id="gs-confirmar-si" class="gs-btn-peligro">Confirmar</button>
    </div>
  </div>
</div>
