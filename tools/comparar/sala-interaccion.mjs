// Verifica que la sala de puja se comporte igual que el prototipo en escritorio.
import { chromium } from 'playwright';

const navegador = await chromium.launch({ channel: 'chrome' });
const casos = {
    original: 'http://127.0.0.1:8081/colliers-subastas-usuario-main/Puja%20en%20Vivo.dc.html',
    // Datos fijos del prototipo (?demo=1, solo local); la sala exige sesión de postor (Bloque K).
    laravel: 'http://127.0.0.1:8000/revision/entrar/postor?a=' + encodeURIComponent('/remates/apoquindo/sala?demo=1'),
};
for (const [nombre, url] of Object.entries(casos)) {
    const ctx = await navegador.newContext({ viewport: { width: 1440, height: 900 } });
    const p = await ctx.newPage();
    await p.clock.setFixedTime(new Date('2026-08-31T15:00:00Z'));
    if (nombre === 'original') {
        await p.route(url, async (ruta) => {
            const r = await ruta.fetch();
            const html = (await r.text()).replace('&quot;simularRivales&quot;:{&quot;editor&quot;:&quot;boolean&quot;,&quot;default&quot;:true', '&quot;simularRivales&quot;:{&quot;editor&quot;:&quot;boolean&quot;,&quot;default&quot;:false');
            await ruta.fulfill({ response: r, body: html });
        });
    }
    await p.goto(url, { waitUntil: 'load' });
    await p.getByText('PRECIO ACTUAL').waitFor();
    const r = {};
    const boton = p.getByRole('button', { name: /^Pujar \$/ });
    r.estadoInicial = await p.getByText(/Vas ganando|Te superaron/).first().textContent();
    r.botonInicial = await boton.textContent();
    const input = p.getByPlaceholder(/Mínimo/);
    await input.fill('190000000');
    r.invalido = await p.getByText(/La postura debe ser al menos/).textContent();
    r.botonInvalidoFondo = await boton.evaluate((b) => getComputedStyle(b).backgroundColor);
    await input.fill('');
    await p.getByRole('button', { name: /\+ \$500k/ }).click();
    r.trasRapida = await boton.textContent();
    await boton.click();
    r.modal = await p.getByText('Confirma tu puja').isVisible();
    r.modalDetalle = (await p.getByText(/supera en/).textContent()).replace(/\s+/g, ' ').trim();
    await p.getByRole('button', { name: 'Cancelar' }).click();
    r.modalCerrado = await p.getByText('Confirma tu puja').count();
    await boton.click();
    await p.getByRole('button', { name: 'Confirmar puja' }).click();
    r.estadoFinal = await p.getByText(/Vas ganando|Te superaron/).first().textContent();
    r.precioFinal = (await p.getByText('PRECIO ACTUAL').locator('xpath=following-sibling::div[1]').textContent()).trim();
    r.posturas = (await p.getByText(/POSTURAS · TIEMPO REAL/).textContent()).replace(/\s+/g, ' ').trim();
    console.log(nombre.padEnd(9), JSON.stringify(r));
    await ctx.close();
}
await navegador.close();
