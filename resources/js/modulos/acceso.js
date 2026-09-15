// Formulario de ingreso: botón habilitado solo con ambos campos, mostrar/ocultar clave
// y aviso de error al intentar ingresar con campos vacíos (comportamiento del prototipo).
export default () => ({
    usuario: '',
    clave: '',
    ver: false,
    recordar: true,
    error: false,

    get listo() {
        return this.usuario.trim().length > 0 && this.clave.length > 0;
    },

    alternarClave() {
        this.ver = !this.ver;
    },

    enviar(evento) {
        if (!this.listo) {
            evento.preventDefault();
            this.error = true;
        }
    },
});
