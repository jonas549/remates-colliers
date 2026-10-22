// Segundo factor del panel en los recorridos (22/10). Después de ingresar con usuario y clave, Administración llega a
// «Revisa tu correo». Una persona abriría su correo y copiaría el código: aquí se «abre el correo» leyendo el que quedó
// en el log local (MAIL_MAILER=log) con tools/comparar/sala-ayudante.php y se escribe en la pantalla, con clics.
import { execFileSync } from 'node:child_process';

/** El último código enviado a esa cuenta. */
export function leerCodigo(correo) {
    return execFileSync('php', ['tools/comparar/sala-ayudante.php', 'codigo', correo], { encoding: 'utf8' }).trim();
}

/**
 * Si la página quedó en la pantalla del código, lo escribe y entra. Devuelve true si hizo falta.
 * `recordar`: marca «No pedir el código en este dispositivo».
 */
export async function completarSegundoFactor(pagina, correo, { recordar = false } = {}) {
    if (!pagina.url().includes('/admin/ingresar/codigo')) return false;
    await pagina.fill('input[name=codigo]', leerCodigo(correo));
    if (recordar) await pagina.check('input[name=recordar_dispositivo]');
    await pagina.getByRole('button', { name: 'Entrar al panel' }).click();
    await pagina.waitForLoadState('load');
    return true;
}
