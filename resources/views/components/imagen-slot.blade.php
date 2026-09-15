@props(['src', 'alt' => ''])
<span {{ $attributes->merge(['class' => 'imagen-slot']) }}>
    <span class="imagen-slot__marco">
        <img src="{{ $src }}" alt="{{ $alt }}" draggable="false" decoding="async">
    </span>
</span>
