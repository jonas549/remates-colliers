// Textos de interfaz para el JavaScript del sitio público (20/10). Salen de los archivos de idioma de Laravel
// (lang/{es,en}/<grupo>.php, clave «js») y el layout los deja en <script type="application/json" id="textos-js">.
// t('sala.vas_ganando') o t('sala.minimo', { monto: '$1.000' }) con los marcadores «:monto», como en Laravel.

let datos = null;

function cargar() {
    if (datos === null) {
        const nodo = document.getElementById('textos-js');
        try {
            datos = nodo ? JSON.parse(nodo.textContent) : {};
        } catch {
            datos = {};
        }
    }
    return datos;
}

// `respaldo`: lo que se muestra si la clave no existe (p. ej. un tipo de propiedad nuevo: se ve el dato tal cual).
export function t(clave, reemplazos = {}, respaldo = clave) {
    const valor = clave.split('.').reduce((o, k) => (o && typeof o === 'object' ? o[k] : undefined), cargar());
    let texto = typeof valor === 'string' ? valor : respaldo;
    for (const [k, v] of Object.entries(reemplazos)) {
        texto = texto.replaceAll(':' + k, String(v));
    }
    return texto;
}

// Idioma de la página (atributo lang del <html>): «es» o «en».
export const idioma = () => (document.documentElement.lang === 'en' ? 'en' : 'es');

// Locale de Intl para números y horas: «es-CL» o, en inglés, miles con coma.
export const locale = () => (idioma() === 'en' ? 'en-US' : 'es-CL');

// Dato del catálogo (tipo de propiedad, ocupación) para mostrar: el valor guardado sigue en español.
export const etiquetaDato = (grupo, valor) => (valor ? t('catalogo.' + grupo + '.' + valor, {}, valor) : valor);

// «Postor #N» viaja como dato en el JSON público; en pantalla se muestra en el idioma de la página.
export function alias(valor, grupo = 'sala') {
    const m = /^Postor #(\d+)$/.exec(valor || '');
    return m ? t(grupo + '.alias', { n: m[1] }, valor) : valor;
}
