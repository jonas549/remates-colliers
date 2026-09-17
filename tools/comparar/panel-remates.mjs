// Bloque I en navegador real: crear, completar, publicar y cerrar remates desde el panel, y panel del martillero.
// Requiere `php artisan serve` (APP_ENV=local). Reinicia la base local con el seeder.
//
//   node tools/comparar/panel-remates.mjs
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';
import { revisarUsabilidad } from './usabilidad.mjs';

const URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const SALIDA = path.resolve('tools/comparar/salida/panel-remates');
let fallas = 0;
const comprobar = (nombre, ok, detalle = '') => {
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre}${detalle ? '  (' + detalle + ')' : ''}`);
};

console.log('Preparando la base local (migrate:fresh --seed)…');
execFileSync('php', ['tools/comparar/sala-ayudante.php', 'reiniciar'], { encoding: 'utf8' });
fs.mkdirSync(SALIDA, { recursive: true });

// Un PDF mínimo válido para subir como documento.
const pdf = path.join(os.tmpdir(), 'bases-colliers.pdf');
fs.writeFileSync(pdf, '%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj 3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 200 200]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n');

const navegador = await chromium.launch({ channel: 'chrome' });
const contexto = await navegador.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-CL', timezoneId: 'America/Santiago' });
const p = await contexto.newPage();
p.on('dialog', (d) => d.accept());
await p.goto(`${URL}/revision/entrar/admin?a=/admin/subastas`, { waitUntil: 'load' });

// ── Crear con el formulario del diseño ──────────────────────────────────────────────────────────────────
await p.getByRole('button', { name: 'Crear subasta' }).click();
await p.fill('input[name=direccion]', 'Av. Italia 1180, Depto. 402');
await p.fill('input[name=comuna]', 'Ñuñoa');
await p.selectOption('select[name=tipo_propiedad]', 'Departamento');
await p.fill('input[name=superficie_util]', '71,5');
await p.fill('input[name=precio_base]', '98000000');
await p.fill('input[name=porcentaje_garantia]', '10');
comprobar('el resumen muestra la garantía calculada (10 %)', (await p.locator('.admin-form__resumen').innerText()).includes('garantía $9.800.000'));
const inicio = new Date(Date.now() + 3 * 86400000);
const local = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
await p.fill('input[name=inicio_en]', local(inicio));
await p.selectOption('select[name=martillero_id]', { label: 'M. Ossandón' });
await p.getByRole('button', { name: 'Publicar subasta' }).click();
await p.waitForURL(/\/admin\/subastas\/\d+$/);
comprobar('publicada desde el formulario y redirige a la ficha', (await p.locator('.admin-aviso--ok').innerText()).includes('publicado'));
comprobar('la ficha muestra el estado PRÓXIMA', (await p.locator('.admin-encabezado__kicker').innerText()).includes('PRÓXIMA'));
const fichaUrl = p.url();

// ── Documento, segundo lote, foto y visita ─────────────────────────────────────────────────────────────
await p.fill('input[name=titulo][placeholder="Bases especiales del remate"]', 'Bases especiales');
await p.setInputFiles('input[name=archivo]', pdf);
await p.getByRole('button', { name: 'Subir documento' }).click();
comprobar('documento subido', (await p.locator('.admin-aviso--ok').innerText()).includes('Documento agregado'));

await p.getByRole('link', { name: 'Agregar lote →' }).click();
await p.fill('input[name=direccion]', 'Av. Italia 1180, Estacionamiento 14');
await p.fill('input[name=comuna]', 'Ñuñoa');
await p.selectOption('select[name=tipo_propiedad]', 'Bodega');
await p.fill('input[name=precio_base]', '8.500.000');
await p.fill('input[name="atributos[mandante]"]', 'Banco Consorcio');
await p.getByRole('button', { name: 'Crear lote' }).click();
comprobar('segundo lote creado', (await p.locator('.admin-aviso--ok').innerText()).includes('Lote 2 guardado'));
await p.setInputFiles('input[name="imagenes[]"]', path.resolve('public/img/demo/prop-montt.jpg'));
await p.getByRole('button', { name: 'Subir fotos' }).click();
comprobar('foto subida y visible', (await p.locator('.admin-foto img').count()) === 1 && (await p.locator('.admin-foto__principal').isVisible()));
const manana = new Date(Date.now() + 86400000);
await p.fill('input[name=inicia_en]', local(manana));
await p.fill('input[name=termina_en]', local(new Date(manana.getTime() + 2 * 3600000)));
await p.getByRole('button', { name: 'Agregar horario' }).click();
comprobar('horario de visita agregado', (await p.locator('.admin-aviso--ok').innerText()).includes('Horario de visita agregado'));
await p.screenshot({ path: path.join(SALIDA, 'lote-1440.png'), fullPage: true });

await p.goto(fichaUrl, { waitUntil: 'load' });
const filas = await p.locator('.admin-gestion__bloque table tbody tr').allInnerTexts();
comprobar('la ficha lista los dos lotes con horario encadenado', filas.length === 2 && filas[1].includes('Estacionamiento 14'), filas.join(' / '));
await p.screenshot({ path: path.join(SALIDA, 'ficha-1440.png'), fullPage: true });

// Reordenar: el segundo lote sube al primer lugar y los horarios se reprograman.
const abreAntes = (await p.locator('.admin-gestion__bloque table tbody tr').nth(0).locator('td').nth(4).innerText()).trim();
await p.getByRole('button', { name: 'Subir lote 2' }).click();
await p.waitForSelector('.admin-aviso--ok');
comprobar('reordenar lotes', (await p.locator('.admin-aviso--ok').innerText()).includes('Orden de los lotes actualizado'));
const filasOrden = await p.locator('.admin-gestion__bloque table tbody tr').allInnerTexts();
const abreDespues = (await p.locator('.admin-gestion__bloque table tbody tr').nth(0).locator('td').nth(4).innerText()).trim();
comprobar('el lote movido queda primero y abre a la hora de inicio', filasOrden[0].startsWith('1. Av. Italia 1180') && filasOrden[1].startsWith('2. ') && abreDespues === abreAntes,
    `${filasOrden.map((f) => f.split('\n')[0]).join(' / ')} · abre ${abreAntes} → ${abreDespues}`);

// ── Cancelar un próximo desde el listado ───────────────────────────────────────────────────────────────
await p.goto(`${URL}/admin/subastas`, { waitUntil: 'load' });
const filaElAlba = p.locator('tr', { hasText: 'Camino El Alba' });
await filaElAlba.getByRole('button', { name: 'Cerrar ahora' }).click();
comprobar('para un próximo el modal ofrece cancelar', (await p.locator('#titulo-cierre').innerText()) === 'Cancelar el remate');
await p.fill('textarea[name=motivo]', 'Retiro de la propiedad por el mandante');
await p.getByRole('button', { name: 'Cancelar remate' }).click();
comprobar('remate cancelado desde el listado', (await p.locator('.admin-aviso--ok').innerText()).includes('cancelado'));

// ── Panel del martillero sobre el remate en vivo del seeder ────────────────────────────────────────────
await p.goto(`${URL}/admin/subastas`, { waitUntil: 'load' });
await p.locator('tr', { hasText: 'Av. Apoquindo 4501' }).getByRole('link', { name: 'Panel en vivo' }).click();
await p.waitForSelector('.admin-vivo__precio');
comprobar('panel en vivo: precio del seeder y ganador con identidad', (await p.locator('.admin-vivo__precio').innerText()) === '$198.500.000'
    && (await p.locator('.admin-vivo__ganador').innerText()).includes('María Paz González Soto (Postor #1)'));
await p.fill('textarea[x-model=mensaje]', 'Quedan pocos minutos');
await p.getByRole('button', { name: 'Publicar mensaje' }).click();
await p.waitForSelector('.admin-aviso');
comprobar('mensaje publicado a la sala', (await p.locator('.admin-aviso').innerText()).includes('Mensaje publicado'));
await p.getByRole('button', { name: 'Cerrar este lote ahora' }).click();
await p.fill('textarea[x-model=motivo]', 'Instrucción del mandante');
await p.getByRole('button', { name: 'Cerrar lote', exact: true }).click();
await p.waitForFunction(() => document.querySelector('.admin-vivo__cabeza')?.textContent.includes('ADJUDICADO'), null, { timeout: 15000 }).catch(() => {});
comprobar('cierre anticipado: el panel pasa a ADJUDICADO sin recargar', (await p.locator('.admin-vivo__cabeza').innerText()).includes('ADJUDICADO'));
await p.screenshot({ path: path.join(SALIDA, 'en-vivo-1440.png'), fullPage: true });

// ── Dashboard con datos reales ─────────────────────────────────────────────────────────────────────────
await p.goto(`${URL}/admin`, { waitUntil: 'load' });
const actividad = await p.locator('.admin-actividad__texto').allInnerTexts();
comprobar('dashboard: actividad real (cierre anticipado y publicación)', actividad.some((t) => t.includes('cerró anticipadamente R-2026-114')) && actividad.some((t) => t.includes('Se publicó el remate')), actividad.join(' / '));

// ── Usabilidad en todos los anchos ─────────────────────────────────────────────────────────────────────
const idRemate = fichaUrl.split('/').pop();
const rutas = [
    ['dashboard', '/admin'], ['subastas', '/admin/subastas'], ['ficha', `/admin/subastas/${idRemate}`],
    ['lote', `/admin/subastas/${idRemate}/lotes/nuevo`], ['en-vivo', `/admin/subastas/1/en-vivo`],
];
for (const ancho of [375, 760, 1120, 1440]) {
    const c = await navegador.newContext({ viewport: { width: ancho, height: 900 }, locale: 'es-CL' });
    const q = await c.newPage();
    await q.goto(`${URL}/revision/entrar/admin`, { waitUntil: 'load' });
    for (const [nombre, ruta] of rutas) {
        const r = await q.goto(URL + ruta, { waitUntil: 'load' });
        await q.waitForTimeout(400);
        const u = await revisarUsabilidad(q, ancho);
        comprobar(`${nombre} a ${ancho}px: HTTP 200, sin desborde, táctiles ≥ 44`, r.status() === 200 && !u.desborde && u.pequenos.length === 0,
            `HTTP ${r.status()} desborde:${u.desborde} ${u.pequenos.slice(0, 4).join('; ')}`);
        await q.screenshot({ path: path.join(SALIDA, `${nombre}-${ancho}.png`), fullPage: true });
    }
    // Móvil: la hoja de acciones del listado lleva a la ficha.
    if (ancho === 375) {
        await q.goto(`${URL}/admin/subastas`, { waitUntil: 'load' });
        await q.locator('.admin-acciones-menu__boton').first().click();
        comprobar('375px: la hoja de acciones ofrece Editar', await q.locator('.admin-acciones-menu__lista .admin-acciones-menu__enlace', { hasText: 'Editar' }).isVisible());
    }
    await c.close();
}

await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
