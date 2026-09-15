import L from 'leaflet';
import { clp, dos } from './formato';

const UF = 39412.73;

function reloj(segundos) {
    const s = Math.max(0, segundos);
    if (s < 60) return s + ' seg';
    if (s < 3600) return Math.floor(s / 60) + ' min';
    return Math.floor(s / 3600) + ' h';
}

function horaDe(fecha) {
    return dos(fecha.getHours()) + ':' + dos(fecha.getMinutes()) + ':' + dos(fecha.getSeconds());
}

// Detalle de remate: cuenta regresiva (inicio o cierre), historial de pujas y mapa.
// delta: segundos desde la carga hasta el inicio/cierre (demo). pujas: [monto, postor, segundos atrás].
export default ({ delta, pujas, mapa }) => ({
    pujas,
    t0: Date.now(),
    ahora: Date.now(),

    init() {
        setInterval(() => { this.ahora = Date.now(); }, 1000);
        this.$nextTick(() => this.iniciarMapa());
    },

    get restante() {
        return Math.max(0, Math.floor((this.t0 + delta * 1000 - this.ahora) / 1000));
    },

    // Próximo: días/horas/min/seg. En vivo: horas totales/min/seg.
    get partes() {
        const rs = this.restante;
        return {
            d: dos(Math.floor(rs / 86400)),
            h: dos(this.pujas.length ? Math.floor(rs / 3600) : Math.floor((rs % 86400) / 3600)),
            m: dos(Math.floor((rs % 3600) / 60)),
            s: dos(rs % 60),
        };
    },

    get tiempo() {
        const p = this.partes;
        return p.h + ':' + p.m + ':' + p.s;
    },

    get transcurrido() {
        return Math.floor((this.ahora - this.t0) / 1000);
    },

    get pujaActual() {
        return this.pujas.length ? clp(this.pujas[0][0]) : '';
    },

    get pujaEnUf() {
        return this.pujas.length ? 'UF ' + (this.pujas[0][0] / UF).toLocaleString('es-CL', { maximumFractionDigits: 0 }) : '';
    },

    get haceUltima() {
        return this.pujas.length ? reloj(this.pujas[0][2] + this.transcurrido) : '';
    },

    get historial() {
        return this.pujas.map(([monto, postor, seg]) => ({
            monto: clp(monto),
            postor: 'Postor #' + postor,
            hora: horaDe(new Date(this.t0 - seg * 1000)),
            hace: 'hace ' + reloj(seg + this.transcurrido),
        }));
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
