import { clp } from './formato';

// Admin · Subastas: formulario de creación desplegable (envío real al servidor), pestañas de filtro con contador y
// modal de cierre anticipado o cancelación con motivo (formulario real).
export default ({ subastas, abierto = false, uf = 0, porcentajeGlobal = 10, incrementoGlobal = 100000, base = '', incremento = '', porcentaje = '', inicio = '', cierreGarantias = '', duracion = 30, horasGarantia = 48, lotes = 1 }) => ({
    subastas,
    form: abierto,
    filtro: 'Todas',
    cerrar: null,
    menuAbierto: null,
    base: String(base).replace(/[^\d]/g, ''),
    incremento: String(incremento).replace(/[^\d]/g, ''),
    porcentaje: String(porcentaje),
    inicio,
    cierreGarantias,
    duracion: String(duracion),

    coincide(s, filtro) {
        if (filtro === 'Todas') return true;
        if (filtro === 'Cerradas') return ['Cerrada', 'Adjudicada', 'Cancelada'].includes(s.estado);
        if (filtro === 'En vivo') return s.estado === 'En vivo';
        if (filtro === 'Próximas') return s.estado === 'Próxima';
        return s.estado === 'Borrador';
    },

    get filtradas() {
        return this.subastas.filter((s) => this.coincide(s, this.filtro));
    },

    cuenta(filtro) {
        return this.subastas.filter((s) => this.coincide(s, filtro)).length;
    },

    numero(v) {
        return parseInt(String(v).replace(/[^\d]/g, ''), 10) || 0;
    },

    /** Fecha larga en español, tal como la lee alguien que no conoce el sistema. */
    fecha(valor) {
        const d = new Date(valor);
        if (Number.isNaN(d.getTime())) return null;
        const texto = d.toLocaleDateString('es-CL', { weekday: 'long', day: 'numeric', month: 'long' });
        return texto + ' a las ' + d.toLocaleTimeString('es-CL', { hour: '2-digit', minute: '2-digit', hour12: false });
    },

    /** Las tres fechas del formulario, explicadas en una frase (QA del 20/09, punto 4). */
    get resumenCalendario() {
        const abre = this.fecha(this.inicio);
        if (!abre) return 'Indica el inicio del remate y aquí aparece, en palabras, lo que va a pasar.';

        const cierre = this.fecha(this.cierreGarantias);
        const porDefecto = new Date(new Date(this.inicio).getTime() - horasGarantia * 3600000);
        const plazo = cierre || this.fecha(porDefecto.toISOString());
        const minutos = parseInt(this.duracion, 10) || 0;
        const antes = !cierre ? ` (${horasGarantia} h antes, por defecto)` : '';

        let frase = `Los postores pueden inscribirse y constituir la garantía hasta el ${plazo}${antes}. `
            + `El remate abre el ${abre} y cada lote dura ${minutos} minutos, sin extensiones.`;
        if (cierre && new Date(cierre === null ? 0 : this.cierreGarantias) >= new Date(this.inicio)) {
            frase += ' ⚠ El cierre de garantías quedó después del inicio: nadie alcanzaría a habilitarse.';
        }
        return frase;
    },

    // La garantía es un porcentaje del precio base (acta): se muestra el monto que resulta.
    get resumenMontos() {
        const base = this.numero(this.base);
        if (!base) return 'Todos los montos se publican y se cobran en pesos. La UF aparece solo como referencia informativa.';
        const inc = this.numero(this.incremento) || incrementoGlobal;
        const pct = parseFloat(String(this.porcentaje).replace(',', '.')) || porcentajeGlobal;
        const garantia = Math.ceil((base * pct) / 100);
        return 'Precio base ' + clp(base) + (uf ? ' · referencia UF ' + (base / uf).toLocaleString('es-CL', { maximumFractionDigits: 0 }) : '') +
            ' · incremento ' + clp(inc) + ' · garantía ' + clp(garantia) + ' (' + pct.toLocaleString('es-CL') + ' %)';
    },

    soloDigitos(campo, valor) {
        this[campo] = String(valor).replace(/[^\d]/g, '');
    },

    get enMenu() {
        return this.menuAbierto ? this.subastas.find((s) => s.id === this.menuAbierto) : null;
    },

    get enCierre() {
        return this.cerrar ? this.subastas.find((s) => s.id === this.cerrar) : null;
    },
});
