// Bloque K: la sala de puja contra el motor real, en navegador, con dos postores a la vez y uno en móvil.
// Requiere `php artisan serve` (local, APP_ENV=local). Reinicia la base local con el seeder.
//
//   node tools/comparar/sala-real.mjs
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const SALA = '/remates/apoquindo/sala';
let fallas = 0;

const ayudante = (...args) => execFileSync('php', ['tools/comparar/sala-ayudante.php', ...args], { encoding: 'utf8' }).trim();
const estadoBase = () => JSON.parse(ayudante('estado'));

function comprobar(nombre, ok, detalle = '') {
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre}${detalle ? '  (' + detalle + ')' : ''}`);
}

async function esperar(nombre, funcion, ms = 8000) {
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

async function abrir(navegador, correo, { ancho = 1440, desfaseMs = 0 } = {}) {
    const contexto = await navegador.newContext({ viewport: { width: ancho, height: 900 }, locale: 'es-CL', timezoneId: 'America/Santiago' });
    const pagina = await contexto.newPage();
    if (desfaseMs) {
        await pagina.clock.install({ time: Date.now() + desfaseMs });
        await pagina.clock.resume();
    }
    await pagina.goto(`${URL}/revision/entrar/postor?usuario=${encodeURIComponent(correo)}&a=${encodeURIComponent(SALA)}`, { waitUntil: 'load' });
    return { contexto, pagina };
}

const texto = (pagina, selector) => pagina.locator(selector).first().innerText();
const panel = (pagina) => pagina.locator('.sala-panel');

console.log('Preparando la base local (migrate:fresh --seed)…');
ayudante('reiniciar');

const navegador = await chromium.launch({ channel: 'chrome' });

// ── A: líder según el seeder, con el reloj del equipo 7 minutos adelantado ─────────────────────────────────
const A = await abrir(navegador, 'mpgonzalez@correo.test', { desfaseMs: 7 * 60 * 1000 });
await esperar('A ve «Vas ganando» y el precio del seeder', async () =>
    (await texto(A.pagina, '.sala-estado__titulo')) === 'Vas ganando' && (await texto(A.pagina, '.sala-panel__precio')) === '$198.500.000');

await A.pagina.waitForTimeout(1500);
const cierraEnMs = estadoBase().cierra_en_ms; // antes de leer: el ayudante PHP tarda 1–2 s en arrancar
const cuenta = await A.pagina.locator('.sala-panel .sala-contador__digito').allInnerTexts();
const leidoEn = Date.now();
const restanteVisto = Number(cuenta[0]) * 3600 + Number(cuenta[1]) * 60 + Number(cuenta[2]);
const restanteReal = Math.floor((cierraEnMs - leidoEn) / 1000);
comprobar('cronómetro con la hora del servidor aunque el equipo esté 7 min adelantado', Math.abs(restanteVisto - restanteReal) <= 2, `visto ${restanteVisto} s, real ${restanteReal} s`);
comprobar('quien va ganando no puede pujar (botón deshabilitado)', await panel(A.pagina).locator('.sala-puja__boton').isDisabled());
comprobar('aviso de fuente oficial visible', await A.pagina.getByText('Precio y cronómetro oficiales: el video tiene 10–30 s de retraso').isVisible());

// ── B: puja rápida con modal de confirmación ─────────────────────────────────────────────────────────────
const B = await abrir(navegador, 'pfuentes@correo.test');
await esperar('B ve «Te superaron»', async () => (await texto(B.pagina, '.sala-estado__titulo')) === 'Te superaron');
await panel(B.pagina).locator('.sala-puja__rapida').first().click();
await panel(B.pagina).locator('.sala-puja__boton').click();
comprobar('la puja exige el modal de confirmación', await B.pagina.locator('.sala-modal').isVisible());
comprobar('el modal muestra el monto', (await texto(B.pagina, '.sala-modal__monto')) === '$198.600.000');
await B.pagina.locator('.sala-modal__confirmar').click();
await esperar('B pasa a «Vas ganando»', async () => (await texto(B.pagina, '.sala-estado__titulo')) === 'Vas ganando');
await esperar('A ve «Te superaron», el precio nuevo y a Postor #2 arriba del historial (JSON estático)', async () =>
    (await texto(A.pagina, '.sala-estado__titulo')) === 'Te superaron'
    && (await texto(A.pagina, '.sala-panel__precio')) === '$198.600.000'
    && (await texto(A.pagina, '.historial-puja__postor')) === 'Postor #2', 5000);
comprobar('la base registra la puja de B', estadoBase().precio_actual === 198600000 && estadoBase().ganador === 'pfuentes@correo.test');

// ── Validación del monto libre ───────────────────────────────────────────────────────────────────────────
await panel(A.pagina).locator('.sala-puja__input').fill('198650000');
comprobar('monto bajo el mínimo: botón deshabilitado y ayuda en rojo', await panel(A.pagina).locator('.sala-puja__boton').isDisabled()
    && (await panel(A.pagina).locator('.sala-puja__ayuda.es-invalido').innerText()) === 'La postura debe ser al menos $198.700.000.');

// ── Rechazo del servidor: A confirma un monto que otro superó mientras tanto ─────────────────────────────
await panel(A.pagina).locator('.sala-puja__input').fill('198700000');
await panel(A.pagina).locator('.sala-puja__boton').click();
// Mientras A tiene el modal abierto, un tercer postor (C) puja por su propia interfaz.
await panel(B.pagina).locator('.sala-puja__input').fill('199000000');
comprobar('B no puede superarse a sí mismo', await panel(B.pagina).locator('.sala-puja__boton').isDisabled());
const C0 = await abrir(navegador, 'contacto@andes.test');
await esperar('C ve el precio de B', async () => (await texto(C0.pagina, '.sala-panel__precio')) === '$198.600.000');
await panel(C0.pagina).locator('.sala-puja__input').fill('199000000');
await panel(C0.pagina).locator('.sala-puja__boton').click();
await C0.pagina.locator('.sala-modal__confirmar').click();
await esperar('C pasa a «Vas ganando» con $199.000.000', async () => (await texto(C0.pagina, '.sala-estado__titulo')) === 'Vas ganando');
await A.pagina.locator('.sala-modal__confirmar').click();
await esperar('A recibe el rechazo del motor en español', async () =>
    (await panel(A.pagina).locator('.sala-puja__ayuda.es-invalido').innerText()) === 'El monto es menor que la puja mínima.');
comprobar('el rechazo no registró una puja', estadoBase().precio_actual === 199000000);
await C0.contexto.close();

// ── Reconexión: A pierde la red, B puja, A vuelve ────────────────────────────────────────────────────────────
await A.contexto.setOffline(true);
await B.pagina.waitForTimeout(500);
await panel(B.pagina).locator('.sala-puja__rapida').nth(1).click();
await panel(B.pagina).locator('.sala-puja__boton').click();
await B.pagina.locator('.sala-modal__confirmar').click();
await esperar('B puja +$500k mientras A está sin conexión', async () => (await texto(B.pagina, '.sala-estado__titulo')) === 'Vas ganando');
await A.pagina.waitForTimeout(2500);
comprobar('A sin conexión conserva el último estado conocido', (await texto(A.pagina, '.sala-panel__precio')) === '$199.000.000');
await A.contexto.setOffline(false);
await esperar('A se reconecta y recupera el estado actual', async () => (await texto(A.pagina, '.sala-panel__precio')) === '$199.500.000', 10000);

// ── Móvil: barra fija, hoja de puja y modal ──────────────────────────────────────────────────────────────
const M = await abrir(navegador, 'contacto@andes.test', { ancho: 375 });
await esperar('móvil: la barra fija muestra el precio', async () => (await texto(M.pagina, '.sala-barra__precio')) === '$199.500.000');
await M.pagina.locator('.sala-barra__boton').click();
await M.pagina.locator('.sala-hoja .sala-puja__rapida').first().click();
await M.pagina.locator('.sala-hoja .sala-puja__boton').click();
await M.pagina.locator('.sala-modal__confirmar').click();
await esperar('móvil: puja confirmada, hoja cerrada y barra en «VAS GANANDO»', async () =>
    !(await M.pagina.locator('.sala-hoja').isVisible()) && (await texto(M.pagina, '.sala-barra__estado')) === 'VAS GANANDO');
const desborde = await M.pagina.evaluate(() => document.documentElement.scrollWidth > innerWidth);
comprobar('móvil: sin scroll horizontal', !desborde);

// ── Cierre: sin anti-sniping, adjudicación pasado el margen ──────────────────────────────────────────────
ayudante('cerrar-en', '6');
await esperar('al llegar a cierra_en el formulario desaparece (A, B y móvil)', async () =>
    (await panel(A.pagina).locator('.sala-puja__boton').count()) === 0 && (await panel(B.pagina).locator('.sala-puja__boton').count()) === 0
    && (await M.pagina.locator('.sala-barra__boton').isDisabled()), 12000);
await esperar('el navegador dispara la liquidación y A ve «Adjudicado a otro postor»', async () =>
    (await texto(A.pagina, '.sala-resultado__titulo')) === 'Adjudicado a otro postor', 15000);
await esperar('el móvil (ganador) ve «Te adjudicaste la propiedad»', async () =>
    (await texto(M.pagina, '.sala-resultado__titulo')) === 'Te adjudicaste la propiedad', 8000);
const final = estadoBase();
comprobar('la base: lote adjudicado al último postor y al último monto', final.estado === 'adjudicado' && final.adjudicado_a === 'contacto@andes.test' && final.precio_actual === 199600000, JSON.stringify(final));

await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
