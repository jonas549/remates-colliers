import { clp } from './formato';

// Admin · Subastas: formulario de creación desplegable, pestañas de filtro con contador,
// cierre anticipado con motivo. Bloque T: los cambios son solo en pantalla (Bloque I conecta el CRUD).
export default ({ subastas }) => ({
    subastas,
    form: false,
    filtro: 'Todas',
    cerrar: null,
    cerradas: {},
    menuAbierto: null,
    base: '',
    incremento: '100000',
    garantia: '',

    get todas() {
        return this.subastas.map((s) => ({ ...s, estado: this.cerradas[s.id] || s.estado }));
    },

    coincide(s, filtro) {
        if (filtro === 'Todas') return true;
        if (filtro === 'Cerradas') return s.estado === 'Cerrada' || s.estado === 'Adjudicada';
        if (filtro === 'En vivo') return s.estado === 'En vivo';
        if (filtro === 'Próximas') return s.estado === 'Próxima';
        return s.estado === 'Borrador';
    },

    get filtradas() {
        return this.todas.filter((s) => this.coincide(s, this.filtro));
    },

    cuenta(filtro) {
        return this.todas.filter((s) => this.coincide(s, filtro)).length;
    },

    numero(v) {
        return parseInt(String(v).replace(/[^\d]/g, ''), 10) || 0;
    },

    get resumenMontos() {
        const base = this.numero(this.base);
        const inc = this.numero(this.incremento);
        const gar = this.numero(this.garantia);
        if (!base) return 'Todos los montos se publican y se cobran en pesos. La UF aparece solo como referencia informativa.';
        return 'Precio base ' + clp(base) + ' · referencia UF ' + (base / 39412.73).toLocaleString('es-CL', { maximumFractionDigits: 0 }) +
            ' · incremento ' + clp(inc || 100000) + (gar ? ' · garantía ' + clp(gar) : '');
    },

    soloDigitos(campo, valor) {
        this[campo] = String(valor).replace(/[^\d]/g, '');
    },

    get enMenu() {
        return this.menuAbierto ? this.todas.find((s) => s.id === this.menuAbierto) : null;
    },

    get enCierre() {
        return this.cerrar ? this.todas.find((s) => s.id === this.cerrar) : null;
    },

    confirmarCierre() {
        this.cerradas = { ...this.cerradas, [this.cerrar]: 'Cerrada' };
        this.cerrar = null;
    },
});
