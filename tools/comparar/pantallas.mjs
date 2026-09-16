// Registro de pantallas a comparar contra el prototipo de Claude Design.
//
// original:  ruta del .dc.html relativa a la raíz del proyecto, donde están ambas carpetas del
//            prototipo (servida con: php -S 127.0.0.1:8081 -t .). Una variante puede
//            sobrescribirla con su propio `original` (p. ej. invitado vs. usuario).
// laravel:   ruta de la aplicación (servida en LARAVEL_URL)
// variantes: estados simulados. `props` sobrescribe los valores por defecto de data-props
//            en el original; `query` se agrega a la URL de Laravel.
// pasos:     selectores a los que se hace clic (en ambos lados) antes de capturar.
// sesion:    rol con el que la aplicación entra antes de capturar (pantallas protegidas, Bloque D).
// mascaras:  selectores que se tapan en ambas capturas (contenido que no puede coincidir:
//            iframes de YouTube, mosaicos de mapa).

export const ANCHOS_ESTRICTOS = [1120, 1280, 1366, 1440];
export const ANCHOS_REFERENCIA = [375, 759, 760, 1024, 1119];

export const PANTALLAS = {
    'admin-reportes': {
        original: 'colliers-subastas-usuario-main/Admin Reportes.dc.html',
        laravel: '/admin/reportes',
        sesion: 'admin',
        alto: 1050,
        variantes: [{ id: 'base' }],
        mascaras: [],
    },
    'admin-subastas': {
        original: 'colliers-subastas-usuario-main/Admin Subastas.dc.html',
        laravel: '/admin/subastas',
        sesion: 'admin',
        alto: 900,
        variantes: [
            { id: 'base' },
            { id: 'formulario', pasos: ['button:has-text("Crear subasta")'] },
            { id: 'cierre', pasos: ['button:has-text("Cerrar ahora")'] },
            { id: 'filtro-cerradas', pasos: ['button:has-text("CERRADAS")'] },
        ],
        mascaras: [],
    },
    'admin-postores': {
        original: 'colliers-subastas-usuario-main/Admin Postores.dc.html',
        laravel: '/admin/postores',
        sesion: 'admin',
        alto: 900,
        variantes: [
            { id: 'base' },
            { id: 'ficha', pasos: ['button:has-text("Ficha"):visible'] },
            { id: 'filtro-revision', pasos: ['button:has-text("EN REVISIÓN")'] },
        ],
        mascaras: [],
    },
    'admin-dashboard': {
        original: 'colliers-subastas-usuario-main/Admin Dashboard.dc.html',
        laravel: '/admin',
        sesion: 'admin',
        alto: 1000,
        variantes: [{ id: 'base' }],
        mascaras: [],
    },
    sala: {
        original: 'colliers-subastas-usuario-main/Puja en Vivo.dc.html',
        laravel: '/remates/apoquindo/sala',
        alto: 900,
        variantes: [{ id: 'base', props: { simularRivales: false } }],
        mascaras: ['iframe'],
    },
    'detalle-vivo': {
        original: 'colliers-subastas-usuario-main/Detalle Remate en Vivo.dc.html',
        laravel: '/remates/apoquindo',
        alto: 900,
        variantes: [
            { id: 'visitante', original: 'colliers-subastas-invitado-main/Detalle Remate en Vivo.dc.html', props: { estadoUsuario: 'Visitante', simularPujas: false } },
            { id: 'registrado', props: { estadoUsuario: 'Registrado', simularPujas: false }, query: 'sesion=registrado' },
            { id: 'en-revision', props: { estadoUsuario: 'En revisión', simularPujas: false }, query: 'sesion=en-revision' },
            { id: 'aprobada', props: { estadoUsuario: 'Aprobada', simularPujas: false }, query: 'sesion=aprobada' },
        ],
        mascaras: ['.leaflet-container', 'iframe'],
    },
    'detalle-proximo': {
        original: 'colliers-subastas-usuario-main/Detalle Remate Proximo.dc.html',
        laravel: '/remates/militares',
        alto: 900,
        variantes: [
            { id: 'visitante', original: 'colliers-subastas-invitado-main/Detalle Remate Proximo.dc.html', props: { estadoUsuario: 'Visitante' } },
            { id: 'registrado', props: { estadoUsuario: 'Registrado' }, query: 'sesion=registrado' },
            { id: 'en-revision', props: { estadoUsuario: 'En revisión' }, query: 'sesion=en-revision' },
            { id: 'aprobada', props: { estadoUsuario: 'Aprobada' }, query: 'sesion=aprobada' },
        ],
        mascaras: ['.leaflet-container'],
    },
    listado: {
        original: 'colliers-subastas-usuario-main/index.dc.html',
        laravel: '/',
        alto: 900,
        variantes: [
            { id: 'visitante', original: 'colliers-subastas-invitado-main/index.dc.html' },
            { id: 'registrado', props: { estadoUsuario: 'Registrado' }, query: 'sesion=registrado' },
            { id: 'en-revision', props: { estadoUsuario: 'En revisión' }, query: 'sesion=en-revision' },
            { id: 'aprobada', props: { estadoUsuario: 'Aprobada' }, query: 'sesion=aprobada' },
        ],
        mascaras: [],
    },
    cuenta: {
        original: 'colliers-subastas-usuario-main/Estado Cuenta.dc.html',
        laravel: '/mi-cuenta',
        sesion: 'postor',
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
        original: 'colliers-subastas-usuario-main/Registro Postor.dc.html',
        laravel: '/registro',
        alto: 900,
        variantes: [{ id: 'base' }],
        mascaras: [],
    },
    login: {
        original: 'colliers-subastas-usuario-main/Login.dc.html',
        laravel: '/ingresar',
        alto: 820,
        variantes: [{ id: 'base' }],
        mascaras: [],
    },
};
