// QA 21/10, grupo 2: cabeceras de seguridad y CSP en navegador real. Lo que la política no puede romper:
// Alpine en todas las pantallas, la sala (estado, latido de presencia, video de YouTube), el mapa de la ficha y los
// confirm() del panel, que eran manejadores en línea. Cualquier bloqueo de la CSP en cualquier página es una falla.
// Requiere `php artisan serve` (APP_ENV=local) e internet (YouTube y mosaicos del mapa). REINICIA la base local.
//
//   node tools/comparar/seguridad.mjs
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
let fallas = 0;
const bloqueos = [];

function comprobar(nombre, ok, detalle = '') {
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre}${detalle ? '  (' + detalle + ')' : ''}`);
}

async function esperar(nombre, funcion, ms = 10000) {
    const inicio = Date.now();
    let ultimo;
    while (Date.now() - inicio < ms) {
        try {
            ultimo = await funcion();
            if (ultimo === true) return comprobar(nombre, true, `${Date.now() - inicio} ms`);
        } catch (e) { ultimo = e.message; }
        await new Promise((r) => setTimeout(r, 200));
    }
    comprobar(nombre, false, 'último valor: ' + JSON.stringify(ultimo));
}

// Cada contexto anota los bloqueos de la CSP (evento del documento y mensaje de la consola).
async function contexto(navegador, ancho = 1440) {
    const c = await navegador.newContext({ viewport: { width: ancho, height: 900 }, locale: 'es-CL', timezoneId: 'America/Santiago' });
    await c.addInitScript(() => document.addEventListener('securitypolicyviolation', (e) =>
        console.error(`CSP-BLOQUEO ${e.effectiveDirective} ${e.blockedURI} en ${location.pathname}`)));
    c.on('page', (p) => p.on('console', (m) => {
        const t = m.text();
        if (t.startsWith('CSP-BLOQUEO') || t.includes('Content Security Policy')) bloqueos.push(t);
    }));
    return c;
}
const entrar = (p, rol, destino, usuario = '') =>
    p.goto(`${URL}/revision/entrar/${rol}?${usuario ? 'usuario=' + encodeURIComponent(usuario) + '&' : ''}a=${encodeURIComponent(destino)}`, { waitUntil: 'load' });

console.log('Preparando la base local (migrate:fresh --seed)…');
execFileSync('php', ['tools/comparar/sala-ayudante.php', 'reiniciar'], { encoding: 'utf8' });
const navegador = await chromium.launch({ channel: 'chrome' });

// ── Visitante: cabeceras, listado, ficha con video y mapa, registro ──────────────────────────────────────
console.log('\n— Visitante');
const cV = await contexto(navegador);
const V = await cV.newPage();
const doc = await V.goto(URL + '/', { waitUntil: 'load' });
const h = doc.headers();
comprobar('cabeceras: nosniff, SAMEORIGIN, Referrer-Policy y Permissions-Policy',
    h['x-content-type-options'] === 'nosniff' && h['x-frame-options'] === 'SAMEORIGIN'
    && h['referrer-policy'] === 'strict-origin-when-cross-origin' && (h['permissions-policy'] || '').includes('camera=()'));
// Control: un script en línea tiene que quedar bloqueado y detectado (si no, el «sin bloqueos» del final no prueba nada).
await V.evaluate(() => { const e = document.createElement('script'); e.textContent = 'window.__enLinea = 1'; document.body.append(e); });
await esperar('control: un script en línea queda bloqueado y se detecta', async () => bloqueos.length > 0
    && (await V.evaluate(() => window.__enLinea === undefined)), 5000);
bloqueos.length = 0;
comprobar('CSP activa con frame-src de YouTube', (h['content-security-policy'] || '').includes('frame-src https://www.youtube-nocookie.com https://www.youtube.com'));
await V.getByRole('button', { name: 'Ocultar filtros' }).click();
await esperar('listado: Alpine funciona («Ocultar filtros» pasa a «Filtros»)', async () => (await V.getByRole('button', { name: 'Filtros', exact: true }).count()) === 1, 5000);
await V.getByRole('link', { name: /Apoquindo 4501/ }).first().click();
await V.waitForLoadState('load');
await esperar('ficha: el reproductor de YouTube carga dentro del iframe', async () =>
    V.frames().some((f) => f.url().startsWith('https://www.youtube-nocookie.com/embed/jfKfPfyJRdk')), 15000);
await esperar('ficha: el mapa carga mosaicos de OpenStreetMap', async () =>
    V.locator('img[src*="tile.openstreetmap.org"]').first().evaluate((i) => i.complete && i.naturalWidth > 0), 15000);
await V.goto(URL + '/ingresar', { waitUntil: 'load' });
await V.getByRole('link', { name: 'Crear cuenta de postor' }).click();
await V.waitForLoadState('load');
await V.locator('input[name=rut]').fill('17.998.221-0');
await esperar('registro: Alpine valida el RUT al escribir', async () =>
    (await V.locator('input[name=rut]').getAttribute('class')).includes('es-invalido'), 5000);

// ── Postor en la sala: estado, presencia y video ─────────────────────────────────────────────────────────
console.log('\n— Postor en la sala');
const cP = await contexto(navegador);
const P = await cP.newPage();
const latidos = [];
P.on('response', (r) => { if (r.url().includes('/presencia.php')) latidos.push(r.status()); });
await entrar(P, 'postor', '/remates/apoquindo/sala', 'mpgonzalez@correo.test');
await esperar('sala: el estado llega (precio y «Vas ganando»)', async () => (await P.locator('.sala-estado__titulo').first().innerText()) === 'Vas ganando');
await esperar('sala: el latido de presencia responde 200', async () => latidos.includes(200));
await esperar('sala: «1 CONECTADO» (la presencia llega al JSON)', async () => (await P.locator('.sala__conectados').innerText()).includes('1 CONECTADO'), 15000);
await esperar('sala: el video de YouTube carga', async () => P.frames().some((f) => f.url().startsWith('https://www.youtube-nocookie.com/embed/')), 15000);

// ── Administración: los confirm() que antes eran onsubmit en línea ──────────────────────────────────────
console.log('\n— Administración');
const cA = await contexto(navegador);
const A = await cA.newPage();
await entrar(A, 'admin', '/admin/reportes');
const antes = A.url();
await A.locator('select[name=periodo]').selectOption({ index: 1 });
await esperar('reportes: cambiar el período recarga con el filtro (era onchange en línea)', async () => A.url() !== antes && A.url().includes('periodo='));
// Restaurar plantilla: se edita y guarda para que aparezca «Restaurar la original»; el confirm debe salir y, cancelado, no enviar.
await entrar(A, 'admin', '/admin/configuracion/plantillas/cuenta_aprobada');
const asunto = A.locator('input[name=asunto]');
if (await asunto.count()) {
    await asunto.fill((await asunto.inputValue()) + ' (prueba)');
    await A.getByRole('button', { name: /Guardar/ }).first().click();
    await A.waitForLoadState('load');
}
const restaurar = A.getByRole('button', { name: 'Restaurar la original' });
if (await restaurar.count()) {
    let dialogo = '';
    A.once('dialog', (d) => { dialogo = d.message(); d.dismiss(); });
    await restaurar.click();
    await A.waitForTimeout(800);
    comprobar('panel: «Restaurar la original» pide confirmación (era onsubmit en línea)', dialogo.startsWith('¿Volver al texto original?'), dialogo);
    comprobar('panel: cancelar el confirm no envía el formulario', await restaurar.isVisible());
} else {
    comprobar('panel: hay una plantilla editada para probar el confirm', false, A.url());
}

comprobar('ningún bloqueo de la CSP en todo el recorrido', bloqueos.length === 0, bloqueos.slice(0, 5).join(' | '));

await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
