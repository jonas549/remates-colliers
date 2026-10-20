import L from 'leaflet';
import { clp, dos } from './formato';
import { alias, locale, t } from './textos';

const TERMINALES = ['adjudicado', 'desierto', 'cerrado', 'incumplido'];

function reloj(segundos) {
    const s = Math.max(0, segundos);
    if (s < 60) return t('remate.seg', { n: s });
    if (s < 3600) return t('remate.min', { n: Math.floor(s / 60) });
    return t('remate.h', { n: Math.floor(s / 3600) });
}

// Detalle de remate (Bloque N): cuenta regresiva al inicio o al cierre con la hora del servidor, historial de pujas en vivo
// y mapa. Los espectadores SOLO leen el JSON estático (cada ~2 s) y sincronizan el reloj con hora.php: el detalle nunca
// ejecuta el framework, así la cantidad de espectadores no compite con las pujas (docs/RENDIMIENTO-SIN-OPCACHE.md).
export default ({ enVivo, proximo = false, loteId, objetivoMs, servidorMs, estadoJson, hora, pujas, precioActual, totalPujas, uf, mapa, rutas, remate,
    margenMs = 2000, avisoCierre = { espera_ms: 5000, porcentaje: 100 }, estadoUrl = null }) => ({
    pujas,
    precio: precioActual,
    total: totalPujas,
    objetivo: objetivoMs,
    desfase: servidorMs - Date.now(),
    ahora: servidorMs,
    compartido: '',
    avisoAbierto: false,
    avisoMensaje: '',
    avisoEnviando: false,
    // Estado del lote según el JSON; mientras no sea terminal, el cierre se deduce del reloj del servidor.
    estadoLote: null,
    margen: margenMs,
    // Este espectador avisa del cierre (según el porcentaje del panel) y cuándo (espera al azar).
    avisara: Math.random() * 100 < (avisoCierre.porcentaje ?? 100),
    esperaAviso: Math.floor(Math.random() * ((avisoCierre.espera_ms ?? 5000) + 1)),
    avisoCierreHecho: false,
    // El remate se canceló o se retiró (borrado: el JSON responde 404) con la página abierta (21/09).
    retirado: false,
    ausencias: 0,

    init() {
        setInterval(() => { this.ahora = Date.now() + this.desfase; }, 1000);
        this.sincronizar();
        if (enVivo) this.consultar();
        // Remate próximo con la página abierta: al llegar la hora de inicio se recarga sola y aparece en vivo (21/09:
        // el espectador que esperaba en la página veía la cuenta llegar a cero y nada más). Cada uno espera un tiempo al
        // azar de hasta 15 s para que no recarguen todos en el mismo segundo: cada recarga es una petición al servidor.
        if (proximo && objetivoMs > 0) {
            const espera = 1000 + Math.floor(Math.random() * 15000);
            const revisar = setInterval(() => {
                if (this.ahora >= objetivoMs + espera) { clearInterval(revisar); window.location.reload(); }
            }, 1000);
        }
        this.$nextTick(() => this.iniciarMapa());
    },

    async sincronizar() {
        try {
            const t0 = Date.now();
            const r = await fetch(hora + '?t=' + t0, { cache: 'no-store' });
            const t1 = Date.now();
            this.desfase = (await r.json()).servidor_ms - (t0 + t1) / 2;
        } catch (e) { /* se mantiene la hora de la carga */ }
    },

    async consultar() {
        if (this.retirado) return;
        try {
            const r = await fetch(estadoJson + '?t=' + Date.now(), { cache: 'no-store' });
            // Dos 404 seguidos: el remate ya no existe (un 404 suelto puede ser un reemplazo del archivo en curso).
            this.ausencias = r.status === 404 ? this.ausencias + 1 : 0;
            if (this.ausencias >= 2) { this.retirado = true; return; }
            if (r.ok) {
                const estado = await r.json();
                if (estado.estado === 'cancelado') { this.retirado = true; return; }
                const lote = estado.lotes.find((l) => l.id === loteId) || estado.lotes.find((l) => !TERMINALES.includes(l.estado));
                if (lote) {
                    this.pujas = lote.pujas || [];
                    this.precio = lote.precio_actual;
                    this.total = lote.total_pujas;
                    this.objetivo = lote.cierra_en_ms;
                    this.estadoLote = lote.estado;
                }
                if (estado.margen_liquidacion_ms) this.margen = estado.margen_liquidacion_ms;
                // El panel puede cambiar el aviso del cierre con la página abierta: el JSON lo trae al día.
                if (estado.aviso_cierre) {
                    this.avisara = this.avisara && (estado.aviso_cierre.porcentaje ?? 100) > 0;
                    this.esperaAviso = Math.min(this.esperaAviso, estado.aviso_cierre.espera_ms ?? this.esperaAviso);
                }
            }
        } catch (e) { /* reintenta en el próximo ciclo */ }
        this.avisarDelCierre();
        setTimeout(() => this.consultar(), 2000);
    },

    // El lote cerró por reloj (hora del servidor), aunque el JSON todavía no traiga el resultado.
    get cerrado() {
        return this.retirado || (enVivo && this.objetivo > 0 && this.ahora >= this.objetivo);
    },

    get adjudicando() {
        return !this.retirado && this.cerrado && !TERMINALES.includes(this.estadoLote);
    },

    get resultado() {
        return TERMINALES.includes(this.estadoLote) ? this.estadoLote : null;
    },

    get textoCierre() {
        if (this.retirado) return t('remate.no_disponible');
        if (this.resultado === 'adjudicado') return t('remate.adjudicado');
        if (this.resultado) return t('remate.cerrado');
        return t('remate.cerrado_adjudicando');
    },

    /**
     * Pasado el cierre y el margen, UN aviso al servidor para que materialice la adjudicación y reescriba el JSON, sin
     * esperar al cron del minuto. Con espera al azar y solo en el porcentaje de espectadores que fija el panel.
     * Si el servidor responde mal (por ejemplo 429 por el límite de peticiones), se rinde: el cron lo resolverá.
     */
    async avisarDelCierre() {
        if (this.avisoCierreHecho || !this.avisara || !estadoUrl || !this.adjudicando) return;
        if (this.ahora < this.objetivo + this.margen + this.esperaAviso) return;

        this.avisoCierreHecho = true; // pase lo que pase, no se reintenta
        try {
            const r = await fetch(estadoUrl, { cache: 'no-store', headers: { Accept: 'application/json' } });
            if (!r.ok) return;
            const estado = await r.json();
            const lote = estado.lotes?.find((l) => l.id === loteId) || estado.lotes?.[0];
            if (lote) {
                this.estadoLote = lote.estado;
                this.precio = lote.precio_actual;
                this.total = lote.total_pujas;
                this.pujas = lote.pujas || this.pujas;
            }
        } catch (e) { /* se rinde: el cron reescribe el JSON */ }
    },

    get restante() {
        return Math.max(0, Math.floor((this.objetivo - this.ahora) / 1000));
    },

    // Próximo: días/horas/min/seg. En vivo: horas totales/min/seg.
    get partes() {
        const rs = this.restante;
        return {
            d: dos(Math.floor(rs / 86400)),
            h: dos(enVivo ? Math.floor(rs / 3600) : Math.floor((rs % 86400) / 3600)),
            m: dos(Math.floor((rs % 3600) / 60)),
            s: dos(rs % 60),
        };
    },

    get tiempo() {
        const p = this.partes;
        return p.h + ':' + p.m + ':' + p.s;
    },

    get pujaActual() {
        return this.precio ? clp(this.precio) : t('remate.sin_pujas');
    },

    get pujaEnUf() {
        return this.precio && uf ? 'UF ' + (this.precio / uf).toLocaleString(locale(), { maximumFractionDigits: 0 }) : '—';
    },

    get haceUltima() {
        return this.pujas.length ? reloj(Math.floor((this.ahora - this.pujas[0].en_ms) / 1000)) : '—';
    },

    get historial() {
        return this.pujas.map((p) => ({
            monto: clp(p.monto),
            postor: alias(p.postor, 'remate'),
            hora: new Date(p.en_ms).toLocaleTimeString(locale(), { timeZone: 'America/Santiago', hourCycle: 'h23' }),
            hace: t('remate.hace', { tiempo: reloj(Math.floor((this.ahora - p.en_ms) / 1000)) }),
        }));
    },

    async compartir() {
        const url = window.location.href;
        try {
            if (navigator.share) {
                await navigator.share({ title: document.title, url });
                return;
            }
            await navigator.clipboard.writeText(url);
            this.compartido = t('remate.enlace_copiado');
        } catch (e) {
            this.compartido = '';
        }
    },

    // «Avísame antes de que comience» (Bloque M).
    async avisame(email) {
        if (!email) { this.avisoAbierto = true; return; }
        this.avisoEnviando = true;
        try {
            const r = await fetch(rutas.avisame, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '' },
                body: JSON.stringify({ email, remate }),
            });
            const datos = await r.json().catch(() => ({}));
            this.avisoMensaje = datos.mensaje || datos.message || (r.ok ? t('remate.listo') : t('remate.error_correo'));
        } catch (e) {
            this.avisoMensaje = t('remate.sin_conexion');
        } finally {
            this.avisoEnviando = false;
        }
    },

    iniciarMapa() {
        const el = this.$refs.mapa;
        if (!el || !mapa) return;
        const punto = [mapa.lat, mapa.lng];
        const m = L.map(el, { center: punto, zoom: 15, scrollWheelZoom: false });
        // OpenStreetMap: los mosaicos de Esri del prototipo son un servicio deprecado que exige
        // cuenta ArcGIS para uso comercial.
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        }).addTo(m);
        const icono = L.divIcon({ className: '', iconSize: [26, 26], iconAnchor: [13, 26], html: '<div class="mapa-marcador"><span></span></div>' });
        // No interactivo: el globo solo repetía la dirección que ya aparece bajo el mapa (y el marcador
        // de 26px no alcanza el área táctil mínima).
        L.marker(punto, { icon: icono, interactive: false, keyboard: false, alt: mapa.etiqueta }).addTo(m);
        L.circle(punto, { radius: 320, color: '#25408f', weight: 1, fillColor: '#25408f', fillOpacity: 0.08 }).addTo(m);
        setTimeout(() => m.invalidateSize(), 200);
    },
});
