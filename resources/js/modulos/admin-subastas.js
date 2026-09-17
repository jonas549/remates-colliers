import { clp } from './formato';

// Admin · Subastas: formulario de creación desplegable (envío real al servidor), pestañas de filtro con contador y
// modal de cierre anticipado o cancelación con motivo (formulario real).
export default ({ subastas, abierto = false, uf = 0, porcentajeGlobal = 10, incrementoGlobal = 100000, base = '', incremento = '', porcentaje = '' }) => ({
    subastas,
    form: abierto,
    filtro: 'Todas',
    cerrar: null,
    menuAbierto: null,
    base: String(base).replace(/[^\d]/g, ''),
    incremento: String(incremento).replace(/[^\d]/g, ''),
    porcentaje: String(porcentaje),

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
