{{-- <x-field label="Nombre" for="gs-prod-nombre" ayuda="Texto de ayuda opcional"> <input ...> </x-field> --}}
@props(['label', 'for' => null, 'ayuda' => null, 'ayudaId' => null])

<div {{ $attributes }}>
  <label @if($for) for="{{ $for }}" @endif class="gs-label">{{ $label }}</label>
  {{ $slot }}
  @if ($ayuda)
    <p @if($ayudaId) id="{{ $ayudaId }}" @endif class="text-[11px] text-stone-400 mt-1">{{ $ayuda }}</p>
  @endif
</div>
