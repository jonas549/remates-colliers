// QA 21/10, grupos 3 y 4: lo visible y lo menor, en navegador real.
// Requiere `php artisan serve` (APP_ENV=local). REINICIA la base local.
//
//   node tools/comparar/qa-visibles.mjs
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { chromium } from 'playwright';

const URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const SALIDA = 'tools/comparar/salida/qa-visibles';
mkdirSync(SALIDA, { recursive: true });
let fallas = 0;

function comprobar(nombre, ok, detalle = '') {
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre}${detalle ? '  (' + detalle + ')' : ''}`);
}
const ayudante = (...args) => execFileSync('php', ['tools/comparar/sala-ayudante.php', ...args], { encoding: 'utf8' });
const entrar = (p, rol, destino, usuario = '') =>
    p.goto(`${URL}/revision/entrar/${rol}?${usuario ? 'usuario=' + encodeURIComponent(usuario) + '&' : ''}a=${encodeURIComponent(destino)}`, { waitUntil: 'load' });
const sinScrollHorizontal = (p) => p.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);
const meta = (p, selector) => p.locator(selector).first().getAttribute('content');

console.log('Preparando la base local (migrate:fresh --seed)…');
ayudante('reiniciar');
const navegador = await chromium.launch({ channel: 'chrome' });

// ── 3.1 Sala sin transmisión ─────────────────────────────────────────────────────────────────────────────
console.log('\n— 3.1 Sala sin transmisión');
ayudante('video', 'apoquindo');
for (const ancho of [1440, 375]) {
    const c = await navegador.newContext({ viewport: { width: ancho, height: 900 }, locale: 'es-CL', timezoneId: 'America/Santiago' });
    const p = await c.newPage();
    await entrar(p, 'postor', '/remates/apoquindo/sala', 'mpgonzalez@correo.test');
    const aviso = p.locator('[data-sin-video]');
    await aviso.waitFor({ timeout: 5000 }).catch(() => {});
    const texto = (await aviso.count()) ? await aviso.innerText() : '';
    comprobar(`${ancho}: el reproductor dice que la transmisión aún no comienza`, texto.includes('La transmisión aún no comienza'), texto.replace(/\s+/g, ' ').slice(0, 80));
    comprobar(`${ancho}: avisa que se puede pujar igual con el precio y el cronómetro del panel`, texto.includes('Puedes pujar igual'));
    comprobar(`${ancho}: sin iframe de YouTube`, (await p.locator('.sala__video iframe').count()) === 0);
    const canal = p.locator('.sala__sin-video-canal');
    comprobar(`${ancho}: enlace al canal de Colliers`, (await canal.getAttribute('href')) === 'https://www.youtube.com/@collierscl');
    const caja = await canal.boundingBox();
    comprobar(`${ancho}: el enlace al canal mide al menos 44 px de alto y cabe en el reproductor`, caja && caja.height >= 44
        && (await p.locator('.sala__video').evaluate((v, b) => { const r = v.getBoundingClientRect(); return b.y + b.height <= r.bottom + 1; }, caja)));
    comprobar(`${ancho}: sin scroll horizontal`, await sinScrollHorizontal(p));
    await p.waitForTimeout(800);
    await p.screenshot({ path: `${SALIDA}/sala-sin-video-${ancho}.png` });
    await c.close();
}
ayudante('video', 'apoquindo', 'jfKfPfyJRdk');

// ── 3.2 Ficha en inglés: condiciones del remate ──────────────────────────────────────────────────────────
console.log('\n— 3.2 Condiciones del remate en inglés');
const cV = await navegador.newContext({ viewport: { width: 1440, height: 900 }, locale: 'en-US', timezoneId: 'America/Santiago' });
const V = await cV.newPage();
await V.goto(URL + '/en/remates/apoquindo', { waitUntil: 'load' });
const condiciones = await V.locator('.detalle__condiciones p').allInnerTexts();
comprobar('tres párrafos y el tercero en inglés', condiciones.length === 3 && condiciones[2].startsWith('The bid deposit is paid by bank draft'), condiciones[2]);
comprobar('ningún párrafo en español', !condiciones.some((t) => /garantía|remate|posturas/i.test(t)));

// ── 3.4 SEO y vista previa al compartir ──────────────────────────────────────────────────────────────────
console.log('\n— 3.4 SEO');
await V.goto(URL + '/remates/apoquindo', { waitUntil: 'load' });
const imagen = await meta(V, 'meta[property="og:image"]');
comprobar('ficha: og:image es la foto principal del lote y carga', /\/img\/demo\/prop-apoquindo\.jpg$/.test(imagen)
    && (await V.request.get(imagen)).ok(), imagen);
comprobar('ficha: description del remate', (await meta(V, 'meta[name="description"]')).startsWith('Remate en vivo de Av. Apoquindo 4501'));
comprobar('ficha: og:title, og:description y og:url', (await meta(V, 'meta[property="og:title"]')) === 'Av. Apoquindo 4501, Depto. 1802 · Remates Colliers'
    && (await meta(V, 'meta[property="og:description"]')) === (await meta(V, 'meta[name="description"]'))
    && (await meta(V, 'meta[property="og:url"]')) === URL + '/remates/apoquindo');
await V.goto(URL + '/?orden=fecha', { waitUntil: 'load' });
comprobar('listado: valores por defecto (descripción del sitio, logo, url sin filtros)',
    (await meta(V, 'meta[name="description"]')).startsWith('Remates de propiedades en línea de Colliers Chile')
    && (await meta(V, 'meta[property="og:image"]')).endsWith('/img/colliers-logo.png')
    && [URL, URL + '/'].includes(await meta(V, 'meta[property="og:url"]')));

// ── 3.3 Dashboard y Postores con el mismo criterio ───────────────────────────────────────────────────────
console.log('\n— 3.3 Cuentas por aprobar');
const cA = await navegador.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-CL', timezoneId: 'America/Santiago' });
const A = await cA.newPage();
await entrar(A, 'admin', '/admin');
const kpi = A.locator('.admin-kpi', { hasText: 'CUENTAS POR APROBAR' });
const valor = Number(await kpi.locator('.admin-kpi__valor').innerText());
const nota = await kpi.locator('.admin-kpi__nota').innerText();
const sinCorreo = Number((nota.match(/^(\d+) más/) || [0, 0])[1]);
await A.screenshot({ path: `${SALIDA}/dashboard-1440.png` });
await A.goto(URL + '/admin/postores', { waitUntil: 'load' });
const pestana = Number(await A.locator('.admin-pestana', { hasText: 'CUENTAS POR APROBAR' }).locator('.admin-pestana__cuenta').innerText());
comprobar('el número del dashboard más «N más esperan el correo» coincide con la pestaña de Postores', valor + sinCorreo === pestana,
    `dashboard ${valor} · nota «${nota}» · pestaña ${pestana}`);

await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
