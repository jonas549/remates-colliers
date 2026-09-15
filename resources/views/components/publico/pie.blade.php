@props(['borde' => false, 'separado' => false])
<div @class(['pub-pie', 'pub-pie--borde' => $borde, 'pub-pie--separado' => $separado])>
    <div class="pub-pie__arriba contenedor">
        <img src="{{ asset('img/colliers-logo.png') }}" alt="Colliers" class="pub-pie__logo">
        <div class="pub-pie__links">
            <a href="#">Nosotros</a>
            <a href="#">Artículos</a>
            <a href="#">Carreras</a>
            <a href="#">Accionistas</a>
        </div>
        <div class="pub-pie__sociales">
            <a href="#" aria-label="LinkedIn">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor"><path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5ZM3 9h4v12H3V9Zm7 0h3.8v1.7h.05c.53-.95 1.83-1.95 3.76-1.95C21.6 8.75 22 11.1 22 14.2V21h-4v-6c0-1.43-.03-3.27-2-3.27-2 0-2.3 1.56-2.3 3.17V21h-4V9Z"></path></svg>
            </a>
            <a href="#" aria-label="X">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M17.53 3H21l-7.6 8.68L22.5 21h-6.9l-4.5-5.77L5.9 21H2.42l8.13-9.28L1.9 3h7.06l4.07 5.28L17.53 3Zm-1.22 16h1.92L7.79 4.9H5.73l10.58 14.1Z"></path></svg>
            </a>
            <a href="#" aria-label="Instagram">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3" y="3" width="18" height="18" rx="5"></rect><circle cx="12" cy="12" r="4"></circle><circle cx="17.2" cy="6.8" r="1" fill="currentColor" stroke="none"></circle></svg>
            </a>
            <a href="#" aria-label="YouTube">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor"><path d="M21.6 7.2a2.5 2.5 0 0 0-1.75-1.77C18.25 5 12 5 12 5s-6.25 0-7.85.43A2.5 2.5 0 0 0 2.4 7.2 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.8 2.5 2.5 0 0 0 1.75 1.77C5.75 19 12 19 12 19s6.25 0 7.85-.43a2.5 2.5 0 0 0 1.75-1.77A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.8ZM10 15V9l5.2 3-5.2 3Z"></path></svg>
            </a>
        </div>
    </div>
    <div class="pub-pie__separador">
        <div class="pub-pie__abajo contenedor">
            <a href="#">Política de información para el uso de Cookies</a>
            <a href="#">Política de privacidad</a>
            <a href="#">Términos de Uso</a>
            <a href="#">Declaración de accesibilidad</a>
            <div class="pub-pie__copy">
                <span>Desarrollado por <a href="https://deltadigital.cl" target="_blank" rel="noopener">DeltaDigital</a></span>
                <span>Copyright © 2026 Colliers</span>
            </div>
        </div>
    </div>
</div>
