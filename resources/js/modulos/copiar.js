// Botón «Copiar» (postores de prueba, 21/09): copia un texto y lo confirma en el mismo botón durante 2 s.
// navigator.clipboard exige https o localhost; si no está disponible, se copia con una selección temporal.
export default (texto = '') => ({
    copiado: false,
    temporizador: null,

    async copiar() {
        try {
            await navigator.clipboard.writeText(texto);
        } catch (e) {
            const area = document.createElement('textarea');
            area.value = texto;
            area.setAttribute('readonly', '');
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            area.remove();
        }
        this.copiado = true;
        clearTimeout(this.temporizador);
        this.temporizador = setTimeout(() => { this.copiado = false; }, 2000);
    },
});
