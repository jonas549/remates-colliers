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

console.log('Recorrido: aprobar cuentas y garantías reales a 375px (base local sembrada)');
await p.goto(URL.replace('/admin', '/revision/entrar/admin'), { waitUntil: 'load' });
await p.goto(URL, { waitUntil: 'load' });
await sinScrollHorizontal('dashboard');

await tocar(p.getByRole('button', { name: 'Abrir menú' }), 'Botón de menú');
await p.waitForTimeout(300);
await tocar(p.locator('#admin-menu').getByRole('link', { name: /Postores/ }), 'Enlace Postores en el menú');
await p.waitForURL('**/admin/postores');
await sinScrollHorizontal('postores');

const badgeGarantia = (t) => t.locator('.badge-admin').nth(1);
const esperarTexto = async (loc, texto) => {
    for (let i = 0; i < 40; i++) {
        if ((await loc.textContent()).trim() === texto) return true;
        await p.waitForTimeout(150);
    }
    return false;
};

// 1. Garantía en revisión de una cuenta aprobada, desde la tarjeta.
const tarjeta = p.locator('.admin-tarjeta').filter({ hasText: 'Inversiones Andes SpA' }).filter({ hasText: 'R-2026-118' });
console.log(`  · Estado inicial: ${(await badgeGarantia(tarjeta).textContent()).trim()}`);
await tocar(tarjeta.getByRole('button', { name: 'Aprobar garantía' }), 'Aprobar garantía (tarjeta)');
if (!await esperarTexto(badgeGarantia(tarjeta), 'GARANTÍA APROBADA')) errores.push('La garantía no quedó aprobada desde la tarjeta');

// 2. Desde la ficha a pantalla completa.
const otra = p.locator('.admin-tarjeta').filter({ hasText: 'Camila Ortiz Vera' }).filter({ hasText: 'R-2026-118' });
await tocar(otra.getByRole('button', { name: 'Ver ficha' }), 'Ver ficha');
const ficha = p.getByRole('dialog');
const cajaFicha = await ficha.locator('.admin-ficha__caja').boundingBox();
if (!cajaFicha || cajaFicha.width < 370) errores.push('La ficha no ocupa la pantalla completa');
await tocar(ficha.getByRole('button', { name: 'Aprobar garantía' }), 'Aprobar garantía (ficha)');
if (!await esperarTexto(badgeGarantia(otra), 'GARANTÍA APROBADA')) errores.push('La garantía (ficha) no quedó aprobada');
await tocar(p.getByRole('button', { name: 'Cerrar ficha' }), 'Cerrar ficha');

// 3. Cuenta en revisión: aprobar la cuenta.
const cuenta = p.locator('.admin-tarjeta').filter({ hasText: 'Rodrigo Salas Pinto' });
await tocar(cuenta.getByRole('button', { name: 'Aprobar cuenta' }), 'Aprobar cuenta (tarjeta)');
if (!await esperarTexto(cuenta.locator('.badge-admin').first(), 'CUENTA APROBADO')) errores.push('La cuenta no quedó aprobada');

// 4. Rechazar una cuenta con motivo.
const rechazo = p.locator('.admin-tarjeta').filter({ hasText: 'Jorge Tapia Ruiz' });
await tocar(rechazo.getByRole('button', { name: 'Rechazar' }), 'Rechazar cuenta (tarjeta)');
await p.locator('.admin-modal textarea').fill('Faltan documentos de identidad');
await tocar(p.locator('.admin-modal').getByRole('button', { name: 'Confirmar' }), 'Confirmar rechazo');
if (!await esperarTexto(rechazo.locator('.badge-admin').first(), 'CUENTA RECHAZADO')) errores.push('La cuenta no quedó rechazada');
await sinScrollHorizontal('postores tras aprobar y rechazar');

// 5. Todo quedó en la base: al recargar se mantiene.
await p.reload({ waitUntil: 'load' });
const persistido = (await badgeGarantia(p.locator('.admin-tarjeta').filter({ hasText: 'Inversiones Andes SpA' }).filter({ hasText: 'R-2026-118' })).textContent()).trim();
if (persistido !== 'GARANTÍA APROBADA') errores.push(`Tras recargar, la garantía está: ${persistido}`);

await navegador.close();
if (errores.length) {
    console.log('\nFALLA:\n- ' + errores.join('\n- '));
    process.exit(1);
}
console.log('\nOK: el recorrido se completa en móvil sin problemas de usabilidad.');
