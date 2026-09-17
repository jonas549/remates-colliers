// Bloque O en navegador real: reportes con los remates cerrados del seeder, cambio de período, descargas XLSX y CSV,
// y usabilidad a 375/760/1120/1440 px. Requiere `php artisan serve` (APP_ENV=local). Reinicia la base local.
//
//   node tools/comparar/reportes.mjs
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';
import { revisarUsabilidad } from './usabilidad.mjs';

const URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const SALIDA = path.resolve('tools/comparar/salida/reportes');
let fallas = 0;
const comprobar = (nombre, ok, detalle = '') => {
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre}${detalle ? '  (' + detalle + ')' : ''}`);
};

console.log('Preparando la base local (migrate:fresh --seed)…');
execFileSync('php', ['tools/comparar/sala-ayudante.php', 'reiniciar'], { encoding: 'utf8' });
fs.mkdirSync(SALIDA, { recursive: true });

const navegador = await chromium.launch({ channel: 'chrome' });
const contexto = await navegador.newContext({ viewport: { width: 1440, height: 900 }, locale: 'es-CL', acceptDownloads: true });
const p = await contexto.newPage();
await p.goto(`${URL}/revision/entrar/admin?a=/admin/reportes`, { waitUntil: 'load' });

const kpis = await p.locator('.admin-reportes__kpi').allInnerTexts();
const filas = await p.locator('.admin-tabla--reportes tbody tr').allInnerTexts();
comprobar('sin el aviso de datos de ejemplo', (await p.locator('text=Datos de ejemplo').count()) === 0);
comprobar('KPIs con los remates cerrados del seeder', kpis.length === 6 && kpis[0].includes('1 propiedad vendida') && kpis[1].includes('50%') && kpis[1].includes('1 de 2'),
    kpis.map((k) => k.replace(/\n/g, ' ')).join(' | '));
comprobar('tabla: San Martín adjudicado y Alemania no adjudicado', filas.length === 2 && filas.some((f) => f.includes('San Martín 655') && f.includes('Adjudicado'))
    && filas.some((f) => f.includes('Av. Alemania 0980') && f.includes('No adjudicado')), filas.map((f) => f.split('\n')[0]).join(' / '));
comprobar('dinámica de cierre dibujada', (await p.locator('.admin-dinamica__columna').count()) >= 1);
await p.screenshot({ path: path.join(SALIDA, 'reportes-1440.png'), fullPage: true });

// Período: todo el historial por el selector (recarga con ?periodo=todo).
await Promise.all([p.waitForURL(/periodo=todo/), p.selectOption('select[name=periodo]', 'todo')]);
comprobar('cambio de período desde el selector', (await p.locator('.admin-encabezado__bajada').innerText()).includes('todo el historial'));

// Descargas.
for (const [boton, extension] of [['XLSX', '.xlsx'], ['CSV', '.csv']]) {
    const [descarga] = await Promise.all([p.waitForEvent('download'), p.getByRole('link', { name: boton, exact: true }).click()]);
    const destino = path.join(SALIDA, descarga.suggestedFilename());
    await descarga.saveAs(destino);
    const tamano = fs.statSync(destino).size;
    comprobar(`descarga ${boton}`, descarga.suggestedFilename().endsWith(extension) && tamano > 100, `${descarga.suggestedFilename()} · ${tamano} bytes`);
    if (extension === '.csv') {
        const csv = fs.readFileSync(destino, 'utf8');
        comprobar('CSV con BOM, punto y coma y el folio', csv.charCodeAt(0) === 0xfeff && csv.includes('"R-2026-105";'));
    }
}
const [pujas] = await Promise.all([p.waitForEvent('download'), p.locator('.admin-exportable', { hasText: 'Detalle de pujas' }).getByRole('link', { name: 'Descargar' }).click()]);
comprobar('exportable «Detalle de pujas»', pujas.suggestedFilename() === 'reporte-pujas-todo.xlsx', pujas.suggestedFilename());

for (const ancho of [375, 760, 1120, 1440]) {
    const c = await navegador.newContext({ viewport: { width: ancho, height: 900 }, locale: 'es-CL' });
    const q = await c.newPage();
    await q.goto(`${URL}/revision/entrar/admin?a=/admin/reportes`, { waitUntil: 'load' });
    await q.evaluate(() => document.fonts.ready);
    const u = await revisarUsabilidad(q, ancho);
    comprobar(`reportes a ${ancho}px: sin desborde, táctiles ≥ 44`, !u.desborde && u.pequenos.length === 0, [u.desborde ? 'desborde' : '', ...u.pequenos].filter(Boolean).join('; '));
    await q.screenshot({ path: path.join(SALIDA, `reportes-${ancho}.png`), fullPage: true });
    await c.close();
}

await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
