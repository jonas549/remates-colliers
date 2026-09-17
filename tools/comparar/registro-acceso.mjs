// QA en navegador real de registro y acceso (Bloque D + G): registro natural y jurídica, RUT inválido/repetido/con y sin
// puntos, confirmación de correo, ingreso con correo y con RUT, bloqueo tras 5 intentos que no se levanta vaciando la
// caché, recuperación de contraseña, cambio obligatorio de la clave temporal y aprobación/rechazo de cuentas.
// Requiere `php artisan serve` (APP_ENV=local, MAIL_MAILER=log). Reinicia la base local con el seeder.
//
//   node tools/comparar/registro-acceso.mjs
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';

const URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const LOG = path.resolve('storage/logs/laravel.log');
let fallas = 0;
const comprobar = (nombre, ok, detalle = '') => {
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre}${detalle ? '  (' + detalle + ')' : ''}`);
};
const artisan = (...args) => execFileSync('php', ['artisan', ...args], { encoding: 'utf8' });

console.log('Preparando la base local (migrate:fresh --seed)…');
execFileSync('php', ['tools/comparar/sala-ayudante.php', 'reiniciar'], { encoding: 'utf8' });
const pdf = path.join(os.tmpdir(), 'documento-colliers.pdf');
fs.writeFileSync(pdf, '%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n');

/** Último enlace que coincide en el correo registrado en el log (decodifica quoted-printable). */
function enlaceDelCorreo(inicioLog, patron) {
    const texto = fs.readFileSync(LOG, 'utf8').slice(inicioLog).replace(/=\r?\n/g, '').replace(/=3D/g, '=');
    const encontrados = [...texto.matchAll(patron)];
    return encontrados.length ? encontrados[encontrados.length - 1][0].replace(/&amp;/g, '&') : null;
}
const tamanoLog = () => (fs.existsSync(LOG) ? fs.statSync(LOG).size : 0);

const navegador = await chromium.launch({ channel: 'chrome' });
const nuevaPagina = async (ancho = 1280) => (await navegador.newContext({ viewport: { width: ancho, height: 900 }, locale: 'es-CL' })).newPage();

async function llenarRegistro(p, datos) {
    await p.goto(`${URL}/registro`, { waitUntil: 'load' });
    if (datos.juridica) {
        await p.getByRole('button', { name: 'Persona jurídica' }).click();
    }
    await p.fill('input[name=nombres]', datos.nombres);
    await p.fill('input[name=apellidos]', 'Prueba QA');
    await p.fill('input[name=rut]', datos.rut);
    await p.fill('input[name=fecha_nacimiento]', '1988-03-14');
    await p.fill('input[name=nacionalidad]', 'Chilena');
    if (datos.juridica) {
        await p.fill('input[name=razon_social]', 'Inversiones QA SpA');
        await p.fill('input[name=rut_empresa]', '77.123.456-9');
        await p.fill('input[name=giro]', 'Inversiones');
        await p.setInputFiles('input[name="documentos[poder]"]', pdf);
    }
    await p.fill('input[name=email]', datos.email);
    await p.fill('input[name=telefono]', '+56 9 5555 0000');
    await p.fill('input[name=direccion]', 'Av. Providencia 1234');
    await p.fill('input[name=comuna]', 'Providencia');
    for (const doc of ['ci-frente', 'ci-dorso', 'domicilio']) {
        await p.setInputFiles(`input[name="documentos[${doc}]"]`, pdf);
    }
    await p.fill('input[name=password]', 'Clave-QA-2026');
    await p.fill('input[name=password_confirmation]', 'Clave-QA-2026');
    await p.check('input[name=acepta]');
    await p.getByRole('button', { name: 'Enviar solicitud de registro' }).click();
    await p.waitForLoadState('load');
}
const errorRut = async (p) => ((await p.locator('input[name=rut]').locator('xpath=ancestor::label').locator('.es-invalido').allInnerTexts()).join(' '));

async function ingresar(p, usuario, clave, admin = false) {
    await p.goto(`${URL}${admin ? '/admin' : ''}/ingresar`, { waitUntil: 'load' });
    await p.fill('input[name=usuario]', usuario);
    await p.fill('input[name=password]', clave);
    await p.getByRole('button', { name: 'Ingresar' }).click();
    await p.waitForLoadState('load');
    return (await p.locator('.acceso__error').allInnerTexts()).join(' ');
}

// ── 1. Registro de persona natural con RUT sin puntos + confirmación de correo ─────────────────────────────
{
    const p = await nuevaPagina(375);
    await llenarRegistro(p, { nombres: 'Rut Invalido', rut: '21.345.678-5', email: 'invalido@qa.test' });
    comprobar('RUT con dígito verificador incorrecto: rechazado con mensaje', (await errorRut(p)).includes('dígito verificador'), await errorRut(p));

    const inicio = tamanoLog();
    await llenarRegistro(p, { nombres: 'Camila', rut: '213456784', email: 'natural@qa.test' });
    comprobar('registro natural con RUT sin puntos ni guion: pide confirmar el correo', p.url().includes('/verificar-correo'), p.url());
    const enlace = enlaceDelCorreo(inicio, /http:\/\/127\.0\.0\.1:8000\/verificar-correo\/\d+\/[a-f0-9]+\?expires=\d+&(?:amp;)?signature=[a-f0-9]+/g);
    comprobar('el correo de confirmación trae el enlace firmado', !!enlace);
    await p.goto(`${URL}/mi-cuenta`, { waitUntil: 'load' });
    comprobar('sin confirmar, «Mi cuenta» vuelve a pedir la confirmación', p.url().includes('/verificar-correo'));
    await p.goto(enlace, { waitUntil: 'load' });
    await p.goto(`${URL}/mi-cuenta`, { waitUntil: 'load' });
    comprobar('confirmado: la cuenta queda en revisión de Colliers', (await p.content()).includes('Estamos revisando tu registro'));
    await p.context().close();
}

// ── 2. RUT repetido en cualquier formato y registro de persona jurídica ───────────────────────────────────
{
    const p = await nuevaPagina(1280);
    for (const formato of ['17998221-8', '21.345.678-4', '21345678-4']) { // el del seeder y el recién registrado, en otros formatos
        await llenarRegistro(p, { nombres: 'Duplicado', rut: formato, email: `dup${formato.replace(/[^0-9]/g, '')}@qa.test` });
        comprobar(`RUT repetido (${formato}): rechazado`, (await errorRut(p)).includes('Ya existe una cuenta con este RUT'), await errorRut(p));
    }
    const inicio = tamanoLog();
    await llenarRegistro(p, { nombres: 'Andrés', rut: '19.876.543-0', email: 'juridica@qa.test', juridica: true });
    comprobar('registro de persona jurídica con RUT con puntos, empresa y poder', p.url().includes('/verificar-correo'), p.url());
    await p.goto(enlaceDelCorreo(inicio, /http:\/\/127\.0\.0\.1:8000\/verificar-correo\/\d+\/[a-f0-9]+\?expires=\d+&(?:amp;)?signature=[a-f0-9]+/g), { waitUntil: 'load' });
    await p.context().close();
}

// ── 3. Ingreso con correo y con RUT; bloqueo tras 5 intentos que no se levanta vaciando la caché ─────────────
{
    const p = await nuevaPagina(1280);
    comprobar('ingreso con correo', (await ingresar(p, 'natural@qa.test', 'Clave-QA-2026')) === '' && p.url().endsWith('/mi-cuenta'), p.url());
    await p.context().clearCookies();
    comprobar('ingreso con RUT con puntos', (await ingresar(p, '21.345.678-4', 'Clave-QA-2026')) === '' && p.url().endsWith('/mi-cuenta'), p.url());
    await p.context().clearCookies();
    comprobar('ingreso con RUT sin puntos', (await ingresar(p, '213456784', 'Clave-QA-2026')) === '' && p.url().endsWith('/mi-cuenta'), p.url());
    await p.context().clearCookies();

    let ultimo = '';
    for (let i = 1; i <= 5; i++) {
        ultimo = await ingresar(p, 'natural@qa.test', 'incorrecta');
        if (i === 1) comprobar('intento fallido 1: avisa cuántos quedan', ultimo.includes('quedan 4 intentos'), ultimo);
    }
    comprobar('intento fallido 5: cuenta bloqueada 15 minutos', ultimo.includes('Bloqueamos la cuenta por seguridad durante 15 minutos'), ultimo);
    artisan('cache:clear');
    artisan('optimize:clear');
    const trasCache = await ingresar(p, 'natural@qa.test', 'Clave-QA-2026');
    comprobar('tras vaciar la caché sigue bloqueada, aun con la clave correcta', trasCache !== '' && !p.url().endsWith('/mi-cuenta'), trasCache);

    // ── 4. Recuperación de contraseña: levanta el bloqueo ──
    const inicio = tamanoLog();
    await p.goto(`${URL}/recuperar-clave`, { waitUntil: 'load' });
    await p.fill('input[name=email]', 'natural@qa.test');
    await p.getByRole('button', { name: 'Enviar enlace' }).click();
    await p.waitForLoadState('load');
    const enlace = enlaceDelCorreo(inicio, /http:\/\/127\.0\.0\.1:8000\/restablecer-clave\/[a-f0-9]+\?email=[^\s"<]+/g);
    comprobar('recuperar contraseña envía el enlace por correo', !!enlace);
    await p.goto(enlace, { waitUntil: 'load' });
    await p.fill('input[name=password]', 'Clave-Nueva-2026');
    await p.fill('input[name=password_confirmation]', 'Clave-Nueva-2026');
    await p.locator('button[type=submit]').first().click();
    await p.waitForLoadState('load');
    comprobar('la clave antigua ya no sirve', (await ingresar(p, 'natural@qa.test', 'Clave-QA-2026')) !== '');
    const conNueva = await ingresar(p, 'natural@qa.test', 'Clave-Nueva-2026');
    comprobar('con la clave nueva entra (bloqueo levantado)', conNueva === '' && p.url().endsWith('/mi-cuenta'), conNueva || p.url());
    await p.context().close();
}

// ── 5. Cambio obligatorio de la clave temporal de administración ───────────────────────────────────────────
{
    const salida = artisan('colliers:crear-usuario', 'admin', 'qa-admin@colliers.test', 'Admin', 'QA');
    const temporal = salida.match(/una sola vez\): (\S+)/)?.[1];
    comprobar('colliers:crear-usuario entrega una clave temporal', !!temporal);
    const p = await nuevaPagina(1280);
    await ingresar(p, 'qa-admin@colliers.test', temporal, true);
    await p.goto(`${URL}/admin/postores`, { waitUntil: 'load' });
    comprobar('con clave temporal el panel exige cambiarla', p.url().includes('/mi-cuenta/cambiar-clave'), p.url());
    await p.fill('input[name=current_password]', temporal);
    await p.fill('input[name=password]', 'Admin-QA-Nueva-2026');
    await p.fill('input[name=password_confirmation]', 'Admin-QA-Nueva-2026');
    await p.getByRole('button', { name: 'Guardar contraseña' }).click();
    await p.waitForLoadState('load');
    await p.goto(`${URL}/admin/postores`, { waitUntil: 'load' });
    comprobar('cambiada la clave, el panel abre', p.url().endsWith('/admin/postores'), p.url());

    // ── 6. Aprobar y rechazar cuentas desde «Cuentas por aprobar» ──
    await p.getByRole('tab', { name: 'Cuentas por aprobar' }).click();
    const fila = (texto) => p.locator('tbody tr', { hasText: texto });
    comprobar('las dos cuentas nuevas están por aprobar', (await fila('natural@qa.test').count()) === 1 && (await fila('juridica@qa.test').count()) === 1);
    await fila('natural@qa.test').getByRole('button', { name: 'Aprobar cuenta' }).click();
    await p.waitForTimeout(800);
    await fila('juridica@qa.test').locator('.admin-rechazar').click();
    await p.fill('.admin-modal textarea', 'Falta el poder notarial vigente');
    await p.locator('.admin-modal button[type=submit]').click();
    await p.waitForTimeout(800);
    const estado = (correo) => execFileSync('php', ['-r', `require 'vendor/autoload.php'; $app = require 'bootstrap/app.php'; $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); $u = App\\Models\\User::where('email', '${correo}')->first(); echo $u->postor->estado . '|' . $u->postor->motivo_rechazo;`], { encoding: 'utf8' });
    comprobar('cuenta natural aprobada en la base', estado('natural@qa.test').startsWith('aprobado|'), estado('natural@qa.test'));
    comprobar('cuenta jurídica rechazada con su motivo', estado('juridica@qa.test') === 'rechazado|Falta el poder notarial vigente', estado('juridica@qa.test'));
    await p.context().close();

    const q = await nuevaPagina(375);
    await ingresar(q, 'juridica@qa.test', 'Clave-QA-2026');
    await q.goto(`${URL}/mi-cuenta`, { waitUntil: 'load' });
    comprobar('el postor rechazado ve el motivo en «Mi cuenta»', (await q.content()).includes('Falta el poder notarial vigente'));
    await q.context().close();
}

await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
