// Ingreso con la clave del sandbox activa, en navegador real (bug del 17/09: la pantalla «solo se refrescaba»).
// Requiere un servidor con la clave de acceso, como el sandbox:
//   APP_ENV=staging SESSION_SECURE_COOKIE=true COLLIERS_ACCESO_CLAVE=prueba-sandbox php artisan serve --port=8010
// y la base local sembrada. Crea su propio administrador con cambio de clave pendiente (como colliers:instalar).
//
//   node tools/comparar/ingreso-sandbox.mjs
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8010';
const ACCESO = process.env.ACCESO || 'prueba-sandbox';
let fallas = 0;
const comprobar = (nombre, ok, detalle = '') => {
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre}${detalle ? '  (' + detalle + ')' : ''}`);
};

const correo = `admin-${Date.now()}@colliers.test`;
const salida = execFileSync('php', ['artisan', 'colliers:crear-usuario', 'admin', correo, 'Admin', 'Sandbox'], { encoding: 'utf8' });
const clave = salida.match(/una sola vez\): (\S+)/)[1];

const navegador = await chromium.launch({ channel: 'chrome' });

async function nuevaSesion() {
    const c = await navegador.newContext({ locale: 'es-CL' });
    const p = await c.newPage();
    await p.goto(`${URL}/admin/ingresar`, { waitUntil: 'load' });
    comprobar('sin la cookie de la clave pide el acceso (primera visita, sin aviso)', p.url().includes('/acceso') && (await p.locator('[role=alert]').count()) === 0);
    await p.fill('input[name=clave]', ACCESO);
    await p.locator('button[type=submit]').click();
    await p.waitForLoadState('load');
    return { c, p };
}
async function ingresar(p) {
    await p.fill('input[name=usuario]', correo);
    await p.fill('input[name=password]', clave);
    await p.getByRole('button', { name: 'Ingresar' }).click();
    await p.waitForLoadState('load');
}

// 1. Flujo normal: entra y llega al cambio de clave obligatorio, sin la marca en la URL.
{
    const { c, p } = await nuevaSesion();
    comprobar('con la clave vuelve al acceso de administración', p.url().endsWith('/admin/ingresar'), p.url());
    await ingresar(p);
    comprobar('ingresa y llega al cambio de clave obligatorio', p.url().endsWith('/mi-cuenta/cambiar-clave'), p.url());

    // La cookie de la clave desaparece con la sesión iniciada: no la expulsa y se la devuelve.
    await c.clearCookies({ name: 'colliers_acceso' });
    await p.goto(`${URL}/mi-cuenta/cambiar-clave`, { waitUntil: 'load' });
    comprobar('sesión iniciada sin la cookie de la clave: no la expulsa', p.url().endsWith('/mi-cuenta/cambiar-clave'), p.url());
    comprobar('y recibe de nuevo la cookie de la clave', (await c.cookies()).some((k) => k.name === 'colliers_acceso'));
    await c.close();
}

// 2. El navegador no presenta la sesión después de ingresar (lo que pasaba en el sandbox): la pantalla lo dice.
{
    const { c, p } = await nuevaSesion();
    // La respuesta del ingreso llega sin sus Set-Cookie: el navegador sigue con la sesión anterior, sin usuario.
    await p.route(/\/admin\/ingresar$/, async (ruta) => {
        if (ruta.request().method() !== 'POST') return ruta.continue();
        // ruta.fetch comparte las cookies con el navegador: se restauran las de antes para simular que no se guardaron.
        const antes = await c.cookies();
        const respuesta = await ruta.fetch({ maxRedirects: 0 });
        await c.clearCookies();
        await c.addCookies(antes);
        const cabeceras = Object.fromEntries(Object.entries(respuesta.headers()).filter(([k]) => k.toLowerCase() !== 'set-cookie'));
        await ruta.fulfill({ status: respuesta.status(), headers: cabeceras, body: await respuesta.body() });
    });
    await ingresar(p);
    const error = (await p.locator('.acceso__error').allInnerTexts()).join(' ');
    comprobar('sesión perdida: vuelve al acceso de administración con el motivo', p.url().includes('/admin/ingresar?sesion=perdida') && error.includes('el navegador no conservó la sesión'), `${p.url()} · ${error}`);
    await c.close();
}

// 3. Clave incorrecta: siempre con mensaje.
{
    const { c, p } = await nuevaSesion();
    await p.fill('input[name=usuario]', correo);
    await p.fill('input[name=password]', 'incorrecta-123');
    await p.getByRole('button', { name: 'Ingresar' }).click();
    await p.waitForLoadState('load');
    const error = (await p.locator('.acceso__error').allInnerTexts()).join(' ');
    comprobar('clave incorrecta: mensaje visible', error.includes('Usuario o contraseña incorrectos'), error);

    // 4. La cookie de la clave vence mientras navega sin sesión: /acceso explica por qué y vuelve al formulario.
    await c.clearCookies({ name: 'colliers_acceso' });
    await p.fill('input[name=usuario]', correo);
    await p.fill('input[name=password]', 'incorrecta-123');
    await p.getByRole('button', { name: 'Ingresar' }).click();
    await p.waitForLoadState('load');
    const aviso = (await p.locator('[role=alert]').allInnerTexts()).join(' ');
    comprobar('clave del sandbox vencida al enviar: /acceso con el motivo', p.url().includes('/acceso') && aviso.includes('venció o no se encontró'), aviso);
    await p.fill('input[name=clave]', ACCESO);
    await p.locator('button[type=submit]').click();
    await p.waitForLoadState('load');
    comprobar('con la clave vuelve al formulario de ingreso', p.url().endsWith('/admin/ingresar'), p.url());
    await c.close();
}

// Intentos fallidos de esta prueba: se limpian para no dejar la cuenta bloqueada.
execFileSync('php', ['artisan', 'tinker', `--execute=App\\Models\\User::where('email', '${correo}')->update(['intentos_fallidos' => 0, 'bloqueado_hasta' => null]);`], { encoding: 'utf8' });
await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
