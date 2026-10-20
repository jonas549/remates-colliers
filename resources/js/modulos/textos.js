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

export function t(clave, reemplazos = {}) {
    const valor = clave.split('.').reduce((o, k) => (o && typeof o === 'object' ? o[k] : undefined), cargar());
    let texto = typeof valor === 'string' ? valor : clave;
    for (const [k, v] of Object.entries(reemplazos)) {
        texto = texto.replaceAll(':' + k, String(v));
    }
    return texto;
}

// Idioma de la página (atributo lang del <html>): «es» o «en».
export const idioma = () => (document.documentElement.lang === 'en' ? 'en' : 'es');
