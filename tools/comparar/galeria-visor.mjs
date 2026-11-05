// Visor de la galería de la ficha pública (05/11). Bug: las miniaturas y «Ver las N fotos» no hacían nada (la galería
// del prototipo es decorativa). Comprueba, solo con clics, teclado y gestos, en una ficha con 14 fotos:
//   - llegan las 14 fotos al visor, no solo las 4 de la grilla;
//   - cada miniatura abre el visor en SU foto (próximo: la grilla parte en la foto 2; en vivo, en la 1);
//   - flechas y teclado avanzan y retroceden, el contador coincide con la foto y da la vuelta;
//   - Esc y el clic en el fondo cierran, la página vuelve a desplazarse y el foco vuelve a la miniatura;
//   - en móvil: deslizar cambia de foto, sin scroll horizontal y con áreas táctiles ≥ 44 px;
//   - ninguna violación de la CSP ni errores en la consola.
// Requiere `php artisan serve` (APP_ENV=local). Reinicia la base local.
//
//   node tools/comparar/galeria-visor.mjs
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';
import { revisarUsabilidad } from './usabilidad.mjs';

const URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const TOTAL = 14;
let fallas = 0;
const comprobar = (nombre, ok, detalle = '') => {
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre}${detalle ? '  (' + detalle + ')' : ''}`);
};
const ayudante = (...args) => execFileSync('php', ['tools/comparar/sala-ayudante.php', ...args], { encoding: 'utf8' }).trim();

console.log('Preparando la base local (migrate:fresh --seed) y 14 fotos en «militares» y «apoquindo»…');
ayudante('reiniciar');
ayudante('fotos', 'militares', String(TOTAL));
ayudante('fotos', 'apoquindo', String(TOTAL));

const navegador = await chromium.launch({ channel: 'chrome' });

async function abrirFicha(contexto, ruta) {
    const p = await contexto.newPage();
    const problemas = [];
    p.on('console', (m) => { if (m.type() === 'error' || /Refused to|Content Security Policy/i.test(m.text())) problemas.push(m.text()); });
    p.on('pageerror', (e) => problemas.push(e.message));
    p.on('response', (r) => { if (r.status() >= 400) problemas.push(`${r.status()} ${r.url()}`); });
    await p.addInitScript(() => {
        window.__csp = [];
        document.addEventListener('securitypolicyviolation', (e) => window.__csp.push(`${e.violatedDirective} ${e.blockedURI}`));
    });
    const r = await p.goto(URL + ruta, { waitUntil: 'networkidle' });
    comprobar(`${ruta}: responde 200 con CSP activa`, r.status() === 200 && !!r.headers()['content-security-policy'], `${r.status()}`);
    return { p, problemas };
}

const visor = (p) => p.getByRole('dialog');
// Alpine actualiza en la siguiente microtarea: se espera a que se oculte (máx. 2 s) antes de afirmar que cerró.
const cerro = async (p) => { await visor(p).waitFor({ state: 'hidden', timeout: 2000 }).catch(() => {}); return !(await visor(p).isVisible()); };
const contador = (p) => p.locator('.visor__contador').textContent();
const fotoN = (p) => p.locator('.visor__foto').evaluate((img) => Number(new URL(img.src).searchParams.get('n')));
const fotoCargada = (p) => p.locator('.visor__foto').evaluate((img) => img.complete && img.naturalWidth > 0);
const enFoto = async (p, n) => (await contador(p)) === `${n} / ${TOTAL}` && (await fotoN(p)) === n;

// ── Remate próximo en escritorio: la grilla muestra las fotos 2 a 5 ─────────────────────────────────────
{
    const c = await navegador.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-CL' });
    const { p, problemas } = await abrirFicha(c, '/remates/militares');
    const miniaturas = p.locator('.detalle__miniatura');

    comprobar('la grilla sigue mostrando 4 miniaturas (diseño 1:1)', (await miniaturas.count()) === 4);
    comprobar('«Ver las 14 fotos» visible', await p.getByText(`Ver las ${TOTAL} fotos`).isVisible());
    comprobar('miniaturas con cursor de mano', (await miniaturas.first().evaluate((e) => getComputedStyle(e).cursor)) === 'pointer');
    comprobar('visor cerrado al cargar', await cerro(p));

    for (let i = 0; i < 4; i++) {
        const esperada = i + 2;
        await miniaturas.nth(i).click();
        await visor(p).waitFor({ state: 'visible' });
        comprobar(`miniatura ${i + 1} abre el visor en la foto ${esperada}`, await enFoto(p, esperada), `${await contador(p)}, foto ?n=${await fotoN(p)}`);
        await p.keyboard.press('Escape');
        await visor(p).waitFor({ state: 'hidden' });
    }

    // «Ver las 14 fotos» es la última miniatura: se busca por su texto visible, como lo haría una persona.
    await p.getByRole('button', { name: `Ver las ${TOTAL} fotos` }).click();
    await visor(p).waitFor({ state: 'visible' });
    comprobar('«Ver las 14 fotos» abre el visor (en la foto 5)', await enFoto(p, 5), await contador(p));
    // 05/11: la entrada del prototipo (`.sc-host > div > div`) lo dejaba translúcido y desplazado medio segundo al abrir.
    const aspecto = await p.locator('.visor').evaluate((v) => { const s = getComputedStyle(v); return [s.opacity, s.animationName, s.transform, s.backgroundColor]; });
    comprobar('el visor aparece opaco y quieto (sin la animación de entrada)', aspecto.join() === '1,none,none,rgb(22, 38, 90)', aspecto.join(' · '));
    comprobar('la página no se desplaza detrás del visor', (await p.evaluate(() => document.documentElement.style.overflow)) === 'hidden');
    comprobar('el foco queda en «Cerrar las fotos»', (await p.evaluate(() => document.activeElement?.getAttribute('aria-label'))) === 'Cerrar las fotos');

    await p.getByRole('button', { name: 'Foto siguiente' }).click();
    comprobar('flecha «siguiente» → 6 / 14', await enFoto(p, 6), await contador(p));
    await p.getByRole('button', { name: 'Foto anterior' }).click();
    await p.getByRole('button', { name: 'Foto anterior' }).click();
    comprobar('flecha «anterior» dos veces → 4 / 14', await enFoto(p, 4), await contador(p));
    await p.keyboard.press('ArrowRight');
    comprobar('tecla → avanza a 5 / 14', await enFoto(p, 5), await contador(p));
    await p.keyboard.press('ArrowLeft');
    comprobar('tecla ← retrocede a 4 / 14', await enFoto(p, 4), await contador(p));

    // Recorrido completo con el teclado: las 14 fotos llegan, son distintas, cargan y el contador coincide siempre.
    const vistas = new Set();
    let coinciden = true, cargadas = true;
    for (let k = 0; k < TOTAL; k++) {
        const n = await fotoN(p);
        vistas.add(n);
        await p.locator('.visor__foto').evaluate((img) => img.decode().catch(() => {}));
        coinciden &&= (await contador(p)) === `${n} / ${TOTAL}`;
        cargadas &&= await fotoCargada(p);
        await p.keyboard.press('ArrowRight');
    }
    comprobar('llegan las 14 fotos al visor, todas distintas', vistas.size === TOTAL, `${vistas.size} distintas`);
    comprobar('el contador coincide con la foto en las 14', coinciden);
    comprobar('las 14 fotos cargan (no hay imágenes rotas)', cargadas);
    comprobar('tras 14 avances vuelve a la misma foto (da la vuelta)', await enFoto(p, 4), await contador(p));
    await p.getByRole('button', { name: 'Foto siguiente' }).click(); // 5
    for (let k = 0; k < 10; k++) await p.keyboard.press('ArrowRight'); // 15 → 1
    comprobar('pasada la 14 sigue la 1', await enFoto(p, 1), await contador(p));
    await p.keyboard.press('ArrowLeft');
    comprobar('antes de la 1 está la 14', await enFoto(p, TOTAL), await contador(p));

    await p.keyboard.press('Escape');
    await visor(p).waitFor({ state: 'hidden' });
    comprobar('Esc cierra el visor', await cerro(p));
    comprobar('al cerrar, la página vuelve a desplazarse', (await p.evaluate(() => document.documentElement.style.overflow)) === '');
    comprobar('al cerrar, el foco vuelve a «Ver las 14 fotos»', (await p.evaluate(() => document.activeElement?.getAttribute('aria-label'))) === `Ver las ${TOTAL} fotos`);

    await miniaturas.nth(1).click();
    await visor(p).waitFor({ state: 'visible' });
    await p.mouse.click(30, 150); // fondo: bajo la barra y sobre la flecha «anterior» (que está al 50 % de la altura)
    await visor(p).waitFor({ state: 'hidden', timeout: 2000 }).catch(() => {});
    comprobar('clic en el fondo cierra el visor', await cerro(p));
    await miniaturas.nth(1).click();
    await visor(p).waitFor({ state: 'visible' });
    await p.getByRole('button', { name: 'Cerrar las fotos' }).click();
    comprobar('el botón «Cerrar las fotos» cierra', await cerro(p));

    const csp = await p.evaluate(() => window.__csp);
    comprobar('sin violaciones de la CSP', csp.length === 0, csp.join(' | '));
    comprobar('sin errores en la consola', problemas.length === 0, problemas.join(' | '));
    await c.close();
}

// ── Remate en vivo: la foto principal es el video, la grilla parte en la foto 1 ─────────────────────────
{
    const c = await navegador.newContext({ viewport: { width: 1280, height: 900 }, locale: 'es-CL' });
    const { p, problemas } = await abrirFicha(c, '/remates/apoquindo');
    const miniaturas = p.locator('.detalle__miniatura');
    await miniaturas.nth(0).click();
    await visor(p).waitFor({ state: 'visible' });
    comprobar('en vivo: miniatura 1 abre la foto 1', await enFoto(p, 1), await contador(p));
    await p.keyboard.press('Escape');
    await miniaturas.nth(3).click();
    comprobar('en vivo: «Ver las 14 fotos» abre la foto 4', await enFoto(p, 4), await contador(p));
    await p.keyboard.press('Escape');
    comprobar('en vivo: sin errores ni CSP', problemas.length === 0 && (await p.evaluate(() => window.__csp)).length === 0, problemas.join(' | '));
    await c.close();
}

// ── Móvil (375 px, táctil): deslizar, sin desborde, áreas táctiles ─────────────────────────────────────
{
    const c = await navegador.newContext({ viewport: { width: 375, height: 812 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2, locale: 'es-CL' });
    const { p, problemas } = await abrirFicha(c, '/remates/militares');
    const cdp = await c.newCDPSession(p);
    const deslizar = async (desde, hasta, y = 380) => {
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [{ x: desde, y }] });
        for (let k = 1; k <= 6; k++) await cdp.send('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ x: desde + ((hasta - desde) * k) / 6, y }] });
        await cdp.send('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        await p.waitForTimeout(150);
    };

    let u = await revisarUsabilidad(p, 375);
    comprobar('móvil: ficha sin scroll horizontal', !u.desborde);
    const cajas = await p.locator('.detalle__miniatura').evaluateAll((els) => els.map((e) => [Math.round(e.getBoundingClientRect().width), Math.round(e.getBoundingClientRect().height)]));
    comprobar('móvil: miniaturas de al menos 44 px', cajas.every(([w, h]) => w >= 44 && h >= 44), JSON.stringify(cajas));
    comprobar('móvil: ningún control de la ficha bajo 44 px', u.pequenos.length === 0, u.pequenos.join(', '));

    await p.locator('.detalle__miniatura').nth(0).tap();
    await visor(p).waitFor({ state: 'visible' });
    comprobar('móvil: el toque en la miniatura 1 abre la foto 2', await enFoto(p, 2), await contador(p));
    await deslizar(300, 80);
    comprobar('móvil: deslizar a la izquierda avanza a 3 / 14', await enFoto(p, 3), await contador(p));
    await deslizar(80, 300);
    comprobar('móvil: deslizar a la derecha retrocede a 2 / 14', await enFoto(p, 2), await contador(p));
    await deslizar(200, 185);
    comprobar('móvil: un roce corto no cambia de foto', await enFoto(p, 2), await contador(p));

    u = await revisarUsabilidad(p, 375);
    comprobar('móvil: con el visor abierto, sin scroll horizontal', !u.desborde);
    const botones = await p.locator('.visor__boton').evaluateAll((els) => els.map((e) => { const r = e.getBoundingClientRect(); return [e.getAttribute('aria-label'), Math.round(r.width), Math.round(r.height), r.left >= 0 && r.right <= innerWidth && r.bottom <= innerHeight]; }));
    comprobar('móvil: botones del visor ≥ 44 px y dentro de la pantalla', botones.every(([, w, h, dentro]) => w >= 44 && h >= 44 && dentro), JSON.stringify(botones));
    const foto = await p.locator('.visor__foto').boundingBox();
    comprobar('móvil: la foto cabe en la pantalla', foto && foto.x >= 0 && foto.x + foto.width <= 375.5, JSON.stringify(foto));
    await p.getByRole('button', { name: 'Foto siguiente' }).tap();
    comprobar('móvil: el botón «siguiente» avanza a 3 / 14', await enFoto(p, 3), await contador(p));
    await p.screenshot({ path: 'tools/comparar/salida/galeria-visor-375.png' });
    await p.getByRole('button', { name: 'Cerrar las fotos' }).tap();
    comprobar('móvil: «Cerrar las fotos» cierra', await cerro(p));
    comprobar('móvil: sin errores ni CSP', problemas.length === 0 && (await p.evaluate(() => window.__csp)).length === 0, problemas.join(' | '));
    await c.close();
}

// ── Inglés: mismos controles con sus textos ────────────────────────────────────────────────────────────
{
    const c = await navegador.newContext({ viewport: { width: 1440, height: 900 }, locale: 'en-US' });
    const { p } = await abrirFicha(c, '/en/remates/militares');
    await p.getByRole('button', { name: `View all ${TOTAL} photos` }).click();
    await visor(p).waitFor({ state: 'visible' });
    await p.getByRole('button', { name: 'Next photo' }).click();
    comprobar('inglés: «View all 14 photos» y «Next photo» funcionan', await enFoto(p, 6), await contador(p));
    await p.getByRole('button', { name: 'Close photos' }).click();
    comprobar('inglés: «Close photos» cierra', await cerro(p));
    await c.close();
}

await navegador.close();
console.log(fallas ? `\n${fallas} FALLA(S)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
