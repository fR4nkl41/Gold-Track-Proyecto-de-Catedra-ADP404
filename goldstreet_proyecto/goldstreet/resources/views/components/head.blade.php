{{-- <x-head title="..."> : metadatos, Tailwind (CDN), fuentes e identidad visual (logo de Gold Street) --}}
@props(['title' => null])

<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="theme-color" content="#1C1713" />
<title>{{ $title ? "$title · GoldTrack" : 'GoldTrack · Gold Street' }}</title>
<link rel="icon" type="image/png" href="{{ asset('img/favicon.png') }}" />
<link rel="apple-touch-icon" href="{{ asset('img/apple-touch-icon.png') }}" />

<script src="https://cdn.tailwindcss.com/3.4.17"></script>
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@400..900&display=swap" rel="stylesheet" />

<script>
// Paleta del logo: ocre y dorado principales, negro secundario, crema del plato como fondo
tailwind.config = {
  theme: { extend: {
    colors: {
      ocre: '#C2621A', ocreOscuro: '#9E4C10',
      dorado: '#E3A21A', doradoClaro: '#F6E3B0',
      negro: '#1C1713', negroSuave: '#2A231D',
      crema: '#FBF6EE', plato: '#F4EADB',
    },
    fontFamily: {
      sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
      display: ['"Playfair Display"', 'Georgia', 'serif'],
    },
  } },
};
</script>

{{-- Clases reutilizables: así los formularios no repiten 10 utilidades en cada campo --}}
<style type="text/tailwindcss">
  @layer components {
    .gs-input { @apply w-full border border-stone-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-dorado focus:border-dorado disabled:bg-stone-100 disabled:text-stone-400; }
    .gs-label { @apply block text-sm font-medium text-stone-600 mb-1; }
    .gs-btn { @apply inline-flex items-center justify-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-dorado focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed; }
    .gs-btn-primario { @apply gs-btn bg-ocre text-white hover:bg-ocreOscuro; }
    .gs-btn-secundario { @apply gs-btn border border-ocre text-ocre bg-white hover:bg-ocre hover:text-white; }
    .gs-btn-negro { @apply gs-btn bg-negro text-dorado hover:bg-negroSuave; }
    .gs-btn-dorado { @apply gs-btn bg-dorado text-negro hover:brightness-95; }
    .gs-btn-merma { @apply gs-btn border border-red-200 text-red-700 bg-white hover:bg-red-50; }
    .gs-btn-peligro { @apply gs-btn bg-red-700 text-white hover:bg-red-800; }
    .gs-btn-neutro { @apply gs-btn border border-stone-300 text-stone-700 font-medium bg-white hover:bg-stone-50; }
    .gs-btn-mini { @apply text-xs border border-stone-300 bg-white rounded-md px-2 py-1 hover:bg-plato; }
    .gs-btn-mini-peligro { @apply text-xs border border-red-200 bg-white text-red-700 rounded-md px-2 py-1 hover:bg-red-50; }
    .gs-pill { @apply inline-block text-[11px] font-semibold px-2 py-0.5 rounded-full whitespace-nowrap; }
    /* Fila "cargando": barras grises animadas mientras llega la API */
    .gs-skeleton { @apply block h-3 rounded bg-stone-200 animate-pulse; }
  }

  /* Elementos restringidos por rol: ocultos hasta que core.js confirme el permiso */
  [data-roles] { display: none !important; }

  /* Tablets de bodega y cocina (pantalla táctil): controles más grandes, fáciles de tocar */
  @media (pointer: coarse) {
    .gs-btn { min-height: 44px; @apply px-5 text-base; }
    .gs-input { min-height: 44px; @apply text-base; }
    .gs-btn-mini, .gs-btn-mini-peligro { min-height: 36px; @apply px-3 text-sm; }
  }

  @media (prefers-reduced-motion: reduce) {
    .gs-skeleton { animation: none; }
  }

  /* Impresión / PDF: hoja blanca, sin sombras, colores de tabla conservados */
  @media print {
    @page { margin: 14mm 12mm; }
    body { background: #fff !important; }
    .shadow, .shadow-sm, .shadow-xl, .shadow-2xl { box-shadow: none !important; }
    thead { display: table-header-group; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    tr { break-inside: avoid; }
  }
</style>
