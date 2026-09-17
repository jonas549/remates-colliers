// Bloque V en navegador real: Administración → Configuración, ahora con una pantalla por tema (17/09).
// Recorre el submenú, guarda valores, prueba el correo, edita y restaura una plantilla, apaga un aviso y revisa
// usabilidad. Requiere `php artisan serve` (APP_ENV=local) y la base local sembrada. No reinicia la base.
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
// Las secciones son sub-ítems del menú lateral (17/09): se navega por ahí, no por pestañas.
const seccion = async (nombre) => {
    await p.locator('.admin-lateral__subitem', { hasText: nombre }).first().click();
    await p.waitForLoadState('load');
};

await p.goto(`${URL}/revision/entrar/admin?a=/admin/configuracion`, { waitUntil: 'load' });
comprobar('menú con el ítem Configuración activo', (await p.locator('.admin-lateral__item.es-actual').innerText()).includes('Configuración'));
comprobar('abre en «Remates y pujas»', p.url().endsWith('/admin/configuracion/remates'), p.url());
const submenu = await p.locator('.admin-lateral__subitem').allInnerTexts();
comprobar('submenú dentro del menú lateral, con las 9 secciones', submenu.length === 9 && submenu.includes('Plantillas de correo')
    && submenu.includes('Registro de correos') && submenu.includes('Sistema'), submenu.join(' · '));
comprobar('el sub-ítem actual queda marcado bajo «Configuración»', (await p.locator('.admin-lateral__subitem.es-actual').innerText()) === 'Remates y pujas');
comprobar('sin pestañas horizontales', (await p.locator('.admin-submenu').count()) === 0);
comprobar('cada pantalla trae solo lo suyo', (await p.locator('input[name="config[margen_liquidacion_segundos]"]').count()) === 1
    && (await p.locator('input[name="config[smtp_host]"]').count()) === 0);

// 1. Remates y pujas: guardar y validar.
await p.fill('input[name="config[margen_liquidacion_segundos]"]', '5');
await p.fill('input[name="config[pujas_rapidas]"]', '100.000, 500.000, 2.000.000');
await p.getByRole('button', { name: 'Guardar cambios' }).click();
comprobar('configuración guardada', (await p.locator('.admin-aviso--ok').innerText()).includes('Configuración guardada'));
comprobar('los valores quedan en el formulario', (await p.inputValue('input[name="config[margen_liquidacion_segundos]"]')) === '5'
    && (await p.inputValue('input[name="config[pujas_rapidas]"]')) === '100.000, 500.000, 2.000.000');

await p.fill('input[name="config[margen_liquidacion_segundos]"]', '0');
await p.getByRole('button', { name: 'Guardar cambios' }).click();
comprobar('error de validación en español', (await p.locator('.admin-errores').innerText()).includes('margen de liquidación'));

// 2. Garantías.
await seccion('Garantías');
await p.fill('input[name="config[porcentaje_garantia]"]', '150');
await p.getByRole('button', { name: 'Guardar cambios' }).click();
comprobar('garantías: validación del porcentaje', (await p.locator('.admin-errores').innerText()).includes('garantía'));

// 3. Correo: los dos botones separados.
await seccion('Correo (SMTP)');
comprobar('correo: «Probar conexión» y «Enviar correo de prueba»', (await p.getByRole('button', { name: 'Probar conexión' }).count()) === 1
    && (await p.getByRole('button', { name: 'Enviar correo de prueba' }).count()) === 1);
await p.selectOption('select[name="config[correo_modo]"]', 'log');
await p.getByRole('button', { name: 'Guardar cambios' }).click();
await p.getByRole('button', { name: 'Probar conexión' }).click();
await p.waitForSelector('.admin-aviso');
comprobar('probar conexión en modo registro explica el motivo', (await p.locator('.admin-aviso').innerText()).includes('Cambia el modo a SMTP'));

await p.getByRole('button', { name: 'Enviar correo de prueba' }).click();
await p.getByRole('button', { name: 'Enviar ahora' }).click();
await p.waitForSelector('.admin-aviso--ok');
comprobar('correo de prueba en modo registro', (await p.locator('.admin-aviso--ok').innerText()).includes('registrado en el log'));

// Servidor inexistente: el mensaje dice exactamente qué falló.
await p.selectOption('select[name="config[correo_modo]"]', 'smtp');
await p.fill('input[name="config[smtp_host]"]', 'servidor-que-no-existe.colliers-invalido');
await p.getByRole('button', { name: 'Guardar cambios' }).click();
await p.getByRole('button', { name: 'Probar conexión' }).click();
await p.waitForSelector('.admin-aviso');
comprobar('probar conexión con servidor inexistente', (await p.locator('.admin-aviso').innerText()).includes('No se pudo resolver el servidor'),
    (await p.locator('.admin-aviso').innerText()).slice(0, 120));
await p.selectOption('select[name="config[correo_modo]"]', 'log');
await p.fill('input[name="config[smtp_host]"]', '');
await p.getByRole('button', { name: 'Guardar cambios' }).click();

// 4. Plantillas: editar, vista previa y restaurar.
await seccion('Plantillas de correo');
const filas = await p.locator('tbody tr').count();
comprobar('lista de plantillas', filas >= 14, `${filas} plantillas`);
await p.locator('tbody tr', { hasText: 'Cuenta aprobada' }).getByRole('link', { name: 'Editar' }).click();
await p.waitForLoadState('load');
comprobar('editor con variables y vista previa', (await p.locator('.admin-datos').innerText()).includes('{{ contacto }}')
    && (await p.locator('.admin-previa__asunto').innerText()).includes('Tu cuenta fue aprobada'));

