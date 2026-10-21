import { clp, dos } from './formato';
import { alias, locale, t } from './textos';

const TERMINALES = ['adjudicado', 'desierto', 'cerrado', 'incumplido'];
// Sin una respuesta del estado en este tiempo, la sala avisa que está desconectada y no deja pujar (QA 21/10).
// El JSON se consulta cada ~1 s: 5 s son cuatro sondeos perdidos seguidos.
const SIN_SENAL_MS = 5000;
const ESPERA_MAXIMA_MS = 5000;
// Una petición colgada (red que se cae sin error) no puede dejar la sala esperando para siempre.
const conLimite = (opciones = {}) => (typeof AbortSignal !== 'undefined' && AbortSignal.timeout ? { ...opciones, signal: AbortSignal.timeout(ESPERA_MAXIMA_MS) } : opciones);

// Sala de puja conectada al motor (Bloque K).
// - Toda hora sale del servidor: el reloj se sincroniza contra /hora compensando la latencia.
// - El estado llega por el JSON estático (~1 s, sin PHP). Si falla, se consulta el endpoint de estado.
// - Pasado cierra_en + margen sin liquidar, el navegador pide el estado a PHP (detector del cierre perezoso).
// - La base de datos es la fuente de verdad: tras cada puja se vuelve a leer el estado.
// - Varios lotes: la ficha sigue al lote vigente y un aviso cuenta cómo terminó el anterior (no hay diseño: mínimo).
// - Conexión: si el estado deja de llegar (SIN_SENAL_MS) o el navegador queda sin red, una banda lo avisa y Pujar se
//   bloquea hasta reconectar. Precio y cronómetro viejos no pueden pasar por actuales.
// - Remate terminado con varios lotes: el resultado de cada lote para este postor, no solo el del último.
// - Presencia: latido cada ~15 s a presencia.php (sin Laravel) con un token firmado; el número de conectados llega en el
//   mismo JSON del estado. El postor solo ve el número: los nombres nunca llegan a su navegador.
export default ({ remate, loteInicial, lotes = {}, miAlias, pujasRapidas, estado, servidorMs, urls, uf = null, presencia = null }) => ({
    estado,
    loteId: loteInicial,
    anterior: null,
    miAlias,
    monto: '',
    modal: false,
    hoja: false,
    enviando: false,
    error: '',
    desfase: servidorMs - Date.now(),
    ahora: servidorMs,
    fallos: 0,
    ultimaConsultaPhp: 0,
    ultimoOk: servidorMs,
    enLinea: typeof navigator === 'undefined' || navigator.onLine !== false,
    // El remate dejó de existir para pujar aunque el reloj diga otra cosa (21/09): 'cancelado' (lo dice el JSON) o
    // 'no-disponible' (se borró: el JSON y el endpoint responden 404). La sala nunca debe mostrar «PUJA ABIERTA» ahí.
    retirado: estado.estado === 'cancelado' ? 'cancelado' : null,

    init() {
        this.sincronizar();
        setInterval(() => this.sincronizar(), 60000);
        setInterval(() => this.tic(), 250);
        this.consultar();
        const reconectar = () => { this.sincronizar(); this.consultarPhp(true); this.latir(); };
        document.addEventListener('visibilitychange', () => { if (!document.hidden) reconectar(); });
        window.addEventListener('online', () => { this.enLinea = true; reconectar(); });
        window.addEventListener('offline', () => { this.enLinea = false; });
        if (presencia) {
            this.latir();
            setInterval(() => this.latir(), presencia.latidoMs);
            // Al cerrar la pestaña o salir de la sala, baja del contador sin esperar a que venza el latido.
            window.addEventListener('pagehide', () => {
                navigator.sendBeacon?.(urls.presencia, new URLSearchParams({ token: presencia.token, accion: 'salir' }));
            });
        }
    },

    // ── Presencia ─────────────────────────────────────────────────────────────────────────────────────────
    async latir() {
        if (!presencia || this.retirado) return;
        try {
            const r = await fetch(urls.presencia, { method: 'POST', cache: 'no-store', body: new URLSearchParams({ token: presencia.token, accion: 'latido' }) });
            if (!r.ok) return;
            const { conectados } = await r.json();
            this.estado.presencia = { conectados };
        } catch (e) { /* el próximo latido lo reintenta */ }
    },
    get conectadosTexto() {
        const n = this.estado.presencia?.conectados;
        return n ? t(n === 1 ? 'sala.conectado' : 'sala.conectados', { n }) : '';
    },

    // ── Reloj ─────────────────────────────────────────────────────────────────────────────────────────────
    async sincronizar() {
        let mejor = null;
        for (let i = 0; i < 3; i++) {
            try {
                const t0 = Date.now();
                const r = await fetch(urls.hora + '?t=' + t0, { cache: 'no-store', headers: { Accept: 'application/json' } });
                const t1 = Date.now();
                const { servidor_ms } = await r.json();
                const muestra = { rtt: t1 - t0, desfase: servidor_ms - (t0 + t1) / 2 };
                if (!mejor || muestra.rtt < mejor.rtt) mejor = muestra;
            } catch (e) { /* se reintenta en la próxima sincronización */ }
        }
        if (mejor) this.desfase = mejor.desfase;
    },

    tic() {
        this.ahora = Date.now() + this.desfase;
        if (this.retirado) return;
        const lote = this.lote;
        if (!lote || TERMINALES.includes(lote.estado) || lote.cierra_en_ms === null) return;
        const limite = lote.cierra_en_ms + this.estado.margen_liquidacion_ms + 300 + Math.random() * 1200;
        if (this.ahora >= limite) this.consultarPhp(false);
    },

    // ── Estado ────────────────────────────────────────────────────────────────────────────────────────────
    async consultar() {
        if (this.retirado === 'no-disponible') return; // ya no hay nada que consultar
        let espera = 1000;
        try {
            const r = await fetch(urls.estadoJson + '?t=' + Date.now(), conLimite({ cache: 'no-store' }));
            // Sin JSON: o se borró el remate o algo falló. El endpoint lo confirma de inmediato (404 = no existe).
            if (r.status === 404) { await this.consultarPhp(true); throw new Error('HTTP 404'); }
            if (!r.ok) throw new Error('HTTP ' + r.status);
            this.aplicar(await r.json());
            this.recibido();
            this.fallos = 0;
        } catch (e) {
            this.fallos++;
            espera = Math.min(5000, 1000 * this.fallos);
            if (this.fallos >= 3) this.consultarPhp(false);
        }
        setTimeout(() => this.consultar(), espera);
    },

    // Endpoint con PHP: reconexión, respaldo del JSON y detector de cierre. Como máximo cada 5 s (salvo forzar).
    async consultarPhp(forzar) {
        const ahora = Date.now();
        if (!forzar && ahora - this.ultimaConsultaPhp < 5000) return;
        this.ultimaConsultaPhp = ahora;
        try {
            const r = await fetch(urls.estado, conLimite({ cache: 'no-store', headers: { Accept: 'application/json' } }));
            if (r.status === 404) this.retirado = 'no-disponible';
            if (r.ok) { this.aplicar(await r.json()); this.recibido(); }
        } catch (e) { /* el sondeo del JSON sigue */ }
    },

    recibido() {
        this.enLinea = true;
        this.ultimoOk = Date.now() + this.desfase;
    },
    // Sin señal: lo que muestra la sala puede estar desactualizado. No aplica a un remate retirado (ya no hay nada que esperar).
    get sinConexion() { return !this.retirado && (!this.enLinea || this.ahora - this.ultimoOk > SIN_SENAL_MS); },

    aplicar(nuevo) {
        if (!nuevo || !Array.isArray(nuevo.lotes)) return;
        if (nuevo.generado_en_ms < this.estado.generado_en_ms) return; // nunca retroceder
        this.estado = nuevo;
        if (nuevo.estado === 'cancelado') this.retirado = 'cancelado';
        const vigente = nuevo.lotes.find((l) => !TERMINALES.includes(l.estado)) || nuevo.lotes[nuevo.lotes.length - 1];
        const actual = nuevo.lotes.find((l) => l.id === this.loteId);
        // Se queda en el lote actual hasta que termina; entonces pasa al siguiente sin liquidar.
        if (!actual || (TERMINALES.includes(actual.estado) && vigente && vigente.id !== actual.id && !TERMINALES.includes(vigente.estado))) {
            // Cómo terminó el lote anterior PARA ESTE POSTOR (21/09): el ganador tiene que seguir viendo que lo ganó.
            if (actual) this.anterior = { orden: actual.orden, estado: actual.estado, precio: actual.precio_actual, siguiente: vigente.orden,
                gane: !!this.miAlias && actual.ganador === this.miAlias };
            this.loteId = vigente.id;
        }
    },

    get lote() {
        return this.estado.lotes.find((l) => l.id === this.loteId) || this.estado.lotes[0];
    },
    get info() { return lotes[this.lote.id] || {}; },
    get etiquetaLote() {
        const total = this.estado.lotes.length;
        return total > 1 ? t('sala.lote_de', { orden: this.lote.orden, total }) : '';
    },
    // Cambio de lote (21/09, Jonas: «que sea evidente»): resultado del lote anterior para este postor y qué se remata ahora.
    get avisoLote() {
        const a = this.anterior;
        if (!a || a.siguiente !== this.lote.orden) return '';
        const resultado = a.estado !== 'adjudicado' ? t('sala.lote_sin_posturas', { orden: a.orden })
            : t(a.gane ? 'sala.lote_ganado' : 'sala.lote_de_otro', { orden: a.orden, precio: clp(a.precio) });
        const datos = { orden: this.lote.orden, direccion: this.info.direccion ? ': ' + this.info.direccion : '' };
        const ahora = this.antesDeAbrir ? t('sala.ahora_abre', { ...datos, hora: this.horaDe(this.lote.abre_en_ms) }) : t('sala.ahora_en_vivo', datos);
        return resultado + ' ' + ahora;
    },
    get avisoLoteGanado() { return !!this.anterior?.gane && this.anterior.siguiente === this.lote.orden; },
    get avisoLoteEtiqueta() { return t('sala.cambio_de_lote', { lote: this.etiquetaLote.toUpperCase() }); },
    horaDe(ms) { return new Date(ms).toLocaleTimeString(locale(), { timeZone: 'America/Santiago', hour: '2-digit', minute: '2-digit', hourCycle: 'h23' }) + t('formato.zona', {}, ''); },
    // Estado con el lote delante cuando hay varios: «LOTE 2 DE 2 · EN VIVO · PUJA ABIERTA».
    get estadoConLote() {
        if (this.resumenLotes.length) return t('sala.estado.finalizado');
        return (this.etiquetaLote && !this.retirado ? this.etiquetaLote.toUpperCase() + ' · ' : '') + this.estadoTexto;
    },
    // Remate de varios lotes ya terminado (QA 21/10): el ganador del lote 1 leía «se cerró sin posturas» porque la sala
    // solo mostraba el último lote. Aquí va cómo terminó cada uno para este postor, con los textos del cambio de lote.
    get resumenLotes() {
        const lotes = this.estado.lotes;
        if (this.retirado || lotes.length < 2 || !lotes.every((l) => TERMINALES.includes(l.estado))) return [];
        return lotes.map((l) => {
            const gane = !!this.miAlias && l.ganador === this.miAlias;
            const adjudicado = l.estado !== 'desierto';
            return {
                orden: l.orden, gane, adjudicado,
                texto: !adjudicado ? t('sala.lote_sin_posturas', { orden: l.orden })
                    : t(gane ? 'sala.lote_ganado' : 'sala.lote_de_otro', { orden: l.orden, precio: clp(l.precio_actual) }),
            };
        });
    },
    get resultadoDesierto() { return this.resumenLotes.length ? !this.resumenLotes.some((r) => r.adjudicado) : !this.hayPujas; },
    get mensajeMartillero() { return this.estado.mensaje_martillero?.texto || ''; },
    get mensajeHace() {
        const en = this.estado.mensaje_martillero?.en_ms;
        if (!en) return '';
        const seg = Math.max(0, Math.floor((this.ahora - en) / 1000));
        return seg < 60 ? t('sala.hace_seg', { n: seg }) : t('sala.hace_min', { n: Math.floor(seg / 60) });
    },
    get terminal() { return TERMINALES.includes(this.lote.estado); },
    get antesDeAbrir() { return this.lote.abre_en_ms !== null && this.ahora < this.lote.abre_en_ms; },
    get abierto() { return !this.retirado && !this.terminal && !this.antesDeAbrir && this.lote.cierra_en_ms !== null && this.ahora < this.lote.cierra_en_ms; },

    // ── Lo que muestra la vista (mismos nombres que la sala del prototipo) ─────────────────────────────────
    get restante() {
        const objetivo = this.antesDeAbrir ? this.lote.abre_en_ms : this.lote.cierra_en_ms;
        return objetivo === null ? 0 : Math.max(0, Math.floor((objetivo - this.ahora) / 1000));
    },
    get vencido() { return !!this.retirado || this.terminal || (!this.antesDeAbrir && this.restante === 0); },
    get hayPujas() { return this.lote.total_pujas > 0; },
    get totalPujas() { return this.lote.total_pujas; },
    get yoGanando() { return !!this.miAlias && this.lote.ganador === this.miAlias; },
    get actual() { return this.lote.precio_actual ?? this.lote.precio_base; },
    get minimo() { return this.lote.puja_minima; },
    get paso() { return this.estado.incremento_minimo; },
    get escrito() { return parseInt(String(this.monto).replace(/[^\d]/g, ''), 10) || 0; },
    get montoPuja() { return this.escrito > 0 ? this.escrito : this.minimo; },
    get montoValido() { return this.abierto && !this.sinConexion && !this.yoGanando && this.montoPuja >= this.minimo; },
    get montoInvalidoVisible() { return !!this.error || (this.escrito > 0 && this.montoPuja < this.minimo); },

    get hh() { return dos(Math.floor(this.restante / 3600)); },
    get mm() { return dos(Math.floor((this.restante % 3600) / 60)); },
    get ss() { return dos(this.restante % 60); },
    get enMinutoFinal() { return this.abierto && this.restante < 60; },
    get etiquetaContador() { return t(this.antesDeAbrir ? 'sala.abre_en_etiqueta' : 'sala.cierra_en_etiqueta'); },

    get estadoTexto() {
        if (this.retirado === 'cancelado') return t('sala.estado.cancelado');
        if (this.retirado) return t('sala.estado.no_disponible');
        if (this.lote.estado === 'adjudicado') return t('sala.estado.adjudicado');
        if (this.lote.estado === 'desierto') return t('sala.estado.desierto');
        if (this.antesDeAbrir) return t('sala.estado.proximo');
        if (this.vencido) return t('sala.estado.adjudicando');
        return t('sala.estado.en_vivo');
    },
    get tiempoTexto() {
        if (this.retirado) return t(this.retirado === 'cancelado' ? 'sala.tiempo.cancelado' : 'sala.tiempo.no_disponible');
        if (this.terminal) return t('sala.tiempo.finalizado');
        const tiempo = this.hh + ':' + this.mm + ':' + this.ss;
        if (this.antesDeAbrir) return t('sala.tiempo.abre_en', { tiempo });
        return this.vencido ? t('sala.tiempo.cerrado') : t('sala.tiempo.cierra_en', { tiempo });
    },

    formatoClp(n) { return clp(n); },
    get actualTexto() { return clp(this.actual); },
    // UF: solo referencia visual y solo si hay un valor vigente (Administración → Configuración).
    enUf(n) { return uf ? 'UF ' + (n / uf).toLocaleString(locale(), { maximumFractionDigits: 0 }) : ''; },
    get actualEnUf() { return this.enUf(this.actual) || '—'; },
    get sobreBase() { return '+' + Math.round((this.actual / this.lote.precio_base - 1) * 100) + '%'; },

    get estadoTitulo() {
        if (!this.hayPujas) return t('sala.sin_posturas');
        return t(this.yoGanando ? 'sala.vas_ganando' : 'sala.te_superaron');
    },
    get estadoDetalle() {
        if (!this.hayPujas) return t('sala.detalle_sin_posturas', { monto: clp(this.minimo) });
        return this.yoGanando
            ? t('sala.detalle_ganando')
            : t('sala.detalle_superado', { monto: clp(this.minimo) });
    },

    get rapidas() {
        return pujasRapidas.map((inc) => ({
            label: inc >= 1000000 ? '+ $' + inc / 1000000 + 'M' : '+ $' + inc / 1000 + 'k',
            total: clp(this.actual + inc),
            valor: this.actual + inc,
        }));
    },

    escribir(valor) {
        this.error = '';
        this.monto = String(valor).replace(/[^\d]/g, '');
    },

    get ayuda() {
        if (this.error) return this.error;
        if (this.sinConexion) return t('sala.ayuda_sin_conexion');
        if (this.antesDeAbrir) return t('sala.ayuda_abre', { tiempo: this.hh + ':' + this.mm + ':' + this.ss });
        if (this.yoGanando) return t('sala.ayuda_ganando');
        if (this.escrito > 0 && this.montoPuja < this.minimo) return t('sala.ayuda_minimo', { monto: clp(this.minimo) });
        return uf ? t('sala.ayuda_incremento_uf', { monto: clp(this.paso), uf: this.enUf(this.montoPuja) }) : t('sala.ayuda_incremento', { monto: clp(this.paso) });
    },
    get placeholder() { return t('sala.placeholder', { monto: clp(this.minimo) }); },
    get botonTexto() { return this.enviando ? t('sala.enviando') : t('sala.pujar', { monto: clp(this.montoPuja) }); },

    abrirModal() {
        this.error = '';
        if (this.montoValido && !this.enviando) this.modal = true;
    },

    // Registra la puja en el motor. El modal de confirmación es obligatorio (acta).
    async confirmar() {
        if (this.enviando) return;
        if (this.sinConexion) { this.modal = false; this.error = t('sala.ayuda_sin_conexion'); return; }
        this.enviando = true;
        const monto = this.montoPuja;
        try {
            const r = await fetch(urls.pujar.replace('__LOTE__', this.lote.id), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: JSON.stringify({ monto }),
            });
            if (r.status === 401) { window.location.href = urls.ingresar; return; }
            const cuerpo = await r.json().catch(() => ({}));
            if (r.status === 201) {
                this.monto = '';
                if (cuerpo.mi_alias) this.miAlias = cuerpo.mi_alias;
            } else if (r.status === 422) {
                this.error = cuerpo.mensaje || t('sala.error.rechazada');
            } else if (r.status === 419) {
                this.error = t('sala.error.sesion');
            } else if (r.status === 403) {
                this.error = cuerpo.mensaje || cuerpo.message || t('sala.error.acceso');
            } else if (r.status === 429) {
                this.error = t('sala.error.seguido');
            } else {
                this.error = t('sala.error.no_confirmada');
            }
            if (cuerpo.lote) this.mezclarResumen(cuerpo.lote);
        } catch (e) {
            this.error = t('sala.error.sin_conexion');
        } finally {
            this.enviando = false;
            this.modal = false;
            if (!this.error) this.hoja = false;
            this.consultarPhp(true);
        }
    },

    // Resumen del lote que devuelve el motor: se muestra de inmediato, sin esperar el próximo sondeo.
    mezclarResumen(resumen) {
        this.estado = {
            ...this.estado,
            lotes: this.estado.lotes.map((l) => (l.id === resumen.id
                ? { ...l, precio_actual: resumen.precio_actual, puja_minima: resumen.puja_minima, total_pujas: resumen.total_pujas, ganador: resumen.ganador }
                : l)),
        };
    },

    get modalMonto() { return clp(this.montoPuja); },
    get modalUf() { return this.enUf(this.montoPuja); },
    get modalDiferencia() { return clp(this.montoPuja - this.actual); },

    get historialVista() {
        return (this.lote.pujas || []).map((p) => {
            const seg = Math.max(0, Math.floor((this.ahora - p.en_ms) / 1000));
            return {
                monto: clp(p.monto),
                postor: p.postor === this.miAlias ? t('sala.postor_tu', { postor: alias(p.postor) }) : alias(p.postor),
                yo: p.postor === this.miAlias,
                hora: new Date(p.en_ms).toLocaleTimeString(locale(), { timeZone: 'America/Santiago', hourCycle: 'h23' }),
                hace: seg < 60 ? t('sala.hace_seg', { n: seg }) : seg < 3600 ? t('sala.hace_min', { n: Math.floor(seg / 60) }) : t('sala.hace_h', { n: Math.floor(seg / 3600) }),
            };
        });
    },

    get resultadoTitulo() {
        if (this.retirado === 'cancelado') return t('sala.resultado.cancelado_titulo');
        if (this.retirado) return t('sala.resultado.no_disponible_titulo');
        if (this.resumenLotes.length) {
            return t(this.resumenLotes.some((r) => r.gane) ? 'sala.resultado.ganado_titulo' : 'sala.resultado.finalizado_titulo');
        }
        if (!this.terminal) return t('sala.resultado.cerrado_titulo');
        if (!this.hayPujas) return t('sala.resultado.desierto_titulo');
        return t(this.yoGanando ? 'sala.resultado.ganado_titulo' : 'sala.resultado.otro_titulo');
    },
    get resultadoTexto() {
        if (this.retirado === 'cancelado') return t('sala.resultado.cancelado_texto');
        if (this.retirado) return t('sala.resultado.no_disponible_texto');
        if (this.resumenLotes.length) return ''; // lo cuenta la lista de lotes
        if (!this.terminal) return t('sala.resultado.cerrado_texto');
        if (!this.hayPujas) return t('sala.resultado.desierto_texto');
        // Neutro a propósito: qué pasa con la garantía (devolución o imputación) está pendiente con el cliente.
        return this.yoGanando
            ? t('sala.resultado.ganado_texto', { precio: clp(this.actual) })
            : t('sala.resultado.otro_texto', { precio: clp(this.actual) });
    },
});
