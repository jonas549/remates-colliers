// Verifica los filtros del listado (decisión del 15/09) en escritorio y en el panel lateral de móvil.
// Datos esperados: los 9 remates de App\Demo\RematesDemo con la hora fija del arnés.
import { chromium } from 'playwright';

const URL = (process.env.LARAVEL_URL || 'http://127.0.0.1:8000') + '/';
const navegador = await chromium.launch({ channel: 'chrome' });
let fallas = 0;

function comprobar(nombre, obtenido, esperado) {
    const ok = JSON.stringify(obtenido) === JSON.stringify(esperado);
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre.padEnd(46)} ${JSON.stringify(obtenido)}${ok ? '' : '  esperado ' + JSON.stringify(esperado)}`);
}

for (const ancho of [1440, 375]) {
    console.log(`\n── ${ancho}px`);
    const ctx = await navegador.newContext({ viewport: { width: ancho, height: 900 } });
    const p = await ctx.newPage();
    await p.clock.setFixedTime(new Date('2026-08-31T15:00:00Z'));
    await p.goto(URL, { waitUntil: 'domcontentloaded' });
    const panel = p.locator('.listado__filtros, aside').filter({ hasText: 'Estado del remate' }).first();
    const total = async () => Number((await p.getByText(/Mostrando \d+ de \d+ remates/).first().textContent()).match(/de (\d+)/)[1]);
    const opciones = (grupo) => panel.locator('details').filter({ hasText: grupo }).locator('label.listado__opcion:visible').allTextContents();
    const marcar = async (grupo, texto) => {
        const d = panel.locator('details').filter({ hasText: grupo });
        if (!(await d.evaluate((el) => el.open))) await d.locator('summary').click();
        await d.locator('label.listado__opcion', { hasText: texto }).first().click();
    };
    const limpiar = () => panel.getByText('Limpiar filtros').click();

    if (ancho < 760) {
        await p.getByRole('button', { name: 'Filtros' }).first().click();
        await p.waitForTimeout(300);
    }

    const grupos = (await panel.locator('details summary').allTextContents()).map((t) => t.replace(/[+–]\s*$/, '').trim());
    comprobar('grupos visibles', grupos, ['Estado del remate', 'Ocupación', 'Fecha de remate', 'Tipo de propiedad', 'Características', 'Rango de precio base', 'Región', 'Comuna']);
    comprobar('sin «visita programada»', await panel.getByText(/visita programada/i).count(), 0);
    comprobar('«Garantía requerida» oculto', await panel.getByText('Garantía requerida').isVisible(), false);
    comprobar('total inicial', await total(), 9);

    await panel.locator('details').filter({ hasText: 'Región' }).locator('summary').click();
    comprobar('regiones en orden geográfico', await opciones('Región'), ['Valparaíso', 'Metropolitana', 'Biobío', 'La Araucanía']);
    await panel.locator('details').filter({ hasText: 'Comuna' }).locator('summary').click();
    comprobar('comunas desde los datos, orden es_CL', await opciones('Comuna'), ['Colina', 'Concepción', 'Las Condes', 'Ñuñoa', 'Providencia', 'Temuco', 'Viña del Mar']);

    await marcar('Región', 'Metropolitana');
    comprobar('región RM → total', await total(), 6);
    comprobar('región RM → comunas de la región', await opciones('Comuna'), ['Colina', 'Las Condes', 'Ñuñoa', 'Providencia']);
    await marcar('Comuna', 'Ñuñoa');
    comprobar('RM + Ñuñoa → total', await total(), 1);
    await limpiar();
    comprobar('limpiar → total', await total(), 9);

    await marcar('Tipo de propiedad', 'Casa');
    comprobar('tipo Casa', await total(), 3);
    await limpiar();

    await marcar('Características', '3 dormitorios o más');
    comprobar('3+ dormitorios', await total(), 6);
    await marcar('Características', 'Con bodega');
    comprobar('3+ dormitorios + bodega', await total(), 4);
    await limpiar();

    await panel.locator('details').filter({ hasText: 'Rango de precio base' }).locator('summary').click();
    await panel.getByLabel('Precio base desde').fill('100.000.000');
    await panel.getByLabel('Precio base hasta').fill('200000000');
    comprobar('precio 100M–200M', await total(), 3);
    comprobar('campo solo acepta dígitos', await panel.getByLabel('Precio base desde').inputValue(), '100000000');
    await limpiar();
    comprobar('limpiar vacía el precio', await panel.getByLabel('Precio base desde').inputValue(), '');

    // La fecha se filtra por la cuenta regresiva (delta), no por el texto de la fecha: en los datos de
    // ejemplo del prototipo Colina está a 30 días justos (delta 2.592.000 s) aunque su texto diga 07-10.
    await marcar('Fecha de remate', 'Esta semana');
    comprobar('esta semana (en vivo + 2 próximos)', await total(), 3);
    await limpiar();
    await marcar('Fecha de remate', 'Próximos 30 días');
    comprobar('próximos 30 días (excluye cerrados)', await total(), 7);
    await limpiar();
    await marcar('Fecha de remate', 'Próximos 90 días');
    comprobar('próximos 90 días (excluye cerrados)', await total(), 7);
    await limpiar();

    await marcar('Región', 'Valparaíso');
    await marcar('Tipo de propiedad', 'Casa');
    comprobar('sin resultados muestra estado vacío', await p.getByText('Sin remates para esta combinación').isVisible(), true);
    await limpiar();

    if (ancho < 760) {
        await marcar('Tipo de propiedad', 'Casa');
        const boton = p.getByRole('button', { name: /Ver \d+ remates/ });
        comprobar('botón del panel refleja el conteo', (await boton.textContent()).trim(), 'Ver 3 remates');
        await boton.click();
        await p.waitForTimeout(400);
        comprobar('panel se cierra al aplicar', await panel.getByText('Estado del remate').isVisible(), false);
        const desborde = await p.evaluate(() => document.documentElement.scrollWidth > innerWidth);
        comprobar('sin scroll horizontal', desborde, false);
    }
    await ctx.close();
}
await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
