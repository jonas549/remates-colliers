// Compara pantallas Blade contra el prototipo original, captura por captura.
//
// Uso:
//   npm run comparar -- login                 (todas las variantes y anchos)
//   npm run comparar -- login --anchos 1440   (solo ese ancho)
//
// Requisitos: prototipo servido en PROTOTIPO_URL (php -S 127.0.0.1:8081 -t colliers-subastas-usuario-main)
// y la aplicación en LARAVEL_URL (php artisan serve). El original necesita internet (React por unpkg).
//
// Salida: tools/comparar/salida/<pantalla>/<variante>/<ancho>-{original,laravel,diff}.png y resumen.json

import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import pixelmatch from 'pixelmatch';
import { PNG } from 'pngjs';
import { PANTALLAS, ANCHOS_ESTRICTOS, ANCHOS_REFERENCIA } from './pantallas.mjs';

const PROTOTIPO_URL = process.env.PROTOTIPO_URL || 'http://127.0.0.1:8081';
const LARAVEL_URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const HORA_FIJA = new Date('2026-08-31T15:00:00Z');
const SALIDA = path.resolve('tools/comparar/salida');

const args = process.argv.slice(2);
const nombre = args.find((a) => !a.startsWith('--'));
const idxAnchos = args.indexOf('--anchos');
const anchosFiltro = idxAnchos >= 0 ? args[idxAnchos + 1].split(',').map(Number) : null;

if (!nombre || !PANTALLAS[nombre]) {
    console.error('Pantalla desconocida. Disponibles: ' + Object.keys(PANTALLAS).join(', '));
    process.exit(1);
}

const pantalla = PANTALLAS[nombre];
const anchos = [...ANCHOS_ESTRICTOS, ...ANCHOS_REFERENCIA].filter((a) => !anchosFiltro || anchosFiltro.includes(a));

function reescribirProps(html, props) {
    if (!props) return html;
    return html.replace(/data-props="([^"]*)"/, (_, crudo) => {
        const json = JSON.parse(crudo.replace(/&quot;/g, '"'));
        for (const [k, v] of Object.entries(props)) {
            if (json[k]) json[k].default = v;
        }
        return 'data-props="' + JSON.stringify(json).replace(/"/g, '&quot;') + '"';
    });
}

async function capturar(navegador, url, ancho, alto, variante, esOriginal) {
    const contexto = await navegador.newContext({
        viewport: { width: ancho, height: alto },
        deviceScaleFactor: 1,
        reducedMotion: 'reduce',
        locale: 'es-CL',
        timezoneId: 'America/Santiago',
    });
    const pagina = await contexto.newPage();
    await pagina.clock.install({ time: HORA_FIJA });
    await pagina.clock.pauseAt(HORA_FIJA);

    if (esOriginal && variante.props) {
        await pagina.route(url, async (ruta) => {
            const respuesta = await ruta.fetch();
            const cuerpo = reescribirProps(await respuesta.text(), variante.props);
            await ruta.fulfill({ response: respuesta, body: cuerpo });
        });
    }

    await pagina.goto(url, { waitUntil: 'networkidle' });
    if (esOriginal) {
        await pagina.waitForSelector('#dc-root .sc-host > *', { timeout: 30000 });
    }
    await pagina.evaluate(async () => {
        await document.fonts.ready;
        await Promise.all([...document.images].map((img) => img.complete ? null : new Promise((r) => { img.onload = img.onerror = r; })));
    });
    await pagina.waitForTimeout(300);

    const mascaras = (pantalla.mascaras || []).map((sel) => pagina.locator(sel));
    const buffer = await pagina.screenshot({ fullPage: true, mask: mascaras, maskColor: '#ff00ff', animations: 'disabled' });

    let usabilidad = null;
    if (!esOriginal && ancho < 1120) {
        usabilidad = await pagina.evaluate(() => {
            const doc = document.documentElement;
            const desborde = doc.scrollWidth > doc.clientWidth;
            const pequenos = [];
            const selector = 'a[href], button, input:not([type=hidden]), select, textarea, summary, [role=button]';
            for (const el of document.querySelectorAll(selector)) {
                const estilo = getComputedStyle(el);
                if (estilo.display === 'none' || estilo.visibility === 'hidden') continue;
                const caja = el.getBoundingClientRect();
                if (caja.width === 0 && caja.height === 0) continue;
                // Enlaces dentro de una frase están exentos (WCAG 2.5.8, excepción "inline"):
                // en línea y con texto propio del contenedor alrededor.
                const padre = el.parentElement;
                const enLinea = estilo.display === 'inline' && padre &&
                    padre.textContent.trim().length > el.textContent.trim().length;
                // Campos dentro de una etiqueta cuentan con la etiqueta como área táctil
                // (tocar la etiqueta enfoca o marca el campo).
                const etiqueta = ['INPUT', 'SELECT', 'TEXTAREA'].includes(el.tagName) && el.closest('label');
                const objetivo = etiqueta ? etiqueta.getBoundingClientRect() : caja;
                if (!enLinea && (objetivo.height < 44 || objetivo.width < 44)) {
                    pequenos.push({
                        elemento: el.tagName.toLowerCase() + (el.type ? '[' + el.type + ']' : ''),
                        texto: (el.textContent || el.placeholder || el.getAttribute('aria-label') || '').trim().slice(0, 40),
                        tamano: Math.round(objetivo.width) + 'x' + Math.round(objetivo.height),
                    });
                }
            }
            return { desbordeHorizontal: desborde, anchoDocumento: doc.scrollWidth, objetivosPequenos: pequenos };
        });
    }

    await contexto.close();
    return { png: PNG.sync.read(buffer), usabilidad };
}

