// Bloque N en navegador real: listado y detalle con datos reales, «Avísame», calendario, documentos y el detalle en vivo
// que se actualiza solo leyendo el JSON estático. Requiere `php artisan serve` (APP_ENV=local). Reinicia la base local.
//
//   node tools/comparar/sitio-publico.mjs
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';
import { revisarUsabilidad } from './usabilidad.mjs';

const URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const SALIDA = path.resolve('tools/comparar/salida/sitio-publico');
let fallas = 0;
const comprobar = (nombre, ok, detalle = '') => {
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre}${detalle ? '  (' + detalle + ')' : ''}`);
};
const ayudante = (...args) => execFileSync('php', ['tools/comparar/sala-ayudante.php', ...args], { encoding: 'utf8' }).trim();

console.log('Preparando la base local (migrate:fresh --seed)…');
ayudante('reiniciar');
fs.mkdirSync(SALIDA, { recursive: true });
const navegador = await chromium.launch({ channel: 'chrome' });

// ── Visitante en escritorio ────────────────────────────────────────────────────────────────────────────
{
    const c = await navegador.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-CL', acceptDownloads: true });
    const p = await c.newPage();
    const peticiones = [];
    p.on('request', (r) => peticiones.push(r.url()));
    await p.goto(URL + '/', { waitUntil: 'load' });

    comprobar('listado: tarjetas con fotos reales', (await p.locator('.tarjeta').count()) === 6 && (await p.locator('.tarjeta__foto img').first().getAttribute('src')).includes('/img/demo/prop-'));
    comprobar('listado: UF de Configuración', (await p.locator('.listado__uf-valor').innerText()) === '$39.412,73');
    comprobar('listado: hero con el próximo remate real', (await p.locator('.listado__hero-titulo').innerText()).length > 5);

    await p.fill('.listado__aviso-form input[name=email]', 'curioso@correo.test');
    await p.getByRole('button', { name: 'Suscribirme' }).click();
    await p.waitForSelector('.listado__aviso-resultado:visible');
    comprobar('«Suscribirme» registra el correo', (await p.locator('.listado__aviso-resultado').innerText()).includes('te avisaremos'));

    await p.locator('.tarjeta', { hasText: 'Los Militares 5620' }).getByRole('link', { name: 'Los Militares 5620, Depto. 703' }).click();
    await p.waitForURL('**/remates/militares');
    comprobar('detalle próximo: ficha y mandante reales', (await p.locator('.detalle__ficha').innerText()).includes('AÑO DE CONSTRUCCIÓN')
        && (await p.locator('.detalle__tabla-kv').last().innerText()).includes('Banco Consorcio'));
    comprobar('detalle próximo: mapa cargado', (await p.locator('.detalle__mapa .leaflet-tile').count()) > 0 || (await p.locator('.detalle__mapa.leaflet-container').count()) === 1);
    const [descarga] = await Promise.all([p.waitForEvent('download'), p.getByRole('link', { name: 'Agregar a mi calendario' }).click()]);
    comprobar('calendario .ics descargable', descarga.suggestedFilename() === 'remate-R-2026-118.ics');
    await p.getByRole('link', { name: 'Avísame antes de que comience' }).click();
    await p.fill('.detalle-avisame input', 'curioso@correo.test');
    await p.getByRole('button', { name: 'Avisarme' }).click();
    await p.waitForSelector('.detalle-avisame__mensaje:visible');
    comprobar('«Avísame antes de que comience» sin sesión pide el correo y confirma', (await p.locator('.detalle-avisame__mensaje').innerText()).includes('antes de que comience'));
    await p.screenshot({ path: path.join(SALIDA, 'detalle-proximo-1440.png'), fullPage: true });

    // En vivo: el espectador se actualiza sin ejecutar el framework.
    peticiones.length = 0;
    await p.goto(URL + '/remates/apoquindo', { waitUntil: 'load' });
    comprobar('detalle en vivo: precio real del seeder', (await p.locator('.detalle-remate__precio').innerText()) === '$198.500.000');
    ayudante('pujar', 'apoquindo', 'pfuentes@correo.test', '198600000');
    await p.waitForFunction(() => document.querySelector('.detalle-remate__precio')?.textContent.trim() === '$198.600.000', null, { timeout: 8000 }).catch(() => {});
    comprobar('detalle en vivo: la puja nueva aparece sola', (await p.locator('.detalle-remate__precio').innerText()) === '$198.600.000'
        && (await p.locator('.historial-puja__postor').first().innerText()) === 'Postor #2');
    const conPhp = peticiones.filter((u) => u.startsWith(URL) && !/\/(tiempo-real\/.*\.json|hora\.php|build\/|img\/|storage\/|favicon)/.test(u) && !u.endsWith('/remates/apoquindo'));
    comprobar('el espectador no llama a rutas con framework (solo JSON estático y hora.php)', conPhp.length === 0, conPhp.slice(0, 3).join(', '));
    await c.close();
}

// ── Postor con cuenta aprobada: inscribirse desde el detalle ───────────────────────────────────────────
{
    const c = await navegador.newContext({ viewport: { width: 375, height: 812 }, locale: 'es-CL' });
    const p = await c.newPage();
    await p.goto(`${URL}/revision/entrar/postor?usuario=${encodeURIComponent('pfuentes@correo.test')}&a=/remates/montt`, { waitUntil: 'load' });
    comprobar('postor sin garantía en ese remate: «Falta constituir la garantía»', (await p.locator('.detalle-bloqueo__titulo').innerText()) === 'Falta constituir la garantía');
    await p.getByRole('button', { name: 'Constituir la garantía' }).click();
    await p.waitForURL('**/mi-cuenta?remate=montt');
    comprobar('inscripción desde el detalle lleva a «Mi cuenta» con la garantía', (await p.locator('.cuenta__aviso').innerText()).includes('Quedaste inscrito en R-2026-121'));
    await c.close();
}

// ── Usabilidad en todos los anchos ─────────────────────────────────────────────────────────────────────
for (const ancho of [375, 760, 1120, 1440]) {
    const c = await navegador.newContext({ viewport: { width: ancho, height: 900 }, locale: 'es-CL' });
    const p = await c.newPage();
    for (const [nombre, ruta] of [['listado', '/'], ['detalle-proximo', '/remates/militares'], ['detalle-vivo', '/remates/apoquindo'], ['detalle-cerrado', '/remates/sanmartin'], ['login', '/ingresar']]) {
        const r = await p.goto(URL + ruta, { waitUntil: 'load' });
        await p.waitForTimeout(300);
        const u = await revisarUsabilidad(p, ancho);
        comprobar(`${nombre} a ${ancho}px: HTTP 200, sin desborde, táctiles ≥ 44`, r.status() === 200 && !u.desborde && u.pequenos.length === 0, u.pequenos.slice(0, 4).join('; '));
        if (ancho === 375 || ancho === 1440) await p.screenshot({ path: path.join(SALIDA, `${nombre}-${ancho}.png`), fullPage: true });
    }
    await c.close();
}

await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
