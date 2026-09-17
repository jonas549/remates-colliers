// Cierre visto por un ESPECTADOR (sin sesión), en navegador real. Bloque J, 17/09:
// A) el estado cambia al segundo con la hora del servidor, sin pedirle nada al servidor;
// B) pasado el margen, UN solo aviso al endpoint de estado (con espera al azar y porcentaje configurables) para que
//    el resultado se publique en segundos, sin esperar el cron del minuto; si el servidor rechaza, se rinde.
//
// Requiere `php artisan serve` (APP_ENV=local). Reinicia la base local con el seeder.
//
//   node tools/comparar/cierre-espectador.mjs
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
let fallas = 0;
const comprobar = (nombre, ok, detalle = '') => {
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre}${detalle ? '  (' + detalle + ')' : ''}`);
};
const ayudante = (...args) => execFileSync('php', ['tools/comparar/sala-ayudante.php', ...args], { encoding: 'utf8' }).trim();
const config = (clave, valor) => execFileSync('php', ['artisan', 'tinker', `--execute=App\\Models\\Configuracion::guardar('${clave}', '${valor}');`], { encoding: 'utf8' });
const esperar = (ms) => new Promise((r) => setTimeout(r, ms));
const estadoJson = async () => (await (await fetch(`${URL}/tiempo-real/apoquindo.json?t=${Date.now()}`, { cache: 'no-store' })).json()).lotes[0];

console.log('Preparando la base local (migrate:fresh --seed)…');
ayudante('reiniciar');
config('margen_liquidacion_segundos', '2');
config('cierre_aviso_espera_segundos', '3');
config('cierre_aviso_porcentaje', '100');

const navegador = await chromium.launch({ channel: 'chrome' });

// ── 1. El espectador ve el cierre al segundo y avisa una sola vez ───────────────────────────────────────
{
    const c = await navegador.newContext({ viewport: { width: 1280, height: 900 }, locale: 'es-CL' });
    const p = await c.newPage();
    const llamadasEstado = [];
    p.on('request', (r) => {
        if (r.url().includes('/remates/apoquindo/estado')) llamadasEstado.push(Date.now());
    });

    await p.goto(`${URL}/remates/apoquindo`, { waitUntil: 'load' });
    comprobar('en vivo: la ficha dice REMATE EN CURSO', (await p.locator('.detalle-remate__cabeza-vivo').first().innerText()).includes('REMATE EN CURSO'));

    ayudante('cerrar-en', '6');
    await esperar(7000);
    const cabecera = await p.locator('.detalle-remate__cabeza-vivo').allInnerTexts();
    comprobar('al llegar la hora de cierre el estado cambia solo', cabecera.some((t) => t.includes('CERRADO · ADJUDICANDO')), cabecera.join(' / '));
    comprobar('y todavía no le pidió nada al servidor (solo el JSON estático)', llamadasEstado.length === 0, `${llamadasEstado.length} llamadas`);

    // Margen (2 s) + espera al azar (0–3 s): a los 6 s ya avisó una sola vez y el JSON trae el resultado.
    await esperar(6000);
    const lote = await estadoJson();
    comprobar('un solo aviso al servidor', llamadasEstado.length === 1, `${llamadasEstado.length} llamadas`);
    comprobar('el JSON público queda con el resultado, sin esperar al cron', ['adjudicado', 'desierto'].includes(lote.estado), lote.estado);
    const textoFinal = (await p.locator('.detalle-remate__cabeza-vivo').allInnerTexts()).join(' / ');
    comprobar('la ficha muestra el resultado', textoFinal.includes('REMATE CERRADO') || textoFinal.includes('REMATE ADJUDICADO'), textoFinal);

    await esperar(4000);
    comprobar('no reintenta después de avisar', llamadasEstado.length === 1, `${llamadasEstado.length} llamadas`);
    await c.close();
}

// ── 2. Si el servidor rechaza por límite de peticiones, el cliente se rinde ──────────────────────────────
{
    ayudante('reiniciar');
    config('margen_liquidacion_segundos', '2');
    config('cierre_aviso_espera_segundos', '0');
    const c = await navegador.newContext({ viewport: { width: 1280, height: 900 }, locale: 'es-CL' });
    const p = await c.newPage();
    const llamadas = [];
    await p.route(/\/remates\/apoquindo\/estado/, async (ruta) => {
        llamadas.push(Date.now());
        await ruta.fulfill({ status: 429, contentType: 'application/json', body: JSON.stringify({ message: 'Demasiadas peticiones' }) });
    });

    await p.goto(`${URL}/remates/apoquindo`, { waitUntil: 'load' });
    ayudante('cerrar-en', '4');
    await esperar(12000);
    comprobar('con 429 avisa una vez y se rinde', llamadas.length === 1, `${llamadas.length} llamadas`);
    comprobar('la ficha sigue mostrando el cierre igual', (await p.locator('.detalle-remate__cabeza-vivo').allInnerTexts()).some((t) => t.includes('CERRADO')));
    await c.close();
}

// ── 3. Con 0 % nadie avisa: el resultado queda para el cron ──────────────────────────────────────────────
{
    ayudante('reiniciar');
    config('margen_liquidacion_segundos', '2');
    config('cierre_aviso_porcentaje', '0');
    const c = await navegador.newContext({ viewport: { width: 1280, height: 900 }, locale: 'es-CL' });
    const p = await c.newPage();
    const llamadas = [];
    p.on('request', (r) => {
        if (r.url().includes('/remates/apoquindo/estado')) llamadas.push(Date.now());
    });

    await p.goto(`${URL}/remates/apoquindo`, { waitUntil: 'load' });
    ayudante('cerrar-en', '4');
    await esperar(10000);
    comprobar('con 0 % ningún espectador avisa', llamadas.length === 0, `${llamadas.length} llamadas`);
    comprobar('pero igual ve el cierre al segundo', (await p.locator('.detalle-remate__cabeza-vivo').allInnerTexts()).some((t) => t.includes('CERRADO · ADJUDICANDO')));
    comprobar('el JSON sigue sin el resultado hasta que corre el cron', !['adjudicado', 'desierto'].includes((await estadoJson()).estado));

    execFileSync('php', ['artisan', 'colliers:liquidar'], { encoding: 'utf8' });
    await esperar(2500);
    comprobar('tras el cron, el JSON y la ficha muestran el resultado', ['adjudicado', 'desierto'].includes((await estadoJson()).estado)
        && (await p.locator('.detalle-remate__cabeza-vivo').allInnerTexts()).some((t) => t.includes('REMATE CERRADO') || t.includes('REMATE ADJUDICADO')));
    await c.close();
}

config('cierre_aviso_porcentaje', '100');
config('cierre_aviso_espera_segundos', '5');
config('margen_liquidacion_segundos', '2');
await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
