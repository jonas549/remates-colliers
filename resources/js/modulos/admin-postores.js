import { clp } from './formato';

// Admin · Postores (Bloques G y H): filtro por estado, búsqueda, aprobar/rechazar cuenta o garantía con motivo, bloqueo
// y ficha. Cada acción va al servidor y la tabla se reemplaza con la respuesta (la base es la fuente de verdad).
export default ({ postores }) => ({
    postores,
    filtro: 'Todos',
    q: '',
    ficha: null,
    motivo: null,
    enviando: false,
    aviso: '',
    avisoError: false,

    coincide(p, filtro) {
        if (filtro === 'Todos') return true;
        if (filtro === 'Cuentas por aprobar') return ['en_revision', 'registrado'].includes(p.cuentaEstado);
        return p.garantia === filtro;
    },

    cuenta(filtro) {
        return this.postores.filter((p) => this.coincide(p, filtro)).length;
    },

    get filtrados() {
        const texto = this.q.trim().toLowerCase();
        return this.postores.filter((p) =>
            this.coincide(p, this.filtro) &&
            (!texto || (p.nombre + ' ' + p.rut + ' ' + p.remate + ' ' + p.folio + ' ' + p.correo).toLowerCase().includes(texto)));
    },

    get conteo() {
        return 'Mostrando ' + this.filtrados.length + ' de ' + this.postores.length + ' registros';
    },

    get totalGarantias() {
        return clp(this.postores.filter((p) => p.garantia === 'Aprobada').reduce((a, p) => a + p.monto, 0));
    },

    monto(p) {
        return p.monto ? clp(p.monto) : '—';
    },

    tono(estado) {
        return {
            'Aprobada': 'aprobada', 'Aprobado': 'aprobada', 'En revisión': 'revision',
            'Pendiente': 'pendiente', 'Sin confirmar correo': 'pendiente', 'Rechazada': 'rechazada', 'Rechazado': 'rechazada', 'Bloqueado': 'rechazada',
        }[estado] || 'cerrado';
    },

    regla(p) {
        return p.garantia === 'Aprobada' ? 'regla-aprobada' : (p.garantia === 'Rechazada' || p.cuentaEstado === 'rechazado') ? 'regla-rechazada'
            : (p.garantia === 'En revisión' || p.cuentaEstado === 'en_revision') ? 'regla-revision' : '';
    },

    // Mientras la cuenta no esté aprobada, las acciones son sobre la cuenta; después, sobre la garantía.
    sobreCuenta(p) { return p.cuentaEstado !== 'aprobado'; },
    puedeAprobar(p) {
        if (this.sobreCuenta(p)) return ['en_revision', 'rechazado'].includes(p.cuentaEstado);
        return !!p.garantiaId && p.garantia !== 'Aprobada';
    },
    puedeRechazar(p) {
        if (this.sobreCuenta(p)) return ['en_revision', 'registrado'].includes(p.cuentaEstado);
        return !!p.garantiaId && p.garantia !== 'Rechazada';
    },
    textoAprobar(p, largo) {
        if (this.sobreCuenta(p)) return largo ? 'Aprobar cuenta' : 'Aprobar cuenta';
        return largo ? 'Aprobar garantía' : 'Aprobar';
    },

    aprobar(p) {
        this.accion(this.sobreCuenta(p) ? p.urls.aprobarCuenta : p.urls.aprobarGarantia, {});
    },

    pedirMotivo(p, tipo) {
        this.motivo = { p, tipo, texto: '' };
    },

    confirmarMotivo() {
        const { p, tipo, texto } = this.motivo;
        this.accion(p.urls[tipo], { motivo: texto }).then((ok) => { if (ok) this.motivo = null; });
    },

    async accion(url, cuerpo) {
        if (!url || this.enviando) return false;
        this.enviando = true;
        try {
            const r = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                body: JSON.stringify(cuerpo),
            });
            // Solo es éxito una respuesta JSON: un redirect seguido hasta una página HTML no lo es.
            const esJson = (r.headers.get('content-type') || '').includes('application/json');
            const datos = esJson ? await r.json().catch(() => ({})) : {};
            const ok = r.ok && esJson && !r.redirected;
            this.avisoError = !ok;
            this.aviso = datos.mensaje || datos.message || (ok ? 'Listo.' : 'No se pudo completar la acción: recarga la página e inténtalo de nuevo.');
            if (ok && Array.isArray(datos.postores)) this.postores = datos.postores;
            return ok;
        } catch (e) {
            this.avisoError = true;
            this.aviso = 'Sin conexión. Revisa el estado antes de reintentar.';
            return false;
        } finally {
            this.enviando = false;
        }
    },

    get seleccionado() {
        return this.ficha ? this.postores.find((p) => p.id === this.ficha) : null;
    },

    datosFicha(p) {
        return [
            ['RUT', p.rut],
            ...(p.representante ? [['Representante', p.representante + ' · RUT ' + p.rutPersona]] : []),
            ['Tipo de postor', p.tipo],
            ['Correo', p.correo],
            ['Teléfono', p.telefono],
            ['Domicilio', p.domicilio || '—'],
            ['Registro', p.registro],
            ['Remate inscrito', p.remate],
            ['Folio', p.folio],
            ['Garantía', p.monto ? clp(p.monto) + ' · ' + p.medio : 'Sin garantía constituida'],
            ['Estado de la cuenta', p.cuenta + (p.motivoCuenta ? ' · ' + p.motivoCuenta : '')],
            ['Estado de la garantía', p.garantia + (p.motivoGarantia ? ' · ' + p.motivoGarantia : '')],
        ];
    },
});
