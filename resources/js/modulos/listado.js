import { clp, cuentaRegresiva, partes, dos } from './formato';

// Listado de remates. Port de la lógica de index.dc.html: filtros por estado y ocupación,
// búsqueda, orden, grilla/tabla, "cargar más" y cuentas regresivas (favoritos: fuera de alcance).
// Bloque T: filtra en el navegador. En el Bloque N pasa a filtrar en el servidor con el estado en la URL.
export default ({ remates, sesion, rutas, columnas = 3 }) => ({
    remates,
    sesion,
    columnas,
    estado: 'Todos',
    ocupacion: 'Todas',
    q: '',
    orden: 'fecha',
    vista: 'Grilla',
    visibles: 6,
    filtros: null,
    ultimoFiltro: null,
    compacto: false,
    t0: Date.now(),
    ahora: Date.now(),

    init() {
        const consulta = window.matchMedia('(max-width: 1119.98px)');
        this.compacto = consulta.matches;
        consulta.addEventListener('change', (e) => { this.compacto = e.matches; });
        setInterval(() => { this.ahora = Date.now(); }, 1000);
        // La lupa de la cabecera lleva a /remates#buscar: enfoca el buscador.
        if (window.location.hash === '#buscar') this.$nextTick(() => document.getElementById('buscar')?.focus());
    },

    get filtrosAbiertos() {
        return this.filtros ?? !this.compacto;
    },

    alternarFiltros() {
        this.filtros = !this.filtrosAbiertos;
    },

    get filtrados() {
        const texto = this.q.trim().toLowerCase();
        const lista = this.remates.filter((l) =>
            (this.estado === 'Todos' || l.estado === this.estado) &&
            (this.ocupacion === 'Todas' || l.ocupacion === this.ocupacion) &&
            (!texto || (l.direccion + ' ' + l.comuna + ' ' + l.region + ' ' + l.tipo).toLowerCase().includes(texto)));
        const orden = this.orden;
        return lista.sort((a, b) => {
            if (orden === 'precio-asc') return a.precio - b.precio;
            if (orden === 'precio-desc') return b.precio - a.precio;
            if (orden === 'superficie') return b.sup - a.sup;
            return (a.delta ?? Infinity) - (b.delta ?? Infinity);
        });
    },

    get tarjetas() {
        return this.filtrados.slice(0, this.visibles).map((l) => this.tarjeta(l));
    },

    tarjeta(l) {
        const abierto = l.estado !== 'Cerrado';
        const restante = l.delta ? Math.max(0, this.t0 + l.delta * 1000 - this.ahora) : 0;
        let statusLabel = 'Resultado';
        let statusValor = l.resultado || '';
        let statusClase = '';
        if (l.estado === 'En vivo') {
            statusLabel = 'Puja actual · ' + l.pujas + ' pujas';
            statusValor = clp(l.puja) + ' · ' + cuentaRegresiva(restante);
            statusClase = 'tarjeta__estado-valor--vivo';
        } else if (l.estado === 'Próximo') {
            statusLabel = 'Comienza en';
            statusValor = cuentaRegresiva(restante);
            statusClase = 'tarjeta__estado-valor--proximo';
        }
        const extras = [l.estac ? l.estac + ' estac.' : '', l.bodega ? 'bodega' : ''].filter(Boolean).join(' · ');
        return {
            id: l.id,
            href: rutas.detalle.replace('__ID__', l.id),
            foto: rutas.fotos + '/prop-' + l.id + '.jpg',
            direccion: l.direccion,
            ubicacion: l.comuna + ', ' + l.region,
            tipoSup: l.tipo + ' · ' + l.sup + ' m² útiles',
            dormBanos: l.dorm + 'D / ' + l.banos + 'B',
            extras,
            ocupacion: l.ocupacion,
            ocupacionClase: l.ocupacion === 'Ocupada' ? 'tarjeta__chip--ocupada' : 'tarjeta__chip--desocupada',
            precio: clp(l.precio),
            garantia: clp(l.garantia),
            limite: l.limite,
            fecha: l.fecha,
            visita: l.visita,
            martillero: l.martillero,
            estado: l.estado.toUpperCase(),
            badgeClase: l.estado === 'En vivo' ? 'badge-remate--vivo' : l.estado === 'Próximo' ? 'badge-remate--proximo' : '',
            statusLabel,
            statusValor,
            statusClase,
            abierto,
            nuevoRemate: l.nuevoRemate || '',
            cta: l.estado === 'En vivo' ? 'Ver transmisión y pujar' : (this.sesion === 'aprobada' ? 'Ver remate' : 'Inscribirme para pujar'),
        };
    },

    opciones(claves, campo, todas) {
        return claves.map((k) => ({
            clave: k,
            count: k === todas ? this.remates.length : this.remates.filter((l) => l[campo] === k).length,
        }));
    },

    elegir(campo, valor, todas) {
        this[campo] = valor;
        this.visibles = 6;
        this.ultimoFiltro = valor === todas ? null : campo;
    },

    buscar(valor) {
        this.q = valor;
        this.visibles = 6;
        this.ultimoFiltro = 'texto';
    },

    limpiar() {
        this.estado = 'Todos';
        this.ocupacion = 'Todas';
        this.q = '';
        this.visibles = 6;
        this.ultimoFiltro = null;
    },

    get conteo() {
        return 'Mostrando ' + this.tarjetas.length + ' de ' + this.filtrados.length + ' remates';
    },

    get hayMas() {
        return this.filtrados.length > this.tarjetas.length;
    },

    get esGrilla() {
        return this.tarjetas.length > 0 && (this.compacto || this.vista === 'Grilla');
    },

    get esTabla() {
        return this.tarjetas.length > 0 && !this.compacto && this.vista === 'Tabla';
    },

    get sugerencia() {
        if (this.ultimoFiltro === 'texto') return 'La búsqueda “' + this.q + '” no coincide con ningún remate publicado.';
        return 'El filtro por ' + (this.ultimoFiltro === 'ocupacion' ? 'ocupación' : 'estado del remate') + ' es el que más resultados descarta.';
    },

    get sugerenciaAccion() {
        return this.ultimoFiltro === 'texto' ? 'Borrar la búsqueda' : 'Quitar ese filtro';
    },

    // Cuenta regresiva del hero: { d, h, m, s } con dos dígitos
    hero(deltaSegundos) {
        const p = partes(Math.max(0, this.t0 + deltaSegundos * 1000 - this.ahora));
        return { d: dos(p.d), h: dos(p.h), m: dos(p.m), s: dos(p.s) };
    },
});
