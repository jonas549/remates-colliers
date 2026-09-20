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
await p.getByRole('button', { name: 'Crear subasta', exact: true }).click();
await p.fill('input[name=direccion]', 'Av. Italia 1180, Depto. 402');
await p.fill('input[name=comuna]', 'Ñuñoa');
await p.selectOption('select[name=tipo_propiedad]', 'Departamento');
await p.fill('input[name=superficie_util]', '71,5');
await p.fill('input[name=precio_base]', '98000000');
await p.fill('input[name=porcentaje_garantia]', '10');
comprobar('el resumen muestra la garantía calculada (10 %)', (await p.locator('.admin-form__resumen').first().innerText()).includes('garantía $9.800.000'));
// QA del 20/09, punto 4: qué significa cada fecha y qué va a pasar, dicho en palabras.
const ayudas = (await p.locator('.admin-form__ayuda').allInnerTexts()).join(' | ');
comprobar('las fechas del formulario explican qué son',
    ayudas.includes('abre el primer lote') && ayudas.includes('antes') && ayudas.includes('sin extensiones'), ayudas.slice(0, 160));
const inicio = new Date(Date.now() + 3 * 86400000);
const local = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
await p.fill('input[name=inicio_en]', local(inicio));
const plan = await p.locator('.admin-form__resumen--plan').innerText();
comprobar('el resumen dice en palabras qué va a pasar con esas fechas',
    plan.includes('inscribirse y constituir la garantía hasta el') && plan.includes('El remate abre el') && plan.includes('sin extensiones'), plan.slice(0, 180));
// El cierre de garantías después del inicio deja a todos fuera: el formulario lo advierte antes de publicar.
await p.fill('input[name=cierre_garantias_en]', local(new Date(inicio.getTime() + 3600000)));
comprobar('avisa si el cierre de garantías queda después del inicio',
    (await p.locator('.admin-form__resumen--plan').innerText()).includes('nadie alcanzaría a habilitarse'));
await p.fill('input[name=cierre_garantias_en]', '');
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
await p.waitForSelector('.admin-modal__texto--destacado:visible');
const avisoCierre = await p.locator('.admin-modal__texto--destacado:visible').innerText();
comprobar('el modal de cierre anticipado dice a quién se adjudica y por cuánto',
    avisoCierre.includes('María Paz González Soto (Postor #1)') && avisoCierre.includes('$198.500.000'), avisoCierre);
comprobar('con pujas, el modal no muestra el texto de lote desierto',
    !(await p.locator('.admin-modal__texto--destacado', { hasText: 'Nadie ha pujado' }).isVisible()));
comprobar('el modal avisa que no se puede deshacer',
    (await p.locator('.admin-modal__cuerpo').innerText()).includes('no se puede deshacer'));
await p.fill('textarea[x-model=motivo]', 'Instrucción del mandante');
await p.getByRole('button', { name: 'Cerrar lote', exact: true }).click();
await p.waitForFunction(() => document.querySelector('.admin-vivo__cabeza')?.textContent.includes('ADJUDICADO'), null, { timeout: 15000 }).catch(() => {});
comprobar('cierre anticipado: el panel pasa a ADJUDICADO sin recargar', (await p.locator('.admin-vivo__cabeza').innerText()).includes('ADJUDICADO'));
await p.screenshot({ path: path.join(SALIDA, 'en-vivo-1440.png'), fullPage: true });

// ── «Cerrar sesión» legible también con el mouse encima (el hover lo pintaba del color del fondo) ──────
const salir = p.locator('.admin-lateral__salir');
const colores = async () => p.evaluate(() => {
    const b = document.querySelector('.admin-lateral__salir');
    const fondo = getComputedStyle(document.querySelector('.admin-lateral')).backgroundColor;
    return { texto: getComputedStyle(b).color, fondo };
});
const normal = await colores();
await salir.hover();
const encima = await colores();
comprobar('«Cerrar sesión» se sigue viendo con el mouse encima',
    encima.texto !== encima.fondo && encima.texto !== normal.fondo, JSON.stringify(encima));

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

// ── Botón «Crear subasta de prueba»: deja todo listo para probar sin llenar el formulario ──────────────
await p.goto(`${URL}/admin/subastas`, { waitUntil: 'load' });
comprobar('el listado ofrece crear una subasta de prueba', await p.getByRole('button', { name: 'Crear subasta de prueba' }).isVisible());
await p.fill('.admin-pruebas__acciones input[name=inicio]', '12');
await p.fill('.admin-pruebas__acciones input[name=lotes]', '2');
await p.fill('.admin-pruebas__acciones input[name=postores]', '3');
await p.getByRole('button', { name: 'Crear subasta de prueba' }).click();
await p.waitForSelector('.admin-pruebas--credenciales');
const credenciales = await p.locator('.admin-pruebas--credenciales').innerText();
const correoPrueba = credenciales.match(/\S+@demo\.colliers\.test/)?.[0];
const clavePrueba = credenciales.match(/solo se muestra ahora[^:]*:\s*(\S+)/)?.[1];
comprobar('la subasta de prueba se crea publicada y muestra la clave una vez',
    (await p.locator('.admin-aviso--ok').innerText()).includes('creada y publicada') && !!correoPrueba && !!clavePrueba,
    `${correoPrueba} · clave ${clavePrueba ? 'sí' : 'no'}`);
comprobar('la ficha de la prueba trae sus lotes programados',
    (await p.locator('.admin-gestion__bloque table tbody tr').count()) === 2);
