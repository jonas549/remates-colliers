@props(['ancho' => null])
<div class="tramite-pie" @if ($ancho) style="--tramite-ancho: {{ $ancho }}px" @endif>
    <div class="tramite-pie__interior">
        <img src="{{ asset('img/colliers-logo.png') }}" alt="Colliers" class="tramite-pie__logo">
        {{-- Las bases generales no tienen página en el sitio corporativo: sin enlace en el panel, no se muestran (22/10). --}}
        @if ($bases = \App\Support\Sitio::enlaceLegal('bases'))
            <x-publico.enlace-externo :href="$bases" class="tramite-pie__enlace">{{ __('sitio.pie.bases') }}</x-publico.enlace-externo>
        @endif
        <x-publico.enlace-externo :href="\App\Support\Sitio::enlaceLegal('privacidad')" class="tramite-pie__enlace">{{ __('sitio.pie.privacidad') }}</x-publico.enlace-externo>
        <div class="tramite-pie__credito">{{ __('sitio.pie.desarrollado_por') }} <a href="https://deltadigital.cl" target="_blank" rel="noopener">DeltaDigital</a> · © 2026 Colliers</div>
    </div>
</div>