// Agrupa los píxeles distintos (rojo puro en la imagen de pixelmatch) en franjas horizontales,
// para decir DÓNDE está cada diferencia sin tener que abrir la imagen.
function zonasDistintas(diff) {
    const filas = [];
    for (let y = 0; y < diff.height; y++) {
        let x0 = -1, x1 = -1, n = 0;
        for (let x = 0; x < diff.width; x++) {
            const i = (y * diff.width + x) * 4;
            if (diff.data[i] === 255 && diff.data[i + 1] === 0 && diff.data[i + 2] === 0) {
                if (x0 < 0) x0 = x;
                x1 = x;
                n++;
            }
        }
        if (n) filas.push({ y, x0, x1, n });
    }
    const zonas = [];
    for (const f of filas) {
        const ultima = zonas[zonas.length - 1];
        if (ultima && f.y - ultima.y1 <= 8) {
            ultima.y1 = f.y;
            ultima.x0 = Math.min(ultima.x0, f.x0);
            ultima.x1 = Math.max(ultima.x1, f.x1);
            ultima.pixeles += f.n;
        } else {
            zonas.push({ y0: f.y, y1: f.y, x0: f.x0, x1: f.x1, pixeles: f.n });
        }
    }
    return zonas.sort((a, b) => b.pixeles - a.pixeles).slice(0, 8)
        .map((z) => `${z.pixeles}px en x${z.x0}-${z.x1} y${z.y0}-${z.y1}`);
}

function igualarTamano(png, ancho, alto) {
    if (png.width === ancho && png.height === alto) return png;
    const lienzo = new PNG({ width: ancho, height: alto });
    lienzo.data.fill(255);
    PNG.bitblt(png, lienzo, 0, 0, png.width, png.height, 0, 0);
    return lienzo;
}

const navegador = await chromium.launch({ channel: 'chrome' });
const resumen = [];

for (const variante of pantalla.variantes) {
    const dir = path.join(SALIDA, nombre, variante.id);
    fs.mkdirSync(dir, { recursive: true });

    for (const ancho of anchos) {
        const urlOriginal = PROTOTIPO_URL + '/' + encodeURI(pantalla.original);
        const urlLaravel = LARAVEL_URL + pantalla.laravel + (variante.query ? '?' + variante.query : '');
        const alto = pantalla.alto || 900;

        const original = await capturar(navegador, urlOriginal, ancho, alto, variante, true);
        const laravel = await capturar(navegador, urlLaravel, ancho, alto, variante, false);

        const w = Math.max(original.png.width, laravel.png.width);
        const h = Math.max(original.png.height, laravel.png.height);
        const a = igualarTamano(original.png, w, h);
        const b = igualarTamano(laravel.png, w, h);
        const diff = new PNG({ width: w, height: h });
        const distintos = pixelmatch(a.data, b.data, diff.data, w, h, { threshold: 0.1, includeAA: false });

        fs.writeFileSync(path.join(dir, ancho + '-original.png'), PNG.sync.write(a));
        fs.writeFileSync(path.join(dir, ancho + '-laravel.png'), PNG.sync.write(b));
        fs.writeFileSync(path.join(dir, ancho + '-diff.png'), PNG.sync.write(diff));

        const fila = {
            variante: variante.id,
            ancho,
            criterio: ANCHOS_ESTRICTOS.includes(ancho) ? 'estricto' : 'referencia',
            altoOriginal: original.png.height,
            altoLaravel: laravel.png.height,
            pixelesDistintos: distintos,
            porcentaje: Number((distintos / (w * h) * 100).toFixed(3)),
            zonas: distintos ? zonasDistintas(diff) : [],
            usabilidad: laravel.usabilidad,
        };
        resumen.push(fila);
        const u = fila.usabilidad;
        console.log(
            `${variante.id.padEnd(12)} ${String(ancho).padStart(4)}px ${fila.criterio.padEnd(10)} ` +
            `${String(fila.porcentaje).padStart(7)}%  alto ${fila.altoOriginal}/${fila.altoLaravel}` +
            (u ? `  desborde:${u.desbordeHorizontal ? 'SÍ' : 'no'} táctiles<44:${u.objetivosPequenos.length}` : '')
        );
        if (process.env.DETALLE) {
            for (const z of fila.zonas) console.log('      · ' + z);
            if (u) for (const t of u.objetivosPequenos) console.log(`      ⊘ ${t.elemento} "${t.texto}" ${t.tamano}`);
        }
    }
}

await navegador.close();
fs.writeFileSync(path.join(SALIDA, nombre, 'resumen.json'), JSON.stringify(resumen, null, 2));
