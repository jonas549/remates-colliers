// reCAPTCHA v2 en los recorridos (22/10). Si el servidor tiene claves (las de prueba de Google siempre aprueban),
// marca «No soy un robot» como una persona y espera el token. Sin claves no hay casilla y no hace nada.
export async function marcarCaptcha(pagina) {
    if ((await pagina.locator('.g-recaptcha').count()) === 0) return false;
    const esCasilla = (f) => f.url().startsWith('https://www.google.com/recaptcha/api2/anchor');
    const hasta = Date.now() + 20000;
    while (!pagina.frames().some(esCasilla) && Date.now() < hasta) await pagina.waitForTimeout(200);
    await pagina.frames().find(esCasilla).locator('#recaptcha-anchor').click();
    await pagina.waitForFunction(() => (document.querySelector('textarea[name="g-recaptcha-response"]')?.value || '').length > 20, null, { timeout: 20000 });
    return true;
}
