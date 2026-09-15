// Verifica que el Registro se comporte igual que el prototipo: validación de RUT,
// cambio a persona jurídica (sección extra y renumeración) y habilitación del envío.
import { chromium } from 'playwright';

const navegador = await chromium.launch({ channel: 'chrome' });
const casos = {
    original: 'http://127.0.0.1:8081/Registro%20Postor.dc.html',
    laravel: 'http://127.0.0.1:8000/registro',
};
const textoNumeros = (p) => p.locator('h2').evaluateAll((hs) => hs.map((h) => h.textContent.replace(/\s+/g, ' ').trim()));
for (const [nombre, url] of Object.entries(casos)) {
    const p = await navegador.newPage({ viewport: { width: 1440, height: 900 } });
    await p.goto(url, { waitUntil: 'networkidle' });
    const r = {};
    const rut = p.getByPlaceholder('12.345.678-9');
    await rut.fill('12.345.678-9');
    r.rutMalo = await p.getByText('Dígito verificador incorrecto').isVisible();
    await rut.fill('12.345.678-5');
    r.rutBueno = await p.getByText('RUT válido').isVisible();
    r.seccionesNatural = await textoNumeros(p);
    await p.getByRole('button', { name: 'Persona jurídica' }).click();
    r.seccionesJuridica = await textoNumeros(p);
    r.razonSocial = await p.getByPlaceholder('Inversiones Andes SpA').isVisible();
    const enviar = p.getByText('Enviar solicitud de registro');
    r.enviarAntes = await enviar.evaluate((e) => getComputedStyle(e).backgroundColor);
    await p.getByRole('checkbox').check();
    r.enviarDespues = await enviar.evaluate((e) => getComputedStyle(e).backgroundColor);
    console.log(nombre.padEnd(9), JSON.stringify(r));
    await p.close();
}
await navegador.close();
