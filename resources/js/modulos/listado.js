import { clp, cuentaRegresiva, partes, dos } from './formato';

// Listado de remates. Port de la lógica de index.dc.html: filtros por estado y ocupación,
// búsqueda, orden, grilla/tabla, "cargar más" y cuentas regresivas (favoritos: fuera de alcance),
// más los filtros adicionales del panel.
// Datos reales desde el Bloque N (App\Publico\Catalogo). Filtra en el navegador: el catálogo es chico (decenas de remates).
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
    // Filtros adicionales del panel (decisión del 15/09: fecha, tipo, dormitorios, estacionamiento/bodega,
    // rango de precio, región y comuna; garantía requerida queda oculta, ver la vista).
    extra: { fecha: [], tipo: [], dorm: [], estac: false, bodega: false, desde: '', hasta: '', region: [], comuna: [], garantia: [] },
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

    numero(v) {
        return parseInt(String(v).replace(/[^\d]/g, ''), 10) || 0;
    },

    // Un remate pasa los filtros adicionales si cumple todos los grupos activos (dentro de cada grupo, basta una opción).
    cumpleExtra(l) {
        const e = this.extra;
        if (e.fecha.length) {
            // Días hasta el remate. Los cerrados no tienen fecha futura y quedan fuera.
            if (l.delta == null) return false;
            const limite = Math.max(...e.fecha.map(Number));
            if (l.delta / 86400 > limite) return false;
        }
        if (e.tipo.length && !e.tipo.includes(l.tipo)) return false;
        if (e.dorm.length && l.dorm < Math.min(...e.dorm.map(Number))) return false;
        if (e.estac && !l.estac) return false;
        if (e.bodega && !l.bodega) return false;
        const desde = this.numero(e.desde);
        const hasta = this.numero(e.hasta);
        if (desde && l.precio < desde) return false;
        if (hasta && l.precio > hasta) return false;
        if (e.region.length && !e.region.includes(l.region)) return false;
        if (e.comuna.length && !e.comuna.includes(l.comuna)) return false;
        if (e.garantia.length && !e.garantia.some((i) => this.enTramo(l.garantia, this.tramosGarantia[i]))) return false;
        return true;
    },

    // Comunas disponibles: si hay regiones marcadas, solo las de esas regiones.
    comunaVisible(comuna, region) {
        return !this.extra.region.length || this.extra.region.includes(region);
    },

    // Tramos de garantía calculados desde los datos (terciles redondeados a 00.000), no fijos:
    // el porcentaje de garantía será configurable y puede variar por remate.
    get tramosGarantia() {
        const valores = this.remates.map((l) => l.garantia).filter(Boolean).sort((a, b) => a - b);
        if (valores.length < 3) return [];
        const redondear = (n) => Math.round(n / 500000) * 500000;
        const c1 = redondear(valores[Math.floor(valores.length / 3)]);
        const c2 = redondear(valores[Math.floor((valores.length * 2) / 3)]);
        if (c1 >= c2) return [];
        return [
            { etiqueta: 'Hasta ' + clp(c1), min: 0, max: c1 },
            { etiqueta: clp(c1 + 1) + ' a ' + clp(c2), min: c1 + 1, max: c2 },
            { etiqueta: 'Más de ' + clp(c2), min: c2 + 1, max: Infinity },
        ];
    },

    enTramo(valor, tramo) {
        return !!tramo && valor >= tramo.min && valor <= tramo.max;
    },

    marcarExtra(nombre) {
        this.visibles = 6;
        this.ultimoFiltro = nombre;
    },

    get filtrados() {
        const texto = this.q.trim().toLowerCase();
        const lista = this.remates.filter((l) =>
            (this.estado === 'Todos' || l.estado === this.estado) &&
            (this.ocupacion === 'Todas' || l.ocupacion === this.ocupacion) &&
            (!texto || (l.direccion + ' ' + l.comuna + ' ' + l.region + ' ' + l.tipo).toLowerCase().includes(texto)) &&
            this.cumpleExtra(l));
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
            foto: l.foto,
            direccion: l.direccion,
            ubicacion: l.comuna + ', ' + l.region,
            tipoSup: l.tipo + (l.sup ? ' · ' + String(l.sup).replace('.', ',') + ' m² útiles' : ''),
            dormBanos: l.dorm ? l.dorm + 'D / ' + l.banos + 'B' : l.tipo,
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
            nuevoHref: l.nuevoHref || '',
            bases: l.bases || '',
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

    // «Avísame de los próximos remates» (Bloque M).
    avisoMensaje: '',
    avisoEnviando: false,
    async suscribir(formulario) {
        this.avisoEnviando = true;
        try {
            const r = await fetch(rutas.avisame, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                body: JSON.stringify({ email: formulario.email.value }),
            });
            const datos = await r.json().catch(() => ({}));
            this.avisoMensaje = datos.mensaje || datos.message || (r.ok ? 'Listo.' : 'No pudimos registrar tu correo.');
        } catch (e) {
            this.avisoMensaje = 'Sin conexión: inténtalo de nuevo.';
        } finally {
            this.avisoEnviando = false;
        }
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
        this.extra = { fecha: [], tipo: [], dorm: [], estac: false, bodega: false, desde: '', hasta: '', region: [], comuna: [], garantia: [] };
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
        const nombres = { ocupacion: 'ocupación', fecha: 'fecha de remate', tipo: 'tipo de propiedad', caracteristicas: 'características', precio: 'rango de precio', region: 'región', comuna: 'comuna', garantia: 'garantía requerida' };
        return 'El filtro por ' + (nombres[this.ultimoFiltro] || 'estado del remate') + ' es el que más resultados descarta.';
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
