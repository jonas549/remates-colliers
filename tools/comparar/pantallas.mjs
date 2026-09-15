// Registro de pantallas a comparar contra el prototipo de Claude Design.
//
// original:  archivo .dc.html dentro de la carpeta del prototipo (servida en PROTOTIPO_URL)
// laravel:   ruta de la aplicación (servida en LARAVEL_URL)
// variantes: estados simulados. `props` sobrescribe los valores por defecto de data-props
//            en el original; `query` se agrega a la URL de Laravel.
// mascaras:  selectores que se tapan en ambas capturas (contenido que no puede coincidir:
//            iframes de YouTube, mosaicos de mapa).

export const ANCHOS_ESTRICTOS = [1120, 1280, 1366, 1440];
export const ANCHOS_REFERENCIA = [375, 759, 760, 1024, 1119];

export const PANTALLAS = {
    cuenta: {
        original: 'Estado Cuenta.dc.html',
        laravel: '/mi-cuenta',
        alto: 900,
        variantes: [
            { id: 'aprobada', props: { estado: 'Aprobada' }, query: 'estado=aprobada' },
            { id: 'cuenta-revision', props: { estado: 'Cuenta en revisión' }, query: 'estado=cuenta-revision' },
            { id: 'garantia-pendiente', props: { estado: 'Garantía pendiente' }, query: 'estado=garantia-pendiente' },
            { id: 'garantia-revision', props: { estado: 'Garantía en revisión' }, query: 'estado=garantia-revision' },
            { id: 'rechazada', props: { estado: 'Rechazada' }, query: 'estado=rechazada' },
        ],
        mascaras: [],
    },
    registro: {
        original: 'Registro Postor.dc.html',
        laravel: '/registro',
        alto: 900,
        variantes: [{ id: 'base' }],
        mascaras: [],
    },
    login: {
        original: 'Login.dc.html',
        laravel: '/ingresar',
        alto: 820,
        variantes: [{ id: 'base' }],
        mascaras: [],
    },
};
