@props(['ancho' => null])
<div class="tramite-pie" @if ($ancho) style="--tramite-ancho: {{ $ancho }}px" @endif>
    <div class="tramite-pie__interior">
        <img src="{{ asset('img/colliers-logo.png') }}" alt="Colliers" class="tramite-pie__logo">
        <x-publico.enlace-externo :href="\App\Support\Sitio::enlaceLegal('bases')" class="tramite-pie__enlace">Bases y condiciones</x-publico.enlace-externo>
        <x-publico.enlace-externo :href="\App\Support\Sitio::enlaceLegal('privacidad')" class="tramite-pie__enlace">Política de privacidad</x-publico.enlace-externo>
        <div class="tramite-pie__credito">Desarrollado por <a href="https://deltadigital.cl" target="_blank" rel="noopener">DeltaDigital</a> · © 2026 Colliers</div>
    </div>
</div>
