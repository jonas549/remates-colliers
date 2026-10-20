import { rutValido } from './rut';
import { t } from './textos';

// Formulario de registro de postor: tipo de persona, RUT con dígito verificador,
// documentos adjuntos y aceptación de bases (habilita el envío).
// Los valores iniciales vienen del servidor cuando el envío volvió con errores.
export default (tipoInicial = 'natural', rutInicial = '', aceptaInicial = false, guardadosIniciales = {}) => ({
    tipo: tipoInicial,
    rut: rutInicial,
    acepta: aceptaInicial,
    // Nombre del archivo que el servidor conserva de un intento fallido (el navegador no puede prellenar un file input).
    guardados: guardadosIniciales,
    cargados: Object.fromEntries(Object.keys(guardadosIniciales).map((id) => [id, true])),

    get juridica() {
        return this.tipo === 'juridica';
    },

    // null = sin escribir, true/false = resultado de la validación
    get estadoRut() {
        return this.rut.length === 0 ? null : rutValido(this.rut);
    },

    get mensajeRut() {
        if (this.estadoRut === false) return t('acceso.rut_incorrecto');
        return this.estadoRut ? t('acceso.rut_valido') : t('acceso.formato_rut');
    },

    numero(base) {
        return String(base + (this.juridica ? 1 : 0)).padStart(2, '0');
    },

    elegirArchivo(id) {
        this.$refs['archivo-' + id].click();
    },

    archivoElegido(id, evento) {
        const elegido = evento.target.files.length > 0;
        this.cargados = { ...this.cargados, [id]: elegido || !!this.guardados[id] };
        this.guardados = { ...this.guardados, [id]: elegido ? evento.target.files[0].name : this.guardados[id] };
    },

    detalle(id, porDefecto) {
        return this.guardados[id] ? t('acceso.ya_adjuntado', { nombre: this.guardados[id] }) : porDefecto;
    },
});
