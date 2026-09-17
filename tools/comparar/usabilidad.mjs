// Comprobaciones de usabilidad compartidas por los recorridos de pantallas sin original en el prototipo:
// sin scroll horizontal y, bajo 1120 px, áreas táctiles de al menos 44 px (acuerdo del Bloque T).

/** @returns {Promise<{desborde: boolean, pequenos: string[], titulo: string}>} */
export async function revisarUsabilidad(pagina, ancho) {
    return pagina.evaluate((ancho) => {
        const doc = document.documentElement;
        const pequenos = [];
        if (ancho < 1120) {
            for (const el of document.querySelectorAll('a[href], button, input:not([type=hidden]), select, textarea')) {
                const estilo = getComputedStyle(el);
                if (estilo.display === 'none' || estilo.visibility === 'hidden') continue;
                const caja = el.getBoundingClientRect();
                if (caja.width === 0 && caja.height === 0) continue;
                const padre = el.parentElement;
                const enLinea = estilo.display === 'inline' && padre && padre.textContent.trim().length > el.textContent.trim().length;
                const etiqueta = ['INPUT', 'SELECT', 'TEXTAREA'].includes(el.tagName) && el.closest('label');
                const objetivo = etiqueta ? etiqueta.getBoundingClientRect() : caja;
                if (!enLinea && (objetivo.height < 44 || objetivo.width < 44)) {
                    pequenos.push(`${el.tagName.toLowerCase()} "${(el.textContent || el.placeholder || el.name || '').trim().slice(0, 30)}" ${Math.round(objetivo.width)}x${Math.round(objetivo.height)}`);
                }
            }
        }
        return { desborde: doc.scrollWidth > doc.clientWidth, pequenos, titulo: document.querySelector('h1')?.textContent.trim() };
    }, ancho);
}
