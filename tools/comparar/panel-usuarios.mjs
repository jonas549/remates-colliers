// QA en navegador real de «06 Usuarios» (usuarios internos del panel; QA del 20/09, punto 2):
// crear un martillero con clave temporal, entrar con esa clave, editar, desactivar y volver a activar.
//
//   php artisan serve      (en otra terminal)
//   node tools/comparar/panel-usuarios.mjs

import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';
import { revisarUsabilidad } from './usabilidad.mjs';

const URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const SALIDA = path.resolve('tools/comparar/salida/panel-usuarios');
fs.mkdirSync(SALIDA, { recursive: true });
let fallas = 0;
const comprobar = (nombre, ok, detalle = '') => {
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre}${detalle ? '  (' + detalle + ')' : ''}`);
};

console.log('Preparando la base local (migrate:fresh --seed)…');
execFileSync('php', ['tools/comparar/sala-ayudante.php', 'reiniciar'], { encoding: 'utf8' });

const navegador = await chromium.launch({ channel: 'chrome' });
const contexto = await navegador.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-CL', timezoneId: 'America/Santiago' });
const p = await contexto.newPage();

// ── Alta de un martillero ─────────────────────────────────────────────────────────────────────────────
await p.goto(`${URL}/revision/entrar/admin?a=/admin/usuarios`, { waitUntil: 'load' });
comprobar('«06 Usuarios» está en el menú lateral', (await p.locator('.admin-lateral__item', { hasText: 'Usuarios' }).count()) === 1);
const filasIniciales = await p.locator('.admin-tabla tbody tr').count();
comprobar('el listado trae los usuarios internos y ningún postor', filasIniciales > 0
    && !(await p.locator('.admin-tabla').innerText()).includes('@correo.test'), `${filasIniciales} filas`);

await p.getByRole('button', { name: 'Crear usuario' }).click();
await p.fill('input[name=name]', 'R. Prueba Martillero');
await p.fill('input[name=email]', 'rprueba@colliers.test');
await p.selectOption('select[name=rol]', 'martillero');
await p.getByRole('button', { name: 'Crear usuario', exact: true }).last().click();
await p.waitForSelector('.admin-aviso--ok');
const claveTexto = await p.locator('.admin-pruebas--credenciales').innerText();
// La clave va seguida de un punto en la frase: se captura solo lo alfanumérico.
const clave = claveTexto.match(/solo se muestra ahora[^:]*:\s*([A-Za-z0-9]{10,})/)?.[1];
comprobar('el usuario se crea y la clave temporal se muestra una sola vez',
    (await p.locator('.admin-aviso--ok').innerText()).includes('creado') && !!clave, clave ? 'clave entregada' : claveTexto.slice(0, 120));
await p.screenshot({ path: path.join(SALIDA, 'usuarios-1440.png'), fullPage: true });

// La clave temporal sirve y obliga a cambiarla.
const c2 = await navegador.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-CL' });
const q = await c2.newPage();
await q.goto(`${URL}/admin/ingresar`, { waitUntil: 'load' });
await q.fill('input[name=usuario]', 'rprueba@colliers.test');
await q.fill('input[name=password]', clave);
await q.getByRole('button', { name: 'Ingresar' }).click();
await q.waitForLoadState('load');
comprobar('con la clave temporal entra y le exige cambiarla', q.url().includes('/mi-cuenta/cambiar-clave'), q.url());
await c2.close();

// El martillero nuevo ya se puede asignar a un remate.
await p.goto(`${URL}/admin/subastas`, { waitUntil: 'load' });
await p.getByRole('button', { name: 'Crear subasta', exact: true }).click();
comprobar('el martillero nuevo aparece para asignarlo a un remate',
    (await p.locator('select[name=martillero_id]').innerText()).includes('R. Prueba Martillero'));

// ── Editar, desactivar y activar ──────────────────────────────────────────────────────────────────────
await p.goto(`${URL}/admin/usuarios`, { waitUntil: 'load' });
const fila = p.locator('tr', { hasText: 'rprueba@colliers.test' });
await fila.getByRole('button', { name: 'Editar' }).click();
await p.waitForSelector('.admin-form__titulo');
await p.fill('.admin-form input[name=name]', 'R. Prueba Editado');
await p.getByRole('button', { name: 'Guardar cambios' }).click();
await p.waitForSelector('.admin-aviso--ok');
comprobar('editar el nombre se guarda', (await p.locator('.admin-tabla').innerText()).includes('R. Prueba Editado'));

p.on('dialog', (d) => d.accept().catch(() => {}));
await p.locator('tr', { hasText: 'rprueba@colliers.test' }).getByRole('button', { name: 'Desactivar' }).click();
await p.waitForSelector('.admin-aviso--ok');
comprobar('desactivar deja la cuenta sin acceso',
    (await p.locator('.admin-aviso--ok').innerText()).includes('sin acceso')
    && (await p.locator('tr', { hasText: 'rprueba@colliers.test' }).innerText()).includes('INACTIVO'));

// Desactivado no entra al panel.
const c3 = await navegador.newContext({ viewport: { width: 1280, height: 800 }, locale: 'es-CL' });
const r = await c3.newPage();
await r.goto(`${URL}/admin/ingresar`, { waitUntil: 'load' });
await r.fill('input[name=usuario]', 'rprueba@colliers.test');
await r.fill('input[name=password]', clave);
await r.getByRole('button', { name: 'Ingresar' }).click();
await r.waitForLoadState('load');
comprobar('la cuenta desactivada no entra al panel', !r.url().includes('/admin/subastas') && !r.url().endsWith('/admin'), r.url());
await c3.close();

await p.locator('tr', { hasText: 'rprueba@colliers.test' }).getByRole('button', { name: 'Activar' }).click();
await p.waitForSelector('.admin-aviso--ok');
comprobar('activar devuelve el acceso',
    (await p.locator('.admin-aviso--ok').innerText()).includes('vuelve a tener acceso')
    && (await p.locator('tr', { hasText: 'rprueba@colliers.test' }).innerText()).includes('ACTIVO'));

// El propio administrador no puede desactivarse.
// El «(tú)» va en un span oculto por x-show en las demás filas: se ubica por el correo del administrador del seeder.
const propia = p.locator('tr', { hasText: 'admin@colliers.test' });
await propia.getByRole('button', { name: 'Desactivar' }).click();
await p.waitForSelector('.admin-aviso, .admin-avisos');
comprobar('no puede desactivar su propia cuenta',
    (await p.locator('.admin-avisos').innerText()).includes('No puedes desactivar tu propia cuenta'));

// ── Usabilidad en todos los anchos ────────────────────────────────────────────────────────────────────
for (const ancho of [375, 760, 1120, 1440]) {
    const c = await navegador.newContext({ viewport: { width: ancho, height: 900 }, locale: 'es-CL' });
    const u = await c.newPage();
    await u.goto(`${URL}/revision/entrar/admin?a=/admin/usuarios`, { waitUntil: 'load' });
    await u.waitForTimeout(300);
    const revision = await revisarUsabilidad(u, ancho);
    comprobar(`usuarios a ${ancho}px: sin desborde, táctiles ≥ 44`, !revision.desborde && revision.pequenos.length === 0,
        `desborde:${revision.desborde} ${revision.pequenos.slice(0, 3).join('; ')}`);
    await u.screenshot({ path: path.join(SALIDA, `usuarios-${ancho}.png`), fullPage: true });
    await c.close();
}

await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
