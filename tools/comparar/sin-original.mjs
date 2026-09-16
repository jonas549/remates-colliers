// Pantallas sin original en el prototipo (Bloque D: reutilizan el diseño del Login, decisión del 16/09).
// No hay contra qué comparar píxeles: verifica usabilidad (sin scroll horizontal, áreas táctiles ≥ 44 px en < 1120)
// y deja capturas en tools/comparar/salida/sin-original/ para revisarlas a ojo.
//
// Uso: node tools/comparar/sin-original.mjs      (requiere php artisan serve y la base local con migrate:fresh --seed)
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const LARAVEL_URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const SALIDA = path.resolve('tools/comparar/salida/sin-original');
const ANCHOS = [375, 760, 1120, 1440];

// [nombre, ruta, rol con el que entra (o null)]
const PANTALLAS = [
    ['admin-ingresar', '/admin/ingresar', null],
    ['recuperar-clave', '/recuperar-clave', null],
    ['restablecer-clave', '/restablecer-clave/token-de-prueba?email=ana@correo.test', null],
    ['cambiar-clave', '/mi-cuenta/cambiar-clave', 'postor'],
    ['sesiones', '/mi-cuenta/sesiones', 'postor'],
    ['confirmar-clave', '/confirmar-clave', 'postor'],
];

fs.mkdirSync(SALIDA, { recursive: true });
const navegador = await chromium.launch({ channel: 'chrome' });
let fallas = 0;

for (const [nombre, ruta, rol] of PANTALLAS) {
    for (const ancho of ANCHOS) {
        const contexto = await navegador.newContext({ viewport: { width: ancho, height: 900 }, deviceScaleFactor: 1, locale: 'es-CL' });
        const pagina = await contexto.newPage();
        if (rol) {
            await pagina.goto(LARAVEL_URL + '/revision/entrar/' + rol, { waitUntil: 'load' });
        }
        const respuesta = await pagina.goto(LARAVEL_URL + ruta, { waitUntil: 'load' });
        await pagina.evaluate(() => document.fonts.ready);
        await pagina.waitForTimeout(300);

        const r = await pagina.evaluate((ancho) => {
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
                        pequenos.push(`${el.tagName.toLowerCase()} "${(el.textContent || el.placeholder || '').trim().slice(0, 30)}" ${Math.round(objetivo.width)}x${Math.round(objetivo.height)}`);
                    }
                }
            }
            return { desborde: doc.scrollWidth > doc.clientWidth, pequenos, titulo: document.querySelector('h1')?.textContent.trim() };
        }, ancho);

        await pagina.screenshot({ path: path.join(SALIDA, `${nombre}-${ancho}.png`), fullPage: true });
        const ok = respuesta.status() === 200 && !r.desborde && r.pequenos.length === 0;
        fallas += ok ? 0 : 1;
        console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre.padEnd(18)} ${String(ancho).padStart(4)}px  HTTP ${respuesta.status()}  desborde:${r.desborde ? 'SÍ' : 'no'}  táctiles<44:${r.pequenos.length}  «${r.titulo}»`);
        for (const p of r.pequenos) console.log('      ⊘ ' + p);
        await contexto.close();
    }
}

await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
