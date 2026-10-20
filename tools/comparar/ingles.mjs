// Sitio público en inglés (20/10): cada pantalla bajo /en en móvil, tablet y escritorio, como visitante y como postor.
// Comprueba: sin desborde horizontal, áreas táctiles ≥ 44 px bajo 1120, ningún texto armado por el JS con una clave sin
// traducir, y que el conmutador lleve a la misma página en español y vuelva. Deja capturas en salida/ingles/.
//
// Requiere `php artisan serve` (APP_ENV=local). REINICIA la base local con el seeder. Un ancho por proceso:
//   node tools/comparar/ingles.mjs --anchos 375
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';
import { revisarUsabilidad } from './usabilidad.mjs';

const URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const SALIDA = path.resolve('tools/comparar/salida/ingles');
const arg = process.argv.indexOf('--anchos');
const ANCHOS = arg > 0 ? process.argv[arg + 1].split(',').map(Number) : [375, 768, 1440];
const CLAVE = /\b(sitio|listado|remate|sala|cuenta|acceso|errores|catalogo|formato)\.[a-z_]+(\.[a-z_]+)*\b/;
let fallas = 0;
const comprobar = (nombre, ok, detalle = '') => {
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre}${detalle ? '  (' + detalle + ')' : ''}`);
};

execFileSync('php', ['tools/comparar/sala-ayudante.php', 'reiniciar'], { encoding: 'utf8' });
fs.mkdirSync(SALIDA, { recursive: true });

const publicas = ['/en', '/en/remates/militares', '/en/remates/apoquindo', '/en/remates/sanmartin', '/en/ingresar', '/en/registro', '/en/recuperar-clave', '/en/no-existe'];
const postor = ['/en/mi-cuenta', '/en/mi-cuenta/sesiones', '/en/remates/apoquindo/sala'];

const navegador = await chromium.launch({ channel: 'chrome' });
for (const ancho of ANCHOS) {
    console.log(`\n— ${ancho} px`);
    const contexto = await navegador.newContext({ viewport: { width: ancho, height: 900 }, locale: 'en-US', timezoneId: 'America/Santiago' });
    const pagina = await contexto.newPage();
    const revisar = async (ruta) => {
        await pagina.goto(URL + ruta, { waitUntil: 'networkidle' });
        await pagina.waitForTimeout(400);
        const u = await revisarUsabilidad(pagina, ancho);
        const texto = await pagina.locator('body').innerText();
        const clave = texto.match(CLAVE);
        comprobar(`${ruta}: sin desborde`, !u.desborde);
        if (ancho < 1120) comprobar(`${ruta}: táctiles ≥ 44`, u.pequenos.length === 0, u.pequenos.slice(0, 4).join(', '));
        comprobar(`${ruta}: sin claves sin traducir`, !clave, clave?.[0]);
        await pagina.screenshot({ path: path.join(SALIDA, `${ancho}-${ruta.replace(/\//g, '_')}.png`), fullPage: true });
    };
    for (const ruta of publicas) await revisar(ruta);

    // Conmutador: de la ficha en inglés a la misma en español y de vuelta (se ve en todos los anchos).
    await pagina.goto(URL + '/en/remates/militares', { waitUntil: 'networkidle' });
    if (ancho < 760) await pagina.getByRole('button', { name: /menu/i }).click();
    await pagina.locator(ancho < 760 ? '.pub-menu a[lang="es"]' : '.pub-cabecera__nav .pub-cabecera__idioma a[lang="es"]').first().click();
    await pagina.waitForURL(/\/remates\/militares$/);
    comprobar('conmutador: ES lleva a la misma ficha en español', new globalThis.URL(pagina.url()).pathname === '/remates/militares' && (await pagina.locator('html').getAttribute('lang')) === 'es');
    await pagina.goto(URL + '/', { waitUntil: 'networkidle' });
    comprobar('la portada recuerda el español elegido', new globalThis.URL(pagina.url()).pathname === '/');

    // Postor: ingreso por /en con clics y sus pantallas.
    await pagina.goto(URL + '/en/ingresar', { waitUntil: 'networkidle' });
    await pagina.locator('input[name=usuario]').fill('mpgonzalez@correo.test');
    await pagina.locator('input[name=password]').fill('colliers-local-2026');
    await pagina.locator('button[type=submit]').click();
    await pagina.waitForURL(/\/en\/mi-cuenta/);
    comprobar('ingreso por /en llega a /en/mi-cuenta', /\/en\/mi-cuenta/.test(pagina.url()));
    for (const ruta of postor) await revisar(ruta);
    // En la sala, el conmutador de la cabecera vuelve a la sala en español.
    await pagina.locator('.sala-cabecera .pub-cabecera__idioma a[lang="es"]').click();
    await pagina.waitForURL(/\/remates\/apoquindo\/sala$/);
    comprobar('sala: el conmutador lleva a la sala en español', !/\/en\//.test(pagina.url()));
    await contexto.close();
}
await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exitCode = fallas ? 1 : 0;
