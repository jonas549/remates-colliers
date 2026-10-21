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

// ── 4.3 Reportes: promedio de pujas con un decimal ──────────────────────────────────────────────────────
console.log('\n— 4.3 Reportes');
await A.goto(URL + '/admin/reportes', { waitUntil: 'load' });
const notaPujas = await A.locator('.admin-reportes__kpi', { hasText: 'PUJAS TOTALES' }).locator('.admin-reportes__kpi-nota').innerText().catch(() => '');
comprobar('«Promedio de N por …» con un decimal como máximo', /^(Promedio de \d+(,\d)? por (lote|remate)|Menos de 0,1 por (lote|remate))$/.test(notaPujas), notaPujas);

// ── 4.4 Error 405 con página propia ──────────────────────────────────────────────────────────────────────
console.log('\n— 4.4 Error 405');
const r405 = await A.goto(URL + '/admin/usuarios/crear', { waitUntil: 'load' });
comprobar('GET a una ruta POST: 405 con la página del sitio, en español', r405.status() === 405
    && (await A.locator('h1').innerText()) === 'Esta página no se abre directamente', `${r405.status()}`);
await A.getByRole('link', { name: 'Volver al panel' }).click();
await A.waitForLoadState('load');
comprobar('«Volver al panel» lleva al dashboard', new globalThis.URL(A.url()).pathname === '/admin');
await A.goto(URL + '/admin/usuarios/crear', { waitUntil: 'load' });
await A.waitForTimeout(800);
await A.screenshot({ path: `${SALIDA}/error-405-1440.png` });

// ── 4.5 Buscador de Postores sin cortar ──────────────────────────────────────────────────────────────────
console.log('\n— 4.5 Buscador de Postores');
for (const ancho of [1120, 1280, 1366, 1440, 760, 375]) {
    await A.setViewportSize({ width: ancho, height: 900 });
    await A.goto(URL + '/admin/postores', { waitUntil: 'load' });
    const medida = await A.locator('.admin-buscador input').evaluate((i) => {
        const ctx = document.createElement('canvas').getContext('2d');
        ctx.font = getComputedStyle(i).font;
        return { texto: Math.ceil(ctx.measureText(i.placeholder).width), caja: i.clientWidth };
    });
    comprobar(`${ancho}: el placeholder cabe entero`, medida.texto <= medida.caja, `${medida.texto} de ${medida.caja} px`);
}
await A.setViewportSize({ width: 1440, height: 900 });

// ── 4.1 Hover en «Ocultar filtros», «Grilla» y «Tabla» ───────────────────────────────────────────────────
console.log('\n— 4.1 Hover en el listado');
await V.goto(URL + '/', { waitUntil: 'load' });
for (const [nombre, boton] of [['Ocultar filtros', V.locator('.listado__btn-filtros')], ['Grilla', V.locator('.listado__vista button').nth(0)], ['Tabla', V.locator('.listado__vista button').nth(1)]]) {
    await V.mouse.move(0, 0);
    await V.waitForTimeout(250);
    const antes = await boton.evaluate((b) => getComputedStyle(b).backgroundColor);
    await boton.hover();
    await V.waitForTimeout(250);
    const durante = await boton.evaluate((b) => getComputedStyle(b).backgroundColor);
    comprobar(`«${nombre}» cambia al pasar el mouse`, antes !== durante, `${antes} → ${durante}`);
}

// ── 4.2 Sala: porcentaje sobre la base con un decimal ────────────────────────────────────────────────────
console.log('\n— 4.2 Sala: sobre la base');
const cP = await navegador.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-CL', timezoneId: 'America/Santiago' });
const P = await cP.newPage();
await entrar(P, 'postor', '/remates/apoquindo/sala', 'mpgonzalez@correo.test');
const linea = P.locator('.sala-panel__base');
const esperarTexto = async (re) => { for (let i = 0; i < 25 && !re.test(await linea.innerText()); i++) await P.waitForTimeout(200); return linea.innerText(); };
// Seeder: base $185.000.000 y precio $198.500.000 → 7,297 %.
let base = await esperarTexto(/\+7,3% sobre la base$/);
comprobar('«+7,3% sobre la base» (antes «+7% sobre el mínimo»)', /^Base \$185\.000\.000 · \+7,3% sobre la base$/.test(base), base);
await P.goto(URL + '/en/remates/apoquindo/sala', { waitUntil: 'load' });
base = await esperarTexto(/\+7\.3% above the starting price$/);
comprobar('en inglés: «+7.3% above the starting price»', /\+7\.3% above the starting price$/.test(base), base);

await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