await p.screenshot({ path: path.join(SALIDA, 'subasta-de-prueba-1440.png'), fullPage: true });

// Un postor de prueba entra con esa clave y llega a la sala del remate.
const cp = await navegador.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-CL' });
const q = await cp.newPage();
await q.goto(`${URL}/ingresar`, { waitUntil: 'load' });
await q.fill('input[name=usuario]', correoPrueba);
await q.fill('input[name=password]', clavePrueba);
await q.getByRole('button', { name: 'Ingresar' }).click();
await q.waitForLoadState('load');
comprobar('el postor de prueba entra con la clave que mostró el panel', q.url().endsWith('/mi-cuenta'), q.url());
const salaPrueba = await p.locator('.admin-pruebas--credenciales a', { hasText: 'sala de pujas' }).getAttribute('href');
await q.goto(salaPrueba, { waitUntil: 'load' });
const textoSala = (await q.locator('body').innerText()).replace(/\s+/g, ' ');
comprobar('y entra a la sala del remate de prueba', q.url().includes('/sala') && /PUJA|Lote/i.test(textoSala),
    q.url() + ' · ' + textoSala.slice(0, 140));
await cp.close();

// La franja de pruebas también se usa desde el celular.
{
    const cmovil = await navegador.newContext({ viewport: { width: 375, height: 800 }, locale: 'es-CL' });
    const r = await cmovil.newPage();
    await r.goto(`${URL}/revision/entrar/admin?a=/admin/subastas`, { waitUntil: 'load' });
    await r.waitForTimeout(400);
    const u = await revisarUsabilidad(r, 375);
    comprobar('la franja de subastas de prueba se usa a 375px (sin desborde, táctiles >= 44)',
        !u.desborde && u.pequenos.length === 0, `desborde:${u.desborde} ${u.pequenos.slice(0, 3).join('; ')}`);
    await r.screenshot({ path: path.join(SALIDA, 'pruebas-375.png'), fullPage: true });
    await cmovil.close();
}

// El sitio público no la muestra y el listado real tampoco.
const publico = await p.goto(`${URL}/`, { waitUntil: 'load' });
comprobar('la subasta de prueba no aparece en el sitio público',
    publico.status() === 200 && !(await p.locator('body').innerText()).includes('PRUEBA ·'));

// Y se borra con sus postores desde la misma franja.
await p.goto(`${URL}/admin/subastas`, { waitUntil: 'load' });
p.on('dialog', (d) => d.accept().catch(() => {}));
await p.locator('.admin-pruebas__tabla').getByRole('button', { name: 'Borrar' }).first().click();
await p.waitForSelector('.admin-aviso--ok');
comprobar('la subasta de prueba se borra con sus postores',
    (await p.locator('.admin-aviso--ok').innerText()).includes('borrada con sus postores')
    && (await p.locator('.admin-pruebas__tabla').count()) === 0);

// ── El martillero trabaja con sus remates (antes el listado le ofrecía los de otros y devolvía 403) ────
const cm = await navegador.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-CL', timezoneId: 'America/Santiago' });
const m = await cm.newPage();
await m.goto(`${URL}/revision/entrar/martillero?usuario=mossandon@colliers.test&a=/admin/subastas`, { waitUntil: 'load' });
await m.waitForSelector('.admin-tabla--subastas tbody tr');
const suyos = (await m.locator('.admin-tabla--subastas tbody tr').allInnerTexts()).join(' | ');
comprobar('el listado del martillero solo trae sus remates', suyos.includes('Apoquindo') && !suyos.includes('Camino El Alba'), suyos.slice(0, 160));
comprobar('el martillero no ve «Crear subasta»', !(await m.locator('.admin-encabezado').innerText()).includes('Crear subasta'));

// Apoquindo (id 1) es suyo; a esta altura del recorrido ya lo cerró la administración, así que se abre por URL.
const panelSuyo = await m.goto(`${URL}/admin/subastas/1/en-vivo`, { waitUntil: 'load' });
await m.waitForSelector('.admin-vivo__precio');
comprobar('el martillero entra al panel en vivo de su remate',
    panelSuyo.status() === 200 && (await m.locator('.admin-vivo__precio').innerText()).startsWith('$'));

// La ficha de su remate: los mismos datos, sin formulario ni acciones de administración.
await m.goto(`${URL}/admin/subastas/1`, { waitUntil: 'load' });
const ficha = await m.locator('.admin-gestion').innerText();
comprobar('el martillero ve la ficha de su remate en modo lectura',
    ficha.includes('Estás a cargo de este remate') && !ficha.includes('Guardar datos') && !ficha.includes('Subir documento'), ficha.slice(0, 120));
await m.screenshot({ path: path.join(SALIDA, 'martillero-ficha-1440.png'), fullPage: true });

// Un remate de otro martillero: 403 que explica de quién es y dónde están los suyos.
const ajeno = await m.goto(`${URL}/admin/subastas/3`, { waitUntil: 'load' });
const texto403 = await m.locator('body').innerText();
comprobar('el remate de otro martillero responde 403 y dice por qué',
    ajeno.status() === 403 && texto403.includes('está a cargo de') && texto403.includes('los remates asignados a ti'),
    `HTTP ${ajeno.status()} · ${texto403.slice(0, 140).replace(/\s+/g, ' ')}`);
await m.screenshot({ path: path.join(SALIDA, 'martillero-403-1440.png'), fullPage: true });
await cm.close();

await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
