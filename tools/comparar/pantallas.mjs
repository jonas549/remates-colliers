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
    login: {
        original: 'Login.dc.html',
        laravel: '/ingresar',
        alto: 820,
        variantes: [{ id: 'base' }],
        mascaras: [],
    },
};
