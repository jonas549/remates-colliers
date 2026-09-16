import { rutValido } from './rut';

// Formulario de registro de postor: tipo de persona, RUT con dígito verificador,
// documentos adjuntos y aceptación de bases (habilita el envío).
// Los valores iniciales vienen del servidor cuando el envío volvió con errores.
export default (tipoInicial = 'natural', rutInicial = '', aceptaInicial = false) => ({
    tipo: tipoInicial,
    rut: rutInicial,
    acepta: aceptaInicial,
    cargados: {},

    get juridica() {
        return this.tipo === 'juridica';
    },

    // null = sin escribir, true/false = resultado de la validación
    get estadoRut() {
        return this.rut.length === 0 ? null : rutValido(this.rut);
    },

    get mensajeRut() {
        if (this.estadoRut === false) return 'Dígito verificador incorrecto';
        return this.estadoRut ? 'RUT válido' : 'Formato 12.345.678-9';
    },

    numero(base) {
        return String(base + (this.juridica ? 1 : 0)).padStart(2, '0');
    },

    elegirArchivo(id) {
        this.$refs['archivo-' + id].click();
    },

    archivoElegido(id, evento) {
        this.cargados = { ...this.cargados, [id]: evento.target.files.length > 0 };
    },
});
