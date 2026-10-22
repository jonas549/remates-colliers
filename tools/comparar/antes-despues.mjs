// Capturas para revisar a ojo un cambio de diseño (22/10): cabecera, menú móvil, pie del sitio, pie de trámite (404 y
// registro) y la ficha de un remate. Se corre antes y después del cambio y se comparan las carpetas.
//
// Requiere `php artisan serve` con la base del seeder. No escribe en la base.
//   node tools/comparar/antes-despues.mjs antes
//   node tools/comparar/antes-despues.mjs despues
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';

const URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const etiqueta = process.argv[2] || 'antes';
const SALIDA = path.resolve('tools/comparar/salida/antes-despues', etiqueta);
fs.mkdirSync(SALIDA, { recursive: true });

const navegador = await chromium.launch({ channel: 'chrome' });
const foto = async (ancho, ruta, nombre, preparar = null, selector = null) => {
    const c = await navegador.newContext({ viewport: { width: ancho, height: 900 }, locale: 'es-CL', timezoneId: 'America/Santiago' });
    const p = await c.newPage();
    await p.goto(URL + ruta, { waitUntil: 'load' });
    await p.evaluate(() => document.fonts.ready);
    if (preparar) await preparar(p);
    const archivo = path.join(SALIDA, `${nombre}-${ancho}.png`);
    // El pie y las secciones aparecen con una transición al entrar en pantalla: se espera a que termine.
    if (selector) {
        await p.locator(selector).first().scrollIntoViewIfNeeded();
        await p.waitForTimeout(1200);
        await p.locator(selector).first().screenshot({ path: archivo });
    }
    else {
        await p.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
        await p.waitForTimeout(1200);
        await p.evaluate(() => window.scrollTo(0, 0));
        await p.screenshot({ path: archivo, fullPage: true });
    }
    console.log('foto', path.relative(process.cwd(), archivo));
    await c.close();
};

for (const ancho of [1440, 375]) {
    await foto(ancho, '/', 'cabecera', null, '.pub-cabecera');
    await foto(ancho, '/', 'pie', null, '.pub-pie');
    await foto(ancho, '/no-existe-esta-pagina', 'error-404');
    await foto(ancho, '/registro', 'pie-registro', null, '.tramite-pie');
}
await foto(375, '/', 'menu-movil', async (p) => {
    await p.locator('.pub-cabecera__menu-boton').click();
    await p.waitForSelector('.pub-menu');
}, '.pub-cabecera');
// Ficha de remate: la del seeder con horarios de visita y una sin horarios (punto 3).
for (const [slug, nombre] of [['apoquindo', 'ficha-en-vivo'], ...(process.env.FICHAS || '').split(',').filter(Boolean).map((s) => [s, 'ficha-' + s])]) {
    for (const ancho of [1440, 375]) await foto(ancho, '/remates/' + slug, nombre);
}

await navegador.close();
