// Segundo factor del panel en navegador real (22/10), como lo haría una persona: clave → «Revisa tu correo» → código.
// Código incorrecto, reenvío con espera, código correcto, recordar el dispositivo, entrar sin código con ese navegador,
// olvidarlo desde «Sesiones activas» y volver a necesitarlo. Pantalla a 1440 y 375 px (sin desborde, táctiles ≥ 44 px).
// El código se «lee del correo» en el log local (MAIL_MAILER=log) con tools/comparar/segundo-factor.mjs.
//
// Requiere `php artisan serve` (APP_ENV=local). REINICIA la base local con el seeder.
//   node tools/comparar/dos-pasos.mjs
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';
import { revisarUsabilidad } from './usabilidad.mjs';
import { leerCodigo } from './segundo-factor.mjs';

const URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const SALIDA = path.resolve('tools/comparar/salida/dos-pasos');
const CORREO = 'admin@colliers.test';
fs.mkdirSync(SALIDA, { recursive: true });
let fallas = 0;
const comprobar = (nombre, ok, detalle = '') => {
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre}${detalle ? '  (' + detalle + ')' : ''}`);
};
const texto = async (p, selector = 'body') => (await p.locator(selector).first().innerText()).replace(/\s+/g, ' ');

console.log('Preparando la base local (migrate:fresh --seed)…');
execFileSync('php', ['tools/comparar/sala-ayudante.php', 'reiniciar'], { encoding: 'utf8' });

const navegador = await chromium.launch({ channel: 'chrome' });
const contexto = await navegador.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-CL', timezoneId: 'America/Santiago' });
const p = await contexto.newPage();

const ingresarConClave = async (pagina) => {
    await pagina.goto(URL, { waitUntil: 'load' });
    if (pagina.viewportSize().width < 760) {
        // En móvil «Ingresar» está en el menú de la cabecera.
        await pagina.locator('.pub-cabecera__menu-boton').click();
        await pagina.locator('.pub-menu__acceso', { hasText: 'Ingresar' }).click();
    } else {
        await pagina.locator('.pub-cabecera__acceso', { hasText: 'Ingresar' }).click();
    }
    await pagina.getByRole('link', { name: 'Acceso administradores' }).click();
    await pagina.fill('input[name=usuario]', CORREO);
    await pagina.fill('input[name=password]', 'colliers-local-2026');
    await pagina.getByRole('button', { name: 'Ingresar' }).click();
    await pagina.waitForLoadState('load');
};

// 1. La clave no abre sesión: pide el código.
await ingresarConClave(p);
comprobar('la clave lleva a «Revisa tu correo», sin sesión', p.url().endsWith('/admin/ingresar/codigo'), p.url());
const intro = await texto(p, '.acceso__contenido');
comprobar('dice a qué correo (enmascarado) y cuánto dura', intro.includes('a••••@colliers.test') && intro.includes('10 minutos'), intro.slice(0, 200));
const primero = leerCodigo(CORREO);
comprobar('el correo trae un código de 6 dígitos', /^\d{6}$/.test(primero), primero);
await p.screenshot({ path: path.join(SALIDA, 'codigo-1440.png'), fullPage: true });
const panelSinCodigo = await p.request.get(URL + '/admin', { maxRedirects: 0 });
comprobar('sin el código, el panel sigue cerrado', panelSinCodigo.status() === 302 && (panelSinCodigo.headers().location || '').endsWith('/admin/ingresar'), String(panelSinCodigo.status()));

// 2. Código incorrecto.
await p.fill('input[name=codigo]', primero === '000000' ? '111111' : '000000');
await p.getByRole('button', { name: 'Entrar al panel' }).click();
await p.waitForLoadState('load');
comprobar('código incorrecto: lo dice y cuántos intentos quedan', (await texto(p, '.acceso__error')).includes('Te quedan 4 intentos'), await texto(p, '.acceso__error'));

// 3. Reenvío antes del minuto: espera.
await p.getByRole('button', { name: 'Enviar otro código' }).click();
await p.waitForLoadState('load');
comprobar('reenviar antes del minuto: pide esperar', (await texto(p, '.acceso__error')).includes('Espera'), await texto(p, '.acceso__error'));
comprobar('y no envía otro correo', leerCodigo(CORREO) === primero);

// 4. Código correcto + recordar el dispositivo.
await p.fill('input[name=codigo]', primero);
await p.check('input[name=recordar_dispositivo]');
await p.getByRole('button', { name: 'Entrar al panel' }).click();
await p.waitForLoadState('load');
comprobar('con el código correcto entra al panel', p.url().endsWith('/admin'), p.url());

// 5. Cerrar sesión y volver a entrar con ese navegador: solo la clave.
await p.getByRole('button', { name: 'Cerrar sesión' }).click();
await p.waitForLoadState('load');
await ingresarConClave(p);
comprobar('dispositivo recordado: la clave basta', p.url().endsWith('/admin'), p.url());
comprobar('y no se envió otro código', leerCodigo(CORREO) === primero);

// 6. «Sesiones activas» (enlace del pie del menú lateral) lo muestra y lo olvida.
await p.getByRole('link', { name: 'Sesiones y contraseña' }).click();
await p.waitForLoadState('load');
comprobar('del panel a «Sesiones activas» con un clic', p.url().endsWith('/mi-cuenta/sesiones'), p.url());
const sesiones = await texto(p);
comprobar('«Sesiones activas» lista el dispositivo recordado', sesiones.includes('DISPOSITIVOS QUE NO PIDEN CÓDIGO') && sesiones.includes('RECORDADO HASTA'), sesiones.slice(0, 160));
await p.screenshot({ path: path.join(SALIDA, 'sesiones-1440.png'), fullPage: true });
await p.locator('[data-dispositivos]').getByRole('button', { name: 'OLVIDAR' }).click();
await p.waitForLoadState('load');
comprobar('olvidar: lo confirma y desaparece de la lista', (await texto(p)).includes('volverá a pedir el código') && (await p.locator('[data-dispositivos]').count()) === 0);

// 7. Con el dispositivo olvidado, vuelve a pedir el código.
await p.getByRole('button', { name: 'Cerrar sesión' }).click();
await p.waitForLoadState('load');
await ingresarConClave(p);
comprobar('olvidado: vuelve a pedir el código', p.url().endsWith('/admin/ingresar/codigo'), p.url());
const segundo = leerCodigo(CORREO);
comprobar('y llegó un código nuevo', /^\d{6}$/.test(segundo) && segundo !== primero, segundo);

// 8. Móvil.
const movil = await navegador.newContext({ viewport: { width: 375, height: 800 }, locale: 'es-CL' });
const m = await movil.newPage();
await ingresarConClave(m);
comprobar('375 px: pide el código', m.url().endsWith('/admin/ingresar/codigo'), m.url());
const usabilidad = await revisarUsabilidad(m, 375);
comprobar('375 px: sin desborde y táctiles ≥ 44 px', !usabilidad.desborde && usabilidad.pequenos.length === 0, JSON.stringify(usabilidad).slice(0, 200));
await m.screenshot({ path: path.join(SALIDA, 'codigo-375.png'), fullPage: true });
await m.fill('input[name=codigo]', leerCodigo(CORREO));
await m.getByRole('button', { name: 'Entrar al panel' }).click();
await m.waitForLoadState('load');
comprobar('375 px: entra al panel', m.url().endsWith('/admin'), m.url());

await navegador.close();
console.log(fallas === 0 ? '\nSegundo factor: todo OK' : `\nSegundo factor: ${fallas} falla(s)`);
process.exit(fallas === 0 ? 0 : 1);
