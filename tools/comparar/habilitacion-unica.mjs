// QA en navegador real del modo «habilitación única» (decisión de Jonas, 20/09): el interruptor del panel, la
// habilitación que se constituye una sola vez y la puja en un remate en el que el postor nunca se inscribió.
//
//   php artisan serve      (en otra terminal)
//   node tools/comparar/habilitacion-unica.mjs

import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const SALIDA = path.resolve('tools/comparar/salida/habilitacion-unica');
fs.mkdirSync(SALIDA, { recursive: true });
let fallas = 0;
const comprobar = (nombre, ok, detalle = '') => {
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre}${detalle ? '  (' + detalle + ')' : ''}`);
};

console.log('Preparando la base local (migrate:fresh --seed)…');
execFileSync('php', ['tools/comparar/sala-ayudante.php', 'reiniciar'], { encoding: 'utf8' });

const navegador = await chromium.launch({ channel: 'chrome' });
const admin = await (await navegador.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-CL', timezoneId: 'America/Santiago' })).newPage();

// ── 1 · El interruptor exige el monto ─────────────────────────────────────────────────────────────────
await admin.goto(`${URL}/revision/entrar/admin?a=/admin/configuracion/garantias`, { waitUntil: 'load' });
comprobar('Configuración → Garantías ofrece el modo de habilitación',
    (await admin.locator('select[name="config[garantia_modo]"]').count()) === 1);

await admin.selectOption('select[name="config[garantia_modo]"]', 'unico');
await admin.fill('input[name="config[garantia_monto_unico]"]', '');
await admin.getByRole('button', { name: /Guardar/ }).first().click();
await admin.waitForLoadState('load');
comprobar('sin monto, el panel no deja activar el modo',
    (await admin.locator('body').innerText()).includes('Fija el monto de la habilitación única'));

await admin.selectOption('select[name="config[garantia_modo]"]', 'unico');
await admin.fill('input[name="config[garantia_monto_unico]"]', '5000000');
await admin.getByRole('button', { name: /Guardar/ }).first().click();
await admin.waitForSelector('.admin-aviso--ok');
comprobar('con monto, el modo queda activo', (await admin.locator('.admin-aviso--ok').innerText()).includes('guardada'));
await admin.screenshot({ path: path.join(SALIDA, 'configuracion-1440.png'), fullPage: true });

// ── 2 · El postor constituye la habilitación una sola vez ─────────────────────────────────────────────
// `rsalas` es el postor del seeder sin garantías; se le aprueba la cuenta para poder habilitarlo.
execFileSync('php', ['artisan', 'tinker', '--execute',
    "$p = App\\Models\\Postor::whereHas('user', fn($q) => $q->where('email', 'rsalas@correo.test'))->first();" +
    " $p->forceFill(['estado' => 'aprobado', 'revisado_en' => now()])->save();"], { encoding: 'utf8' });

const postor = await (await navegador.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-CL' })).newPage();
await postor.goto(`${URL}/revision/entrar/postor?usuario=rsalas@correo.test&a=/mi-cuenta`, { waitUntil: 'load' });
const cuenta = (await postor.locator('body').innerText()).replace(/\s+/g, ' ');
comprobar('«Mi cuenta» explica la habilitación única en vez de la inscripción por remate',
    cuenta.includes('constituir la habilitación') && cuenta.includes('$5.000.000'), cuenta.slice(0, 180));

await postor.locator('form[action*="inscribirme"] button').first().click();
await postor.waitForLoadState('load');
comprobar('el postor constituye la habilitación desde cualquier remate',
    (await postor.locator('body').innerText()).includes('GARANTÍA PENDIENTE') || (await postor.locator('body').innerText()).includes('habilitación'));
await postor.screenshot({ path: path.join(SALIDA, 'mi-cuenta-pendiente-1440.png'), fullPage: true });

// ── 3 · La administración la aprueba como cualquier garantía ──────────────────────────────────────────
await admin.goto(`${URL}/admin/postores`, { waitUntil: 'load' });
const fila = admin.locator('tr', { hasText: 'Salas' }).first();
await fila.getByRole('button', { name: /Aprobar/ }).first().click();
await admin.waitForTimeout(800);
comprobar('la habilitación se aprueba desde Postores como cualquier garantía',
    (await admin.locator('body').innerText()).includes('aprobada') || (await admin.locator('body').innerText()).includes('Aprobada'));

// ── 4 · Con una sola habilitación puja en un remate donde nunca se inscribió ──────────────────────────
await postor.goto(`${URL}/mi-cuenta`, { waitUntil: 'load' });
const habilitado = (await postor.locator('body').innerText()).replace(/\s+/g, ' ');
comprobar('«Mi cuenta» dice que la habilitación sirve para todos los remates',
    habilitado.includes('todos los remates') || habilitado.includes('cualquier remate publicado'), habilitado.slice(0, 200));

// El remate en vivo del seeder (apoquindo) es de otros postores: este nunca se inscribió en él.
const sala = await postor.goto(`${URL}/remates/apoquindo/sala`, { waitUntil: 'load' });
const textoSala = (await postor.locator('body').innerText()).replace(/\s+/g, ' ');
comprobar('entra a la sala de un remate en el que nunca se inscribió',
    sala.status() === 200 && postor.url().includes('/sala') && textoSala.includes('GARANTÍA APROBADA'),
    postor.url() + ' · ' + textoSala.slice(0, 120));
await postor.screenshot({ path: path.join(SALIDA, 'sala-con-habilitacion-1440.png'), fullPage: true });

// ── 5 · Volver al modo por remate deja las cosas como estaban ─────────────────────────────────────────
await admin.goto(`${URL}/admin/configuracion/garantias`, { waitUntil: 'load' });
await admin.selectOption('select[name="config[garantia_modo]"]', 'remate');
await admin.getByRole('button', { name: /Guardar/ }).first().click();
await admin.waitForSelector('.admin-aviso--ok');
const salaOtra = await postor.goto(`${URL}/remates/apoquindo/sala`, { waitUntil: 'load' });
comprobar('al volver al modo por remate, la habilitación ya no abre un remate ajeno',
    !postor.url().includes('/sala'), `HTTP ${salaOtra.status()} · ${postor.url()}`);

await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
