{{--
  <x-layouts.base> : esqueleto HTML mínimo (head + body + scripts), sin navegación.
  Úsalo directo para pantallas sueltas (login) o a través de <x-layouts.app>.

  Props:
    title     → título de la pestaña
    page      → nombre de la página; core.js ejecuta App.init_{page}()
    acceso    → roles que pueden abrir la página, ej. "admin" (vacío = cualquiera con sesión)
    bodyClass → clases del <body>
  Pila: @push('scripts') para agregar el JS propio de cada página.
--}}
@props([
    'title' => null,
    'page' => null,
    'acceso' => null,
    'bodyClass' => 'bg-crema text-stone-800 font-sans antialiased min-h-screen',
])
<!DOCTYPE html>
<html lang="es">
<head>
  <x-head :title="$title" />
  @stack('head')
</head>
<body data-page="{{ $page }}" @if($acceso) data-acceso="{{ $acceso }}" @endif class="{{ $bodyClass }}">

  {{ $slot }}

  <script src="{{ asset('js/core.js') }}"></script>
  @stack('scripts')
</body>
</html>
