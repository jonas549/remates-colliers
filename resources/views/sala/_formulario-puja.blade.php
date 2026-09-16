{{-- Puja rápida + monto libre + botón. Se usa en el panel lateral (escritorio) y en la hoja (< 1120px). --}}
<div>
    <div class="sala-puja__titulo">PUJA RÁPIDA</div>
    <div class="sala-puja__rapidas">
        <template x-for="r in rapidas" :key="r.label">
            <button type="button" class="sala-puja__rapida" @click="escribir(r.valor)">
                <div class="sala-puja__rapida-label" x-text="r.label"></div>
                <div class="sala-puja__rapida-total" x-text="r.total"></div>
            </button>
        </template>
    </div>

    <label class="sala-puja__libre-titulo" for="monto-{{ $id }}" style="display: block">MONTO LIBRE (CLP)</label>
    <input id="monto-{{ $id }}" :value="monto" @input="escribir($event.target.value); $event.target.value = monto" inputmode="numeric" :placeholder="placeholder" class="sala-puja__input" :class="{ 'es-invalido': montoInvalidoVisible }" autocomplete="off">
    <div class="sala-puja__ayuda" :class="{ 'es-invalido': montoInvalidoVisible }" x-text="ayuda"></div>

    <button type="button" class="sala-puja__boton" :disabled="!montoValido || enviando" @click="abrirModal()" x-text="botonTexto"></button>
    <div class="sala-puja__nota">Las posturas se cursan en pesos y son irrevocables. Se te pedirá confirmar antes de registrarlas.</div>
</div>
