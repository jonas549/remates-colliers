// Bloque H en navegador real, lado del postor: subir el comprobante de una garantía pendiente y reenviar uno rechazado.
// Requiere `php artisan serve` (APP_ENV=local). Reinicia la base local con el seeder.
//
//   node tools/comparar/cuenta-postor.mjs
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';
import { revisarUsabilidad } from './usabilidad.mjs';

const URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const SALIDA = path.resolve('tools/comparar/salida/cuenta-postor');
let fallas = 0;
const comprobar = (nombre, ok, detalle = '') => {
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre}${detalle ? '  (' + detalle + ')' : ''}`);
};

console.log('Preparando la base local (migrate:fresh --seed)…');
execFileSync('php', ['tools/comparar/sala-ayudante.php', 'reiniciar'], { encoding: 'utf8' });
fs.mkdirSync(SALIDA, { recursive: true });
const pdf = path.join(os.tmpdir(), 'comprobante-colliers.pdf');
fs.writeFileSync(pdf, '%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n');

const navegador = await chromium.launch({ channel: 'chrome' });

async function como(correo, ancho) {
    const c = await navegador.newContext({ viewport: { width: ancho, height: 900 }, locale: 'es-CL' });
    const p = await c.newPage();
    await p.goto(`${URL}/revision/entrar/postor?usuario=${encodeURIComponent(correo)}&a=/mi-cuenta`, { waitUntil: 'load' });
    return { c, p };
}

// Garantía pendiente (Constructora Lonquén, R-2026-121), en el celular.
{
    const { c, p } = await como('finanzas@lonquen.test', 375);
    comprobar('pendiente: banda «falta la garantía» y monto del 10 %', (await p.locator('.cuenta__banda-titulo').innerText()).includes('falta la garantía')
        && (await p.locator('.cuenta__dato-valor').first().innerText()) === '$9.600.000');
    await p.getByRole('button', { name: 'Subir comprobante' }).click();
    await p.selectOption('select[name=medio]', 'transferencia');
    await p.setInputFiles('input[name=comprobante]', pdf);
    const u = await revisarUsabilidad(p, 375);
    comprobar('pendiente a 375px con el formulario abierto: sin desborde, táctiles ≥ 44', !u.desborde && u.pequenos.length === 0, u.pequenos.join('; '));
    await p.getByRole('button', { name: 'Enviar comprobante' }).click();
    await p.waitForLoadState('load');
    comprobar('comprobante recibido: aviso y banda «en revisión»', (await p.locator('.cuenta__aviso').innerText()).includes('Recibimos tu comprobante')
        && (await p.locator('.cuenta__banda-etiqueta').innerText()) === 'GARANTÍA EN REVISIÓN');
    await p.screenshot({ path: path.join(SALIDA, 'en-revision-375.png'), fullPage: true });
    await c.close();
}

// Garantía rechazada (Luis Cárcamo, R-2026-119): ve el motivo y reenvía.
{
    const { c, p } = await como('lcarcamo@correo.test', 1440);
    comprobar('rechazada: se ve el motivo', (await p.locator('.cuenta__banda-texto').innerText()).includes('Monto insuficiente'));
    await p.getByRole('button', { name: 'Subir comprobante' }).click();
    await p.setInputFiles('input[name=comprobante]', pdf);
    await p.getByRole('button', { name: 'Enviar comprobante' }).click();
    await p.waitForLoadState('load');
    comprobar('reenvío tras rechazo: vuelve a revisión', (await p.locator('.cuenta__banda-etiqueta').innerText()) === 'GARANTÍA EN REVISIÓN');
    await c.close();
}

// Habilitada (María Paz, remate en vivo): acceso a la sala.
for (const ancho of [375, 760, 1120, 1440]) {
    const { c, p } = await como('mpgonzalez@correo.test', ancho);
    const u = await revisarUsabilidad(p, ancho);
    comprobar(`aprobada a ${ancho}px: botón a la sala, sin desborde, táctiles ≥ 44`, (await p.getByRole('link', { name: 'Entrar a la sala de pujas' }).first().isVisible())
        && !u.desborde && u.pequenos.length === 0, u.pequenos.join('; '));
    await p.screenshot({ path: path.join(SALIDA, `aprobada-${ancho}.png`), fullPage: true });
    await c.close();
}

await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
