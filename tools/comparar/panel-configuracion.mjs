// Bloque V en navegador real: Administración → Configuración (guardar, correo de prueba en modo registro, usabilidad).
// Requiere `php artisan serve` (APP_ENV=local) y la base local sembrada (migrate:fresh --seed). No reinicia la base.
//
//   node tools/comparar/panel-configuracion.mjs
import fs from 'node:fs';
import path from 'node:path';
import { chromium } from 'playwright';
import { revisarUsabilidad } from './usabilidad.mjs';

const URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const SALIDA = path.resolve('tools/comparar/salida/panel-configuracion');
let fallas = 0;
const comprobar = (nombre, ok, detalle = '') => {
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre}${detalle ? '  (' + detalle + ')' : ''}`);
};
fs.mkdirSync(SALIDA, { recursive: true });

const navegador = await chromium.launch({ channel: 'chrome' });
const p = await (await navegador.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-CL' })).newPage();
await p.goto(`${URL}/revision/entrar/admin?a=/admin/configuracion`, { waitUntil: 'load' });

comprobar('menú con el ítem Configuración activo', (await p.locator('.admin-lateral__item.es-actual').innerText()).includes('Configuración'));
comprobar('sistema visto desde la web', (await p.locator('.admin-datos').innerText()).includes('OPcache (web)'));

await p.fill('input[name="config[margen_liquidacion_segundos]"]', '5');
await p.fill('input[name="config[pujas_rapidas]"]', '100.000, 500.000, 2.000.000');
await p.selectOption('select[name="config[correo_modo]"]', 'log');
await p.getByRole('button', { name: 'Guardar configuración' }).click();
comprobar('configuración guardada', (await p.locator('.admin-aviso--ok').innerText()).includes('Configuración guardada'));
comprobar('los valores quedan en el formulario', (await p.inputValue('input[name="config[margen_liquidacion_segundos]"]')) === '5'
    && (await p.inputValue('input[name="config[pujas_rapidas]"]')) === '100.000, 500.000, 2.000.000');

await p.fill('input[name="config[porcentaje_garantia]"]', '150');
await p.getByRole('button', { name: 'Guardar configuración' }).click();
comprobar('error de validación en español', (await p.locator('.admin-errores').innerText()).includes('garantía'));

await p.goto(`${URL}/admin/configuracion`, { waitUntil: 'load' });
await p.getByRole('button', { name: 'Enviar correo de prueba' }).click();
comprobar('correo de prueba en modo registro', (await p.locator('.admin-aviso--ok').innerText()).includes('registrado en storage/logs'));

// Deja los valores por defecto del acta para las demás pruebas.
await p.fill('input[name="config[margen_liquidacion_segundos]"]', '2');
await p.fill('input[name="config[pujas_rapidas]"]', '100.000, 500.000, 1.000.000');
await p.getByRole('button', { name: 'Guardar configuración' }).click();

for (const ancho of [375, 760, 1120, 1440]) {
    const c = await navegador.newContext({ viewport: { width: ancho, height: 900 }, locale: 'es-CL' });
    const q = await c.newPage();
    await q.goto(`${URL}/revision/entrar/admin?a=/admin/configuracion`, { waitUntil: 'load' });
    const u = await revisarUsabilidad(q, ancho);
    comprobar(`configuración a ${ancho}px: sin desborde, táctiles ≥ 44`, !u.desborde && u.pequenos.length === 0, u.pequenos.slice(0, 4).join('; '));
    await q.screenshot({ path: path.join(SALIDA, `configuracion-${ancho}.png`), fullPage: true });
    await c.close();
}

await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
