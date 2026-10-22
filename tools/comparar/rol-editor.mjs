// Recorrido del rol Editor en navegador real (22/10): entra por el acceso de administración con clics, ve solo
// «Subastas» y «Configuración → Sitio», edita y despublica un remate sin inscritos, y no llega a nada más aunque
// escriba la dirección (eso último es lo único que escribe URL: comprueba la protección, no el uso normal).
//
// Requiere `php artisan serve` (APP_ENV=local). REINICIA la base local con el seeder.
//   node tools/comparar/rol-editor.mjs
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';
import { revisarUsabilidad } from './usabilidad.mjs';

const URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const SALIDA = path.resolve('tools/comparar/salida/rol-editor');
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
p.on('dialog', (d) => d.accept());

await p.goto(URL, { waitUntil: 'load' });
await p.locator('.pub-cabecera__acceso', { hasText: 'Ingresar' }).click();
await p.getByRole('link', { name: 'Acceso administradores' }).click();
await p.fill('input[name=usuario]', 'editor@colliers.test');
await p.fill('input[name=password]', 'colliers-local-2026');
await p.getByRole('button', { name: 'Ingresar' }).click();
await p.waitForLoadState('load');
comprobar('editor: entra con clics y llega a «Subastas» (no tiene dashboard)', p.url().endsWith('/admin/subastas'), p.url());

const menu = (await p.locator('.admin-lateral__menu').innerText()).replace(/\s+/g, ' ');
comprobar('editor: el menú tiene Subastas y Configuración', menu.includes('Subastas') && menu.includes('Configuración'), menu);
comprobar('editor: el menú no tiene Dashboard, Postores, Reportes ni Usuarios', !/Dashboard|Postores|Reportes|Usuarios/.test(menu), menu);
comprobar('editor: el pie del menú dice su rol', (await p.locator('.admin-lateral__rol').innerText()).includes('Editor'));
const tabla = await p.locator('.admin-tabla').first().innerText();
comprobar('editor: el listado no ofrece «Panel en vivo» ni «Cerrar ahora»', !tabla.includes('Panel en vivo') && !tabla.includes('Cerrar ahora'));
await p.screenshot({ path: path.join(SALIDA, 'subastas-1440.png'), fullPage: true });

// Remate del seeder sin inscritos: Pedro de Valdivia (R-2026-122). Se elige por folio visible.
const fila = p.locator('.admin-tabla tbody tr', { hasText: 'R-2026-122' });
await fila.getByRole('link', { name: 'Editar' }).click();
await p.waitForLoadState('load');
const acciones = await p.locator('.admin-encabezado__acciones').innerText();
comprobar('editor: la ficha ofrece Despublicar y no Cancelar ni Panel en vivo',
    acciones.includes('Despublicar') && !acciones.includes('Cancelar remate') && !acciones.includes('Panel en vivo'), acciones.replace(/\s+/g, ' '));
await p.getByRole('button', { name: 'Despublicar' }).click();
await p.waitForSelector('.admin-aviso--ok');
comprobar('editor: despublica y la ficha queda en borrador con «Publicar»',
    (await p.locator('.admin-aviso--ok').innerText()).includes('despublicado') && (await p.getByRole('button', { name: 'Publicar' }).count()) === 1);
await p.getByRole('button', { name: 'Publicar' }).click();
await p.waitForSelector('.admin-aviso--ok');
comprobar('editor: lo vuelve a publicar', (await p.locator('.admin-aviso--ok').innerText()).includes('publicado'));

// Configuración → Sitio, con clics.
await p.locator('.admin-lateral__item', { hasText: 'Configuración' }).click();
await p.waitForLoadState('load');
const submenu = (await p.locator('.admin-lateral__submenu').innerText()).replace(/\s+/g, ' ').trim();
comprobar('editor: Configuración abre «Sitio» y el submenú no ofrece otras secciones', p.url().endsWith('/admin/configuracion/sitio') && submenu === 'Sitio', submenu);
await p.fill('input[name="config[contacto_telefono]"]', '+56 2 2760 3536');
await p.getByRole('button', { name: 'Guardar cambios' }).click();
comprobar('editor: guarda un texto del sitio', (await p.locator('.admin-aviso--ok').innerText()).includes('Configuración guardada'));

// Lo que no le corresponde responde 403 aunque escriba la dirección.
for (const ruta of ['/admin/postores', '/admin/reportes', '/admin/usuarios', '/admin/configuracion/garantias', '/admin/configuracion/plantillas']) {
    const r = await p.goto(URL + ruta, { waitUntil: 'load' });
    comprobar(`editor: ${ruta} responde 403`, r.status() === 403, String(r.status()));
}

// Móvil: menú deslizable con los mismos dos ítems.
const movil = await navegador.newContext({ viewport: { width: 375, height: 800 }, locale: 'es-CL', storageState: await contexto.storageState() });
const m = await movil.newPage();
await m.goto(URL + '/admin/subastas', { waitUntil: 'load' });
await m.getByRole('button', { name: 'Abrir menú' }).click();
await m.waitForSelector('.admin-lateral.es-abierta');
await m.waitForTimeout(500);
const menuMovil = (await m.locator('.admin-lateral__menu').textContent()).replace(/\s+/g, ' ');
comprobar('editor 375 px: el menú deslizable tiene solo Subastas y Configuración', menuMovil.includes('Subastas') && menuMovil.includes('Configuración') && !/Dashboard|Postores|Reportes|Usuarios/.test(menuMovil), menuMovil);
await m.getByRole('button', { name: 'Cerrar menú' }).click();
const usabilidad = await revisarUsabilidad(m);
comprobar('editor 375 px: sin desborde horizontal', !usabilidad.desborde, JSON.stringify(usabilidad).slice(0, 160));
await m.screenshot({ path: path.join(SALIDA, 'subastas-375.png'), fullPage: true });

await navegador.close();
console.log(fallas === 0 ? '\nRol Editor: todo OK' : `\nRol Editor: ${fallas} falla(s)`);
process.exit(fallas === 0 ? 0 : 1);
