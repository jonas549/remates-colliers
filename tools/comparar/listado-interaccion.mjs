// Verifica que el listado se comporte igual que el prototipo.
import { chromium } from 'playwright';

const navegador = await chromium.launch({ channel: 'chrome' });
const casos = {
    original: 'http://127.0.0.1:8081/colliers-subastas-invitado-main/index.dc.html',
    laravel: 'http://127.0.0.1:8000/remates',
};
const titulos = (p) => p.locator('h3 a').allTextContents();
for (const [nombre, url] of Object.entries(casos)) {
    const r = {};
    const ctx = await navegador.newContext({ viewport: { width: 1440, height: 900 } });
    const p = await ctx.newPage();
    await p.clock.setFixedTime(new Date('2026-08-31T15:00:00Z'));
    await p.goto(url, { waitUntil: 'domcontentloaded' });
    await p.getByText(/Mostrando \d+ de \d+ remates/).waitFor();
    r.inicial = [await p.getByText(/Mostrando \d+ de \d+ remates/).textContent(), (await titulos(p)).join(' | ')];
    await p.getByText('Cargar más remates').click();
    r.cargarMas = await p.getByText(/Mostrando \d+ de \d+ remates/).textContent();
    await p.locator('select').first().selectOption('precio-asc');
    r.ordenPrecio = (await titulos(p)).slice(0, 3).join(' | ');
    await p.getByText('Cerrados', { exact: true }).click();
    r.cerrados = [await p.getByText(/Mostrando \d+ de \d+ remates/).textContent(), await p.getByText('Remate cerrado').count()];
    await p.getByText('Limpiar filtros').click();
    await p.getByPlaceholder('Busca por dirección, comuna o tipo').fill('zzz');
    r.vacio = [await p.getByText('Sin remates para esta combinación').isVisible(), await p.getByText(/La búsqueda/).textContent()];
    await p.getByRole('button', { name: 'Borrar la búsqueda' }).click();
    await p.getByRole('button', { name: 'Tabla' }).click();
    r.tabla = await p.locator('table tbody tr').count();
    await p.getByRole('button', { name: 'Grilla' }).click();
    await p.locator('[title="Guardar remate"]').first().click();
    r.guardado = await p.locator('[title="Guardar remate"] svg').first().getAttribute('fill');
    await p.getByText('Ocultar filtros').click();
    r.ocultarFiltros = await p.getByText('Estado del remate').count();
    await p.setViewportSize({ width: 800, height: 900 });
    await p.waitForTimeout(200);
    r.tabletPanelInicial = await p.getByText('Estado del remate').count();
    await p.getByRole('button', { name: 'Filtros' }).first().click();
    r.tabletPanelAbierto = [await p.getByText('Estado del remate').isVisible(), (await p.getByRole('button', { name: /Ver \d+ remates/ }).textContent()).trim()];
    console.log(nombre.padEnd(9), JSON.stringify(r));
    await ctx.close();
}
await navegador.close();
