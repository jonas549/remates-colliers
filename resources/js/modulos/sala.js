import { clp, dos } from './formato';

const UF = 39412.73;

// Sala de puja. Port de Puja en Vivo.dc.html: precio actual, estado del postor, cuenta regresiva,
// puja rápida, monto libre con validación, modal de confirmación obligatorio e historial.
// Bloque T: la puja se registra solo en pantalla. En el Bloque K se conecta al motor (Bloque J).
export default ({ base, paso, actual, deltaCierre, historial, postor }) => ({
    base,
    paso,
    actual,
    monto: '',
    modal: false,
    hoja: false,
    cerrado: false,
    t0: Date.now(),
    ahora: Date.now(),
    historialBruto: historial.map(([monto, numero, seg, yo]) => ({ monto, postor: 'Postor #' + numero, yo: !!yo, t: Date.now() - seg * 1000 })),

    init() {
        this.fin = this.t0 + deltaCierre * 1000;
        setInterval(() => { this.ahora = Date.now(); }, 1000);
    },

    get restante() {
        return Math.max(0, Math.floor((this.fin - Math.max(this.ahora, Date.now())) / 1000));
    },
    get vencido() { return this.restante === 0 || this.cerrado; },
    get hayPujas() { return this.historialBruto.length > 0; },
    get yoGanando() { return !!this.historialBruto[0] && this.historialBruto[0].yo; },
    get minimo() { return this.actual + this.paso; },
    get escrito() { return parseInt(String(this.monto).replace(/[^\d]/g, ''), 10) || 0; },
    get montoPuja() { return this.escrito > 0 ? this.escrito : this.minimo; },
    get montoValido() { return this.montoPuja >= this.minimo; },
    get montoInvalidoVisible() { return this.escrito > 0 && !this.montoValido; },

    get hh() { return dos(Math.floor(this.restante / 3600)); },
    get mm() { return dos(Math.floor((this.restante % 3600) / 60)); },
    get ss() { return dos(this.restante % 60); },
    get enMinutoFinal() { return this.restante < 60 && !this.vencido; },

    get estadoTexto() {
        if (!this.vencido) return 'EN VIVO · PUJA ABIERTA';
        return (this.hayPujas ? 'Adjudicado' : 'Cerrado sin postores').toUpperCase();
    },
    get tiempoTexto() {
        return this.vencido ? 'Remate finalizado' : 'Cierra en ' + this.hh + ':' + this.mm + ':' + this.ss;
    },

    get actualTexto() { return clp(this.actual); },
    get actualEnUf() { return 'UF ' + (this.actual / UF).toLocaleString('es-CL', { maximumFractionDigits: 0 }); },
    get sobreBase() { return '+' + Math.round((this.actual / this.base - 1) * 100) + '%'; },
    get espectadores() { return 128 + ((Math.floor((this.ahora - this.fin) / 7000) % 40) + 40); },

    get estadoTitulo() { return this.yoGanando ? 'Vas ganando' : 'Te superaron'; },
    get estadoDetalle() {
        return this.yoGanando
            ? 'Tu postura es la más alta registrada en este remate.'
            : 'La puja más alta es de otro postor. Puedes ofertar desde ' + clp(this.minimo) + '.';
    },

    get rapidas() {
        return [100000, 500000, 1000000].map((inc) => ({
            label: inc >= 1000000 ? '+ $1M' : '+ $' + inc / 1000 + 'k',
            total: clp(this.actual + inc),
            valor: this.actual + inc,
        }));
    },

    escribir(valor) {
        this.monto = String(valor).replace(/[^\d]/g, '');
    },

    get ayuda() {
        return this.montoInvalidoVisible
            ? 'La postura debe ser al menos ' + clp(this.minimo) + '.'
            : 'Incrementos de ' + clp(this.paso) + ' o múltiplos. Referencia: UF ' + (this.montoPuja / UF).toLocaleString('es-CL', { maximumFractionDigits: 0 }) + '.';
    },
    get placeholder() { return 'Mínimo ' + clp(this.minimo); },
    get botonTexto() { return 'Pujar ' + clp(this.montoPuja); },

    abrirModal() {
        if (this.montoValido && !this.vencido) this.modal = true;
    },
    confirmar() {
        const monto = this.montoPuja;
        this.actual = monto;
        this.monto = '';
        this.modal = false;
        this.hoja = false;
        this.historialBruto = [{ monto, postor: 'Postor #' + postor + ' (tú)', yo: true, t: Date.now() }, ...this.historialBruto];
    },

    get modalMonto() { return clp(this.montoPuja); },
    get modalUf() { return 'UF ' + (this.montoPuja / UF).toLocaleString('es-CL', { maximumFractionDigits: 0 }); },
    get modalDiferencia() { return clp(this.montoPuja - this.actual); },

    get historialVista() {
        const ahora = Math.max(this.ahora, Date.now());
        return this.historialBruto.map((p) => {
            const seg = Math.max(0, Math.floor((ahora - p.t) / 1000));
            const d = new Date(p.t);
            return {
                monto: clp(p.monto),
                postor: p.postor,
                yo: p.yo,
                hora: dos(d.getHours()) + ':' + dos(d.getMinutes()) + ':' + dos(d.getSeconds()),
                hace: seg < 60 ? 'hace ' + seg + ' seg' : seg < 3600 ? 'hace ' + Math.floor(seg / 60) + ' min' : 'hace ' + Math.floor(seg / 3600) + ' h',
            };
        });
    },

    get resultadoTitulo() {
        if (!this.hayPujas) return 'El remate se cerró sin posturas';
        return this.yoGanando ? 'Te adjudicaste la propiedad' : 'Adjudicado a otro postor';
    },
    get resultadoTexto() {
        if (!this.hayPujas) return 'No se registraron posturas sobre el precio base. La propiedad se publicará en un remate nuevo, con fecha y condiciones propias.';
        return this.yoGanando
            ? 'Precio final ' + clp(this.actual) + '. Un ejecutivo te contactará hoy para la firma y el pago del saldo. Tu garantía se imputa al precio.'
            : 'Precio final ' + clp(this.actual) + '. Tu garantía será devuelta dentro de los próximos días hábiles.';
    },
});