await p.fill('input[name=asunto]', 'Tu cuenta quedó habilitada');
await p.fill('textarea[name=cuerpo]', 'Colliers aprobó tu cuenta.\n\nEscríbenos a {{ contacto }} si tienes dudas.');
await p.getByRole('button', { name: 'Ver la vista previa' }).click();
await p.waitForLoadState('load');
const previa = await p.locator('.admin-previa__cuerpo').innerText();
comprobar('la vista previa reemplaza las variables sin guardar', previa.includes('remates@colliers.cl') && !previa.includes('{{ contacto }}'));

await p.getByRole('button', { name: 'Guardar plantilla' }).click();
await p.waitForSelector('.admin-aviso--ok');
comprobar('plantilla guardada', (await p.locator('.admin-aviso--ok').innerText()).includes('Plantilla guardada'));
p.once('dialog', (d) => d.accept()); // «¿Volver al texto original?»
await p.getByRole('button', { name: 'Restaurar la original' }).click();
await p.waitForSelector('.admin-aviso--ok');
comprobar('plantilla restaurada', (await p.locator('.admin-aviso--ok').innerText()).includes('restaurada')
    && (await p.inputValue('input[name=asunto]')) === 'Tu cuenta fue aprobada');

// 4b. Aviso del remitente distinto al usuario autenticado, antes de intentar enviar.
await p.goto(`${URL}/admin/configuracion/correo`, { waitUntil: 'load' });
await p.selectOption('select[name="config[correo_modo]"]', 'smtp');
await p.fill('input[name="config[smtp_host]"]', 'mail.colliers.test');
await p.fill('input[name="config[smtp_usuario]"]', 'noreply@rematescolliers.sandbox');
await p.fill('input[name="config[correo_remitente]"]', 'remates@colliers.cl');
await p.getByRole('button', { name: 'Guardar cambios' }).click();
await p.waitForSelector('.admin-aviso');
comprobar('avisa que el remitente no es el usuario autenticado', (await p.locator('.admin-aviso--error').innerText()).includes('no coincide con el usuario autenticado'));
await p.selectOption('select[name="config[correo_modo]"]', 'log');
await p.fill('input[name="config[smtp_usuario]"]', '');
await p.fill('input[name="config[correo_remitente]"]', '');
await p.fill('input[name="config[smtp_host]"]', '');
await p.getByRole('button', { name: 'Guardar cambios' }).click();

// 5. Notificaciones: interruptores y bitácora.
await seccion('Notificaciones');
comprobar('tabla de avisos y últimos envíos', (await p.locator('.admin-tabla').count()) === 2
    && (await p.locator('body').innerText()).includes('Siempre (correo de la cuenta)'));
await p.uncheck('input[type=checkbox][name="avisos[remate_nuevo]"]');
await p.getByRole('button', { name: 'Guardar cambios' }).click();
await p.waitForSelector('.admin-aviso--ok');
comprobar('interruptor apagado queda guardado', !(await p.isChecked('input[type=checkbox][name="avisos[remate_nuevo]"]')));
await p.check('input[type=checkbox][name="avisos[remate_nuevo]"]');
await p.getByRole('button', { name: 'Guardar cambios' }).click();

// 5b. Registro de correos: historial con filtros.
await seccion('Registro de correos');
comprobar('registro de correos con resumen y filtros', (await p.locator('.admin-resumen-correos').count()) === 1
    && (await p.locator('select[name=estado]').count()) === 1 && (await p.locator('input[name=q]').count()) === 1);
await p.selectOption('select[name=estado]', 'fallida');
await p.getByRole('button', { name: 'Filtrar' }).click();
await p.waitForLoadState('load');
comprobar('el filtro viaja en la URL y la tabla responde', p.url().includes('estado=fallida')
    && ((await p.locator('tbody tr').innerText()).includes('Sin correos con esos filtros') || (await p.locator('.badge-admin').first().innerText()) === 'FALLIDA'));

// 6. Seguridad y Sistema.
await seccion('Seguridad');
comprobar('seguridad: duración de la sesión', (await p.locator('input[name="config[sesion_minutos]"]').count()) === 1);
await seccion('Sistema');
comprobar('sistema visto desde la web', (await p.locator('.admin-datos').innerText()).includes('OPcache (web)'));

// Deja los valores por defecto del acta para las demás pruebas.
await p.goto(`${URL}/admin/configuracion/remates`, { waitUntil: 'load' });
await p.fill('input[name="config[margen_liquidacion_segundos]"]', '2');
await p.fill('input[name="config[pujas_rapidas]"]', '100.000, 500.000, 1.000.000');
await p.getByRole('button', { name: 'Guardar cambios' }).click();
await p.goto(`${URL}/admin/configuracion/garantias`, { waitUntil: 'load' });
await p.fill('input[name="config[porcentaje_garantia]"]', '10');
await p.getByRole('button', { name: 'Guardar cambios' }).click();

for (const [ancho, ruta] of [[375, 'remates'], [760, 'correos'], [1120, 'plantillas'], [1440, 'notificaciones']]) {
    const c = await navegador.newContext({ viewport: { width: ancho, height: 900 }, locale: 'es-CL' });
    const q = await c.newPage();
    await q.goto(`${URL}/revision/entrar/admin?a=/admin/configuracion/${ruta}`, { waitUntil: 'load' });
    await q.evaluate(() => document.fonts.ready);
    const u = await revisarUsabilidad(q, ancho);
    comprobar(`configuración (${ruta}) a ${ancho}px: sin desborde, táctiles ≥ 44`, !u.desborde && u.pequenos.length === 0, u.pequenos.slice(0, 4).join('; '));
    await q.screenshot({ path: path.join(SALIDA, `configuracion-${ruta}-${ancho}.png`), fullPage: true });
    await c.close();
}

await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
