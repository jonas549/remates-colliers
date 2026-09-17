{{-- Mensajes de resultado de una acción del panel (sesión flash «estado» y «error»). Sin diseño: tokens del panel. --}}
@if (session('estado') || session('error'))
    <div class="admin-avisos" role="status" aria-live="polite">
        @if (session('estado'))
            <div class="admin-aviso admin-aviso--ok">{{ session('estado') }}</div>
        @endif
        @if (session('error'))
            <div class="admin-aviso admin-aviso--error">{{ session('error') }}</div>
        @endif
    </div>
@endif
