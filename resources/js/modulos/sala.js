import { clp, dos } from './formato';

const TERMINALES = ['adjudicado', 'desierto', 'cerrado', 'incumplido'];

// Sala de puja conectada al motor (Bloque K).
// - Toda hora sale del servidor: el reloj se sincroniza contra /hora compensando la latencia.
// - El estado llega por el JSON estático (~1 s, sin PHP). Si falla, se consulta el endpoint de estado.
// - Pasado cierra_en + margen sin liquidar, el navegador pide el estado a PHP (detector del cierre perezoso).
// - La base de datos es la fuente de verdad: tras cada puja se vuelve a leer el estado.
// - Varios lotes: la ficha sigue al lote vigente y un aviso cuenta cómo terminó el anterior (no hay diseño: mínimo).
export default ({ remate, loteInicial, lotes = {}, miAlias, pujasRapidas, estado, servidorMs, urls, uf = null }) => ({
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

    init() {
        this.sincronizar();
        setInterval(() => this.sincronizar(), 60000);
        setInterval(() => this.tic(), 250);
        this.consultar();
        const reconectar = () => { this.sincronizar(); this.consultarPhp(true); };
        document.addEventListener('visibilitychange', () => { if (!document.hidden) reconectar(); });
        window.addEventListener('online', reconectar);
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
        const lote = this.lote;
        if (!lote || TERMINALES.includes(lote.estado) || lote.cierra_en_ms === null) return;
        const limite = lote.cierra_en_ms + this.estado.margen_liquidacion_ms + 300 + Math.random() * 1200;
        if (this.ahora >= limite) this.consultarPhp(false);
    },

    // ── Estado ────────────────────────────────────────────────────────────────────────────────────────────
    async consultar() {
        let espera = 1000;
        try {
            const r = await fetch(urls.estadoJson + '?t=' + Date.now(), { cache: 'no-store' });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            this.aplicar(await r.json());
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
            const r = await fetch(urls.estado, { cache: 'no-store', headers: { Accept: 'application/json' } });
            if (r.ok) this.aplicar(await r.json());
        } catch (e) { /* el sondeo del JSON sigue */ }
    },

    aplicar(nuevo) {
        if (!nuevo || !Array.isArray(nuevo.lotes)) return;
        if (nuevo.generado_en_ms < this.estado.generado_en_ms) return; // nunca retroceder
        this.estado = nuevo;
        const vigente = nuevo.lotes.find((l) => !TERMINALES.includes(l.estado)) || nuevo.lotes[nuevo.lotes.length - 1];
        const actual = nuevo.lotes.find((l) => l.id === this.loteId);
        // Se queda en el lote actual hasta que termina; entonces pasa al siguiente sin liquidar.
        if (!actual || (TERMINALES.includes(actual.estado) && vigente && vigente.id !== actual.id && !TERMINALES.includes(vigente.estado))) {
            if (actual) this.anterior = { orden: actual.orden, estado: actual.estado, precio: actual.precio_actual, siguiente: vigente.orden };
            this.loteId = vigente.id;
        }
    },

    get lote() {
        return this.estado.lotes.find((l) => l.id === this.loteId) || this.estado.lotes[0];
    },
    get info() { return lotes[this.lote.id] || {}; },
    get etiquetaLote() {
        const total = this.estado.lotes.length;
        return total > 1 ? 'Lote ' + this.lote.orden + ' de ' + total : '';
    },
    get avisoLote() {
        const a = this.anterior;
        if (!a || a.siguiente !== this.lote.orden) return '';
        const cierre = a.estado === 'adjudicado' ? 'se adjudicó en ' + clp(a.precio) : 'cerró sin posturas';
        return 'El lote ' + a.orden + ' ' + cierre + '. Ahora se remata el lote ' + this.lote.orden + '.';
    },
    get mensajeMartillero() { return this.estado.mensaje_martillero?.texto || ''; },
    get mensajeHace() {
        const en = this.estado.mensaje_martillero?.en_ms;
        if (!en) return '';
        const seg = Math.max(0, Math.floor((this.ahora - en) / 1000));
        return seg < 60 ? 'hace ' + seg + ' seg' : 'hace ' + Math.floor(seg / 60) + ' min';
    },
    get terminal() { return TERMINALES.includes(this.lote.estado); },
    get antesDeAbrir() { return this.lote.abre_en_ms !== null && this.ahora < this.lote.abre_en_ms; },
    get abierto() { return !this.terminal && !this.antesDeAbrir && this.lote.cierra_en_ms !== null && this.ahora < this.lote.cierra_en_ms; },

    // ── Lo que muestra la vista (mismos nombres que la sala del prototipo) ─────────────────────────────────
    get restante() {
        const objetivo = this.antesDeAbrir ? this.lote.abre_en_ms : this.lote.cierra_en_ms;
        return objetivo === null ? 0 : Math.max(0, Math.floor((objetivo - this.ahora) / 1000));
    },
    get vencido() { return this.terminal || (!this.antesDeAbrir && this.restante === 0); },
    get hayPujas() { return this.lote.total_pujas > 0; },
    get totalPujas() { return this.lote.total_pujas; },
    get yoGanando() { return !!this.miAlias && this.lote.ganador === this.miAlias; },
    get actual() { return this.lote.precio_actual ?? this.lote.precio_base; },
    get minimo() { return this.lote.puja_minima; },
    get paso() { return this.estado.incremento_minimo; },
    get escrito() { return parseInt(String(this.monto).replace(/[^\d]/g, ''), 10) || 0; },
    get montoPuja() { return this.escrito > 0 ? this.escrito : this.minimo; },
    get montoValido() { return this.abierto && !this.yoGanando && this.montoPuja >= this.minimo; },
    get montoInvalidoVisible() { return !!this.error || (this.escrito > 0 && this.montoPuja < this.minimo); },

    get hh() { return dos(Math.floor(this.restante / 3600)); },
    get mm() { return dos(Math.floor((this.restante % 3600) / 60)); },
    get ss() { return dos(this.restante % 60); },
    get enMinutoFinal() { return this.abierto && this.restante < 60; },
    get etiquetaContador() { return this.antesDeAbrir ? 'ABRE EN' : 'CIERRA EN'; },

    get estadoTexto() {
        if (this.lote.estado === 'adjudicado') return 'ADJUDICADO';
        if (this.lote.estado === 'desierto') return 'CERRADO SIN POSTORES';
        if (this.antesDeAbrir) return 'PRÓXIMO · PUJA AÚN NO ABRE';
        if (this.vencido) return 'CERRADO · ADJUDICANDO';
        return 'EN VIVO · PUJA ABIERTA';
    },
    get tiempoTexto() {
        if (this.terminal) return 'Remate finalizado';
        if (this.antesDeAbrir) return 'Abre en ' + this.hh + ':' + this.mm + ':' + this.ss;
        return this.vencido ? 'Cerrado' : 'Cierra en ' + this.hh + ':' + this.mm + ':' + this.ss;
    },

    formatoClp(n) { return clp(n); },
    get actualTexto() { return clp(this.actual); },
    // UF: solo referencia visual y solo si hay un valor vigente (Administración → Configuración).
    enUf(n) { return uf ? 'UF ' + (n / uf).toLocaleString('es-CL', { maximumFractionDigits: 0 }) : ''; },
    get actualEnUf() { return this.enUf(this.actual) || '—'; },
    get sobreBase() { return '+' + Math.round((this.actual / this.lote.precio_base - 1) * 100) + '%'; },

    get estadoTitulo() {
        if (!this.hayPujas) return 'Sin posturas';
        return this.yoGanando ? 'Vas ganando' : 'Te superaron';
    },
    get estadoDetalle() {
        if (!this.hayPujas) return 'Nadie ha pujado todavía. La primera postura parte en ' + clp(this.minimo) + '.';
        return this.yoGanando
            ? 'Tu postura es la más alta registrada en este remate.'
            : 'La puja más alta es de otro postor. Puedes ofertar desde ' + clp(this.minimo) + '.';
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
        if (this.antesDeAbrir) return 'La puja abre en ' + this.hh + ':' + this.mm + ':' + this.ss + '.';
        if (this.yoGanando) return 'Tienes la puja más alta: espera a que otro postor la supere.';
        if (this.escrito > 0 && this.montoPuja < this.minimo) return 'La postura debe ser al menos ' + clp(this.minimo) + '.';
        return 'Incremento mínimo ' + clp(this.paso) + '.' + (uf ? ' Referencia: ' + this.enUf(this.montoPuja) + '.' : '');
    },
    get placeholder() { return 'Mínimo ' + clp(this.minimo); },
    get botonTexto() { return this.enviando ? 'Enviando…' : 'Pujar ' + clp(this.montoPuja); },

    abrirModal() {
        this.error = '';
        if (this.montoValido && !this.enviando) this.modal = true;
    },

    // Registra la puja en el motor. El modal de confirmación es obligatorio (acta).
    async confirmar() {
        if (this.enviando) return;
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
                this.error = cuerpo.mensaje || 'La puja fue rechazada.';
            } else if (r.status === 419) {
                this.error = 'Tu sesión expiró. Recarga la página para seguir pujando.';
            } else if (r.status === 429) {
                this.error = 'Estás enviando pujas muy seguido. Espera unos segundos.';
            } else {
                this.error = 'No pudimos confirmar tu puja. Revisa el historial: si no aparece, vuelve a intentarlo.';
            }
            if (cuerpo.lote) this.mezclarResumen(cuerpo.lote);
        } catch (e) {
            this.error = 'Sin conexión: no sabemos si la puja llegó. Revisa el historial antes de reintentar.';
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
                postor: p.postor === this.miAlias ? p.postor + ' (tú)' : p.postor,
                yo: p.postor === this.miAlias,
                hora: new Date(p.en_ms).toLocaleTimeString('es-CL', { timeZone: 'America/Santiago', hour12: false }),
                hace: seg < 60 ? 'hace ' + seg + ' seg' : seg < 3600 ? 'hace ' + Math.floor(seg / 60) + ' min' : 'hace ' + Math.floor(seg / 3600) + ' h',
            };
        });
    },

    get resultadoTitulo() {
        if (!this.terminal) return 'Remate cerrado';
        if (!this.hayPujas) return 'El remate se cerró sin posturas';
        return this.yoGanando ? 'Te adjudicaste la propiedad' : 'Adjudicado a otro postor';
    },
    get resultadoTexto() {
        if (!this.terminal) return 'Estamos confirmando la adjudicación con las posturas recibidas antes del cierre.';
        if (!this.hayPujas) return 'No se registraron posturas sobre el precio base. La propiedad se publicará en un remate nuevo, con fecha y condiciones propias.';
        // Neutro a propósito: qué pasa con la garantía (devolución o imputación) está pendiente con el cliente.
        return this.yoGanando
            ? 'Precio final ' + clp(this.actual) + '. Colliers te contactará para la firma y el pago del saldo, según las bases del remate.'
            : 'Precio final ' + clp(this.actual) + '. Colliers te informará sobre tu garantía según las bases del remate.';
    },
});
