// Verifica que el Login se comporte igual que el prototipo: error con campos vacíos,
// botón que se activa al completar, mostrar/ocultar clave.
import { chromium } from 'playwright';

const navegador = await chromium.launch({ channel: 'chrome' });
const casos = {
    original: 'http://127.0.0.1:8081/Login.dc.html',
    laravel: 'http://127.0.0.1:8000/ingresar',
};
for (const [nombre, url] of Object.entries(casos)) {
    const p = await navegador.newPage({ viewport: { width: 1440, height: 900 } });
    await p.goto(url, { waitUntil: 'networkidle' });
    const boton = p.getByRole('button', { name: 'Ingresar' });
    const fondo = () => boton.evaluate((b) => getComputedStyle(b).backgroundColor);
    const r = {};
    r.botonInicial = await fondo();
    await boton.click();
    await p.waitForTimeout(150);
    r.errorVisible = await p.getByText('Usuario o contraseña incorrectos').isVisible();
    await p.getByPlaceholder('maria@correo.cl').fill('maria@correo.cl');
    r.errorTrasEscribir = await p.getByText('Usuario o contraseña incorrectos').isVisible().catch(() => false);
    await p.getByPlaceholder('Tu contraseña').fill('secreta');
    r.botonListo = await fondo();
    r.tipoClave = await p.getByPlaceholder('Tu contraseña').getAttribute('type');
    await p.getByRole('button', { name: 'MOSTRAR' }).click();
    r.tipoClaveVer = await p.getByPlaceholder('Tu contraseña').getAttribute('type');
    r.textoBotonVer = await p.getByRole('button', { name: /OCULTAR|MOSTRAR/ }).textContent();
    r.recordarMarcado = await p.getByRole('checkbox').isChecked();
    console.log(nombre.padEnd(9), JSON.stringify(r));
    await p.close();
}
await navegador.close();
