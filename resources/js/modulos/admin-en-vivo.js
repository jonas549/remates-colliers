import { clp, dos } from './formato';

const TERMINALES = ['adjudicado', 'desierto', 'cerrado', 'incumplido'];

// Panel del martillero (Bloque I). Mismo transporte que la sala: JSON estático cada ~1 s, reloj sincronizado con
// hora.php y el endpoint de estado solo como respaldo o pasado el cierre. Muestra la identidad detrás de cada «Postor #N».
export default ({ estado, nombres, lotes, servidorMs, urls }) => ({
    estado,
    terminales: TERMINALES,
    desfase: servidorMs - Date.now(),
    ahora: servidorMs,
    loteId: (estado.lotes.find((l) => !TERMINALES.includes(l.estado)) || estado.lotes[estado.lotes.length - 1])?.id,
    modal: false,
    motivo: '',
    mensaje: estado.mensaje_martillero?.texto || '',
    enviando: false,
    aviso: '',
    avisoError: false,
    ultimaConsultaPhp: 0,

    init() {
        this.sincronizar();
        setInterval(() => this.sincronizar(), 60000);
        setInterval(() => { this.ahora = Date.now() + this.desfase; this.revisarCierre(); }, 250);
        this.consultar();
    },

    async sincronizar() {
        try {
            const t0 = Date.now();
            const r = await fetch(urls.hora + '?t=' + t0, { cache: 'no-store' });
            const t1 = Date.now();
            this.desfase = (await r.json()).servidor_ms - (t0 + t1) / 2;
        } catch (e) { /* se reintenta al minuto */ }
    },

    async consultar() {
        try {
            const r = await fetch(urls.estadoJson + '?t=' + Date.now(), { cache: 'no-store' });
            if (r.ok) this.aplicar(await r.json());
        } catch (e) { this.consultarPhp(); }
        setTimeout(() => this.consultar(), 1000);
    },

    async consultarPhp() {
        if (Date.now() - this.ultimaConsultaPhp < 5000) return;
        this.ultimaConsultaPhp = Date.now();
        try {
            const r = await fetch(urls.estado, { cache: 'no-store', headers: { Accept: 'application/json' } });
            if (r.ok) this.aplicar(await r.json());
        } catch (e) { /* sigue el sondeo */ }
    },

    revisarCierre() {
        const l = this.lote;
        if (l && !TERMINALES.includes(l.estado) && l.cierra_en_ms !== null && this.ahora > l.cierra_en_ms + this.estado.margen_liquidacion_ms + 1500) {
            this.consultarPhp();
        }
    },

    aplicar(nuevo) {
        if (!nuevo || !Array.isArray(nuevo.lotes) || nuevo.generado_en_ms < this.estado.generado_en_ms) return;
        this.estado = nuevo;
        const actual = nuevo.lotes.find((l) => l.id === this.loteId);
        const vigente = nuevo.lotes.find((l) => !TERMINALES.includes(l.estado));
        if (!actual || (TERMINALES.includes(actual.estado) && vigente)) this.loteId = (vigente || nuevo.lotes[nuevo.lotes.length - 1]).id;
    },

    get lote() { return this.estado.lotes.find((l) => l.id === this.loteId) || this.estado.lotes[0]; },
    get info() { return lotes[this.lote.id] || {}; },
    get etiquetaLote() { return this.estado.lotes.length > 1 ? 'Lote ' + this.lote.orden + ' de ' + this.estado.lotes.length : '1 lote'; },
    get antesDeAbrir() { return this.lote.abre_en_ms !== null && this.ahora < this.lote.abre_en_ms; },
    get abierto() { return !TERMINALES.includes(this.lote.estado) && !this.antesDeAbrir && this.lote.cierra_en_ms !== null && this.ahora < this.lote.cierra_en_ms; },
    get restante() {
        const objetivo = this.antesDeAbrir ? this.lote.abre_en_ms : this.lote.cierra_en_ms;
        return objetivo === null ? 0 : Math.max(0, Math.floor((objetivo - this.ahora) / 1000));
    },
    get hh() { return dos(Math.floor(this.restante / 3600)); },
    get mm() { return dos(Math.floor((this.restante % 3600) / 60)); },
    get ss() { return dos(this.restante % 60); },

    get estadoTexto() {
        if (this.lote.estado === 'adjudicado') return 'ADJUDICADO';
        if (this.lote.estado === 'desierto') return 'CERRADO SIN POSTORES';
        if (this.antesDeAbrir) return 'PRÓXIMO · PUJA AÚN NO ABRE';
        if (!this.abierto) return 'CERRADO · ADJUDICANDO';
        return 'EN VIVO · PUJA ABIERTA';
    },
    get precioTexto() { return clp(this.lote.precio_actual ?? this.lote.precio_base); },
    get ganadorTexto() {
        if (!this.lote.ganador) return 'Sin posturas todavía.';
        const verbo = this.lote.estado === 'adjudicado' ? 'Adjudicado a ' : 'Va ganando ';
        return verbo + this.quien(this.lote.ganador);
    },
    quien(alias) { return nombres[alias] ? nombres[alias] + ' (' + alias + ')' : alias; },
    formato(n) { return n === null || n === undefined ? '—' : clp(n); },

    get historial() {
        return (this.lote.pujas || []).map((p) => ({
            ...p,
            montoTexto: clp(p.monto),
            quien: this.quien(p.postor),
            hora: new Date(p.en_ms).toLocaleTimeString('es-CL', { timeZone: 'America/Santiago', hour12: false }),
        }));
    },

    async enviar(url, cuerpo) {
        this.enviando = true;
        this.aviso = '';
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
            if (!ok && !datos.mensaje) datos.mensaje = datos.message || 'No se pudo completar la acción: recarga la página e inténtalo de nuevo.';
            this.avisoError = !ok;
            return { ok, datos };
        } catch (e) {
            this.avisoError = true;
            return { ok: false, datos: { mensaje: 'Sin conexión. Revisa el estado antes de reintentar.' } };
        } finally {
            this.enviando = false;
        }
    },

    async cerrarLote() {
        const { ok, datos } = await this.enviar(urls.cerrar.replace('__LOTE__', this.lote.id), { motivo: this.motivo });
        this.aviso = datos.mensaje || (ok ? 'Lote cerrado.' : 'No se pudo cerrar el lote.');
        if (ok) { this.modal = false; this.motivo = ''; }
        this.consultarPhp();
    },

    async enviarMensaje() {
        const { ok, datos } = await this.enviar(urls.mensaje, { texto: this.mensaje });
        if (ok && datos.estado) this.aplicar(datos.estado);
        this.aviso = ok ? (this.mensaje ? 'Mensaje publicado en la sala.' : 'Mensaje quitado de la sala.') : (datos.mensaje || datos.message || 'No se pudo publicar el mensaje.');
    },
});
