// Recorrido de usabilidad: un administrador aprueba una garantía desde un teléfono (375×812, táctil).
// Criterio acordado para el panel: si esto no se puede hacer cómodamente, está mal.
// Verifica en cada paso que el objetivo esté visible en pantalla y mida al menos 44×44px,
// y que la página no tenga scroll horizontal.
import { chromium } from 'playwright';

const URL = (process.env.LARAVEL_URL || 'http://127.0.0.1:8000') + '/admin';
const navegador = await chromium.launch({ channel: 'chrome' });
const contexto = await navegador.newContext({ viewport: { width: 375, height: 812 }, hasTouch: true, isMobile: true, deviceScaleFactor: 2 });
const p = await contexto.newPage();
const errores = [];

async function tocar(locator, descripcion) {
    await locator.scrollIntoViewIfNeeded();
    const caja = await locator.boundingBox();
    if (!caja) throw new Error(`No visible: ${descripcion}`);
    const enPantalla = caja.x >= 0 && caja.x + caja.width <= 375;
    const tamanoOk = caja.width >= 44 && caja.height >= 44;
    console.log(`  ${tamanoOk && enPantalla ? '✓' : '✗'} ${descripcion} (${Math.round(caja.width)}×${Math.round(caja.height)})`);
    if (!tamanoOk) errores.push(`${descripcion}: área táctil ${Math.round(caja.width)}×${Math.round(caja.height)}`);
    if (!enPantalla) errores.push(`${descripcion}: fuera del ancho de pantalla`);
    await locator.tap();
}

async function sinScrollHorizontal(paso) {
    const desborde = await p.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
    if (desborde) errores.push(`Scroll horizontal en: ${paso}`);
}

console.log('Recorrido: aprobar una garantía a 375px');
await p.goto(URL, { waitUntil: 'load' });
await sinScrollHorizontal('dashboard');

await tocar(p.getByRole('button', { name: 'Abrir menú' }), 'Botón de menú');
await p.waitForTimeout(300);
await tocar(p.locator('#admin-menu').getByRole('link', { name: /Postores/ }), 'Enlace Postores en el menú');
await p.waitForURL('**/admin/postores');
await sinScrollHorizontal('postores');

const tarjeta = p.locator('.admin-tarjeta').filter({ hasText: 'Inversiones Andes SpA' });
const estadoAntes = (await tarjeta.locator('.badge-admin').nth(1).textContent()).trim();
console.log(`  · Estado inicial: ${estadoAntes}`);
await tocar(tarjeta.getByRole('button', { name: 'Aprobar garantía' }), 'Aprobar garantía (tarjeta)');
const estadoDespues = (await tarjeta.locator('.badge-admin').nth(1).textContent()).trim();
console.log(`  · Estado final: ${estadoDespues}`);
if (estadoDespues !== 'GARANTÍA APROBADA') errores.push(`La garantía no quedó aprobada (estado: ${estadoDespues})`);

// Camino alternativo: desde la ficha a pantalla completa
const otra = p.locator('.admin-tarjeta').filter({ hasText: 'Camila Ortiz Vera' });
await tocar(otra.getByRole('button', { name: 'Ver ficha' }), 'Ver ficha');
const ficha = p.getByRole('dialog');
const cajaFicha = await ficha.locator('.admin-ficha__caja').boundingBox();
if (!cajaFicha || cajaFicha.width < 370) errores.push('La ficha no ocupa la pantalla completa');
await tocar(ficha.getByRole('button', { name: 'Aprobar garantía' }), 'Aprobar garantía (ficha)');
const estadoFicha = (await otra.locator('.badge-admin').nth(1).textContent()).trim();
console.log(`  · Estado tras aprobar desde la ficha: ${estadoFicha}`);
if (estadoFicha !== 'GARANTÍA APROBADA') errores.push(`La garantía (ficha) no quedó aprobada (estado: ${estadoFicha})`);
await sinScrollHorizontal('postores tras aprobar');

await navegador.close();
if (errores.length) {
    console.log('\nFALLA:\n- ' + errores.join('\n- '));
    process.exit(1);
}
console.log('\nOK: el recorrido se completa en móvil sin problemas de usabilidad.');
