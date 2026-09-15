import { clp } from './formato';

// Admin · Postores: filtro por estado de garantía, búsqueda, aprobar/rechazar y ficha.
// Bloque T: los cambios son solo en pantalla (Bloques G y H conectan la aprobación real con motivo y correo).
export default ({ postores }) => ({
    postores,
    filtro: 'Todos',
    q: '',
    cambios: {},
    ficha: null,

    get todos() {
        return this.postores.map((p) => ({ ...p, garantia: this.cambios[p.id] || p.garantia }));
    },

    cuenta(estado) {
        return estado === 'Todos' ? this.todos.length : this.todos.filter((p) => p.garantia === estado).length;
    },

    get filtrados() {
        const texto = this.q.trim().toLowerCase();
        return this.todos.filter((p) =>
            (this.filtro === 'Todos' || p.garantia === this.filtro) &&
            (!texto || (p.nombre + ' ' + p.rut + ' ' + p.remate + ' ' + p.folio).toLowerCase().includes(texto)));
    },

    get conteo() {
        return 'Mostrando ' + this.filtrados.length + ' de ' + this.todos.length + ' postores';
    },

    get totalGarantias() {
        return clp(this.todos.filter((p) => p.garantia === 'Aprobada').reduce((a, p) => a + p.monto, 0));
    },

    monto(p) {
        return p.monto ? clp(p.monto) : '—';
    },

    tono(estado) {
        return {
            'Aprobada': 'aprobada', 'Aprobado': 'aprobada', 'En revisión': 'revision',
            'Pendiente': 'pendiente', 'Rechazada': 'rechazada',
        }[estado] || 'cerrado';
    },

    regla(p) {
        return p.garantia === 'Aprobada' ? 'regla-aprobada' : p.garantia === 'Rechazada' ? 'regla-rechazada' : p.garantia === 'En revisión' ? 'regla-revision' : '';
    },

    marcar(id, estado) {
        this.cambios = { ...this.cambios, [id]: estado };
        this.ficha = null;
    },

    get seleccionado() {
        return this.ficha ? this.todos.find((p) => p.id === this.ficha) : null;
    },

    datosFicha(p) {
        return [
            ['RUT', p.rut],
            ['Tipo de postor', p.tipo],
            ['Correo', p.correo],
            ['Teléfono', p.telefono],
            ['Remate inscrito', p.remate],
            ['Folio', p.folio],
            ['Garantía', p.monto ? clp(p.monto) + ' · ' + p.medio : 'Sin garantía constituida'],
            ['Estado de la cuenta', p.cuenta],
            ['Estado de la garantía', p.garantia],
        ];
    },
});
