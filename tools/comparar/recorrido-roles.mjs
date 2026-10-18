// Recorrido de los tres roles con la subasta de prueba, como lo haría una persona (21/09, criterio de Jonas).
// Administrador, martillero y dos postores en sesiones separadas (como navegadores distintos). La única dirección que se
// escribe es la del sitio; todo lo demás es clic, copiar y pegar lo que la pantalla ofrece. Incluye el cierre
// anticipado con los postores mirando la sala: tienen que verlo en segundos, sin recargar.
//
// Requiere `php artisan serve` (APP_ENV=local). REINICIA la base local con el seeder.
//   node tools/comparar/recorrido-roles.mjs
import fs from 'node:fs';
import path from 'node:path';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright';
import { revisarUsabilidad } from './usabilidad.mjs';

const URL = process.env.LARAVEL_URL || 'http://127.0.0.1:8000';
const SALIDA = path.resolve('tools/comparar/salida/recorrido-roles');
const CLAVE_INTERNA = 'colliers-local-2026'; // la del seeder: la persona la conoce (se la dieron al crear su cuenta)
let fallas = 0;
const comprobar = (nombre, ok, detalle = '') => {
    if (!ok) fallas++;
    console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre}${detalle ? '  (' + detalle + ')' : ''}`);
    return ok;
};
const esperar = async (condicion, ms) => {
    const hasta = Date.now() + ms;
    while (Date.now() < hasta) {
        if (await condicion().catch(() => false)) return true;
        await new Promise((r) => setTimeout(r, 200));
    }
    return false;
};
const texto = async (pagina, selector = 'body') => (await pagina.locator(selector).first().innerText()).replace(/\s+/g, ' ');

console.log('Preparando la base local (migrate:fresh --seed)…');
execFileSync('php', ['tools/comparar/sala-ayudante.php', 'reiniciar'], { encoding: 'utf8' });
fs.mkdirSync(SALIDA, { recursive: true });

const navegador = await chromium.launch({ channel: 'chrome' });
const ventana = async (ancho = 1440) => {
    const c = await navegador.newContext({ viewport: { width: ancho, height: 900 }, locale: 'es-CL', timezoneId: 'America/Santiago' });
    await c.grantPermissions(['clipboard-read', 'clipboard-write'], { origin: URL });
    return c;
};
const portapapeles = (p) => p.evaluate(() => navigator.clipboard.readText());

// ══ ADMINISTRADOR ══════════════════════════════════════════════════════════════════════════════════════════
console.log('\n— Administrador');
const cA = await ventana();
const A = await cA.newPage();
await A.goto(URL, { waitUntil: 'load' });
await A.locator('.pub-cabecera__acceso', { hasText: 'Ingresar' }).click();
await A.getByRole('link', { name: 'Acceso administradores' }).click();
await A.fill('input[name=usuario]', 'admin@colliers.test');
await A.fill('input[name=password]', CLAVE_INTERNA);
await A.getByRole('button', { name: 'Ingresar' }).click();
await A.waitForLoadState('load');
comprobar('admin: del sitio al panel con clics (Ingresar → Acceso administradores)', A.url().endsWith('/admin'), A.url());

// Configuración como la dejará Jonas en el sandbox: margen 5 s y aviso del cierre al 10 %.
await A.locator('.admin-lateral__item', { hasText: 'Configuración' }).first().click();
await A.waitForLoadState('load');
await A.fill('input[name="config[margen_liquidacion_segundos]"]', '5');
await A.fill('input[name="config[cierre_aviso_porcentaje]"]', '10');
await A.getByRole('button', { name: 'Guardar cambios' }).click();
comprobar('admin: margen 5 s y aviso del cierre al 10 % guardados', (await texto(A, '.admin-aviso--ok')).includes('Configuración guardada'));

await A.locator('.admin-lateral__item', { hasText: 'Subastas' }).first().click();
await A.waitForLoadState('load');
const selectorMartillero = A.locator('[data-martillero-prueba]');
comprobar('admin: con varios martilleros, la franja pregunta cuál queda a cargo', await selectorMartillero.isVisible());
// Inicio en 2 minutos: el espectador alcanza a abrir la página antes de que empiece (caso del 21/09).
await A.fill('.admin-pruebas__acciones input[name=inicio]', '2');
await A.fill('.admin-pruebas__acciones input[name=lotes]', '2');
await A.fill('.admin-pruebas__acciones input[name=postores]', '2');
// Sin elegir martillero, el navegador no deja enviar (campo obligatorio).
await A.getByRole('button', { name: 'Crear subasta de prueba' }).click();
comprobar('admin: sin elegir martillero no se crea', A.url().endsWith('/admin/subastas') && (await A.locator('[data-postores-prueba]').count()) === 0);
await selectorMartillero.selectOption({ label: 'M. Ossandón' });
await A.getByRole('button', { name: 'Crear subasta de prueba' }).click();
await A.waitForSelector('[data-postores-prueba]');
const ficha = A.url().split('#')[0];
const folio = (await texto(A, '.admin-gestion__migas')).split('/').pop().trim();
comprobar('admin: la ficha muestra al martillero a cargo con su correo',
    (await texto(A, '[data-martillero-nombre]')) === 'M. Ossandón' && (await texto(A, '[data-martillero-correo]')) === 'mossandon@colliers.test');
const filas = A.locator('[data-postor-prueba]');
comprobar('admin: dos postores con correo y clave en su fila', (await filas.count()) === 2 && await filas.first().locator('[data-clave]').isVisible());
const correos = [(await texto(filas.nth(0), '[data-correo]')).trim(), (await texto(filas.nth(1), '[data-correo]')).trim()];
await filas.first().getByRole('button', { name: /Copiar la clave/ }).click();
const clavePostores = (await portapapeles(A)).trim();
comprobar('admin: copia la clave con el botón', clavePostores.length >= 12);
await A.waitForTimeout(1200);
await A.waitForTimeout(1500); // animación de entrada
await A.screenshot({ path: path.join(SALIDA, '01-admin-ficha-prueba.png'), fullPage: true });

// «Ingresar como …»: abre otra pestaña que explica la sesión abierta; se copia el enlace para una ventana privada.
async function enlaceIngreso(enlace) {
    const [pestana] = await Promise.all([cA.waitForEvent('page'), enlace.click()]);
    await pestana.waitForLoadState('load');
    await pestana.getByRole('button', { name: 'Copiar enlace' }).click();
    const copiado = (await portapapeles(pestana)).trim();
    await pestana.close();
    return copiado;
}
async function entrar(enlace, clave) {
    const c = await ventana();
    const p = await c.newPage();
    await p.goto(enlace, { waitUntil: 'load' }); // pegar el enlace copiado en la ventana privada
    await p.fill('input[name=password]', clave);
    await p.getByRole('button', { name: 'Ingresar' }).click();
    await p.waitForLoadState('load');
    return { c, p };
}

// ══ MARTILLERO ═════════════════════════════════════════════════════════════════════════════════════════════
console.log('\n— Martillero');
const enlaceM = await enlaceIngreso(A.getByRole('link', { name: 'Ingresar como este martillero' }));
comprobar('martillero: el enlace usa el acceso de administración', enlaceM.includes('portal=admin'), enlaceM);
const { c: cM, p: M } = await entrar(enlaceM, CLAVE_INTERNA);
comprobar('martillero: entra y cae en «Tus remates»', M.url().endsWith('/admin') && (await texto(M, 'h1')).includes('Tus remates'), M.url());
// Tiene otros remates (los del seeder): elige el de prueba por su folio, como haría una persona.
const miRemate = M.locator('[data-mi-remate]', { hasText: folio });
comprobar('martillero: ve su subasta de prueba apenas entra, marcada PRUEBA, con «Abrir panel en vivo»',
    (await miRemate.count()) === 1 && (await miRemate.innerText()).includes('PRUEBA')
    && await miRemate.getByRole('link', { name: 'Abrir panel en vivo' }).isVisible(), folio);
comprobar('martillero: no ve el resumen general ni datos de postores', !(await texto(M)).includes('Pendientes de aprobación'));
await M.waitForTimeout(1500); // animación de entrada
await M.screenshot({ path: path.join(SALIDA, '02-martillero-inicio.png'), fullPage: true });
await miRemate.getByRole('link', { name: 'Abrir panel en vivo' }).click();
await M.waitForLoadState('load');
comprobar('martillero: abre el panel en vivo de SU subasta de prueba', M.url().includes('/en-vivo') && (await texto(M)).includes(folio), M.url());

// ══ POSTORES ═══════════════════════════════════════════════════════════════════════════════════════════════
console.log('\n— Postores');
const postores = [];
for (let i = 0; i < 2; i++) {
    const enlace = await enlaceIngreso(filas.nth(i).getByRole('link', { name: 'Ingresar como este postor' }));
    const { c, p } = await entrar(enlace, clavePostores);
    comprobar(`postor ${i + 1}: entra con la clave copiada y llega a «Mi cuenta»`, p.url().endsWith('/mi-cuenta'), p.url());
    const cuenta = await texto(p);
    comprobar(`postor ${i + 1}: «Mi cuenta» dice habilitado para pujar`, cuenta.includes('HABILITADO PARA PUJAR') && !cuenta.includes('Bloqueado'));
    await p.getByRole('link', { name: 'Entrar a la sala de pujas' }).first().click();
    await p.waitForLoadState('load');
    comprobar(`postor ${i + 1}: entra a la sala con el botón`, p.url().includes('/sala'), p.url());
    postores.push({ c, p });
}
const [P1, P2] = postores.map((x) => x.p);

// ══ PRESENCIA (QA de Jonas, 18/10): el postor ve cuántos hay; el martillero, quiénes ════════════════════════════
console.log('\n— Presencia en la sala');
const conteoSala = (p) => texto(p, '.sala__historial-conteo');
const enLaSala = async () => texto(M, '[data-presencia]');
comprobar('postores: los dos ven «2 CONECTADOS» en la sala, sin recargar',
    await esperar(async () => (await conteoSala(P1)).includes('2 CONECTADOS') && (await conteoSala(P2)).includes('2 CONECTADOS'), 20000),
    (await conteoSala(P1)).slice(0, 80));
comprobar('martillero: «En la sala ahora» lista a los dos por nombre (se actualiza solo, cada 15 s)',
    await esperar(async () => { const t = await enLaSala(); return t.includes('2 conectados') && t.includes('Postor de prueba 1') && t.includes('Postor de prueba 2'); }, 25000),
    (await enLaSala()).slice(0, 160));
const htmlP1 = await P1.content();
comprobar('postor: su navegador no recibe el nombre del otro postor ni correos', !htmlP1.includes('Postor de prueba 2') && !htmlP1.includes(correos[1]));
// El postor 2 sale de la sala con el enlace a «Mi cuenta»: baja del contador sin esperar a que venza su latido.
await P2.locator('.sala-cabecera__usuario--enlace').click();
await P2.waitForLoadState('load');
comprobar('cuando un postor sale de la sala, el otro ve «1 CONECTADO» en segundos', await esperar(async () => (await conteoSala(P1)).includes('1 CONECTADO'), 8000),
    (await conteoSala(P1)).slice(0, 80));
comprobar('martillero: la lista baja a uno', await esperar(async () => { const t = await enLaSala(); return t.includes('1 conectado') && !t.includes('Postor de prueba 2'); }, 25000),
    (await enLaSala()).slice(0, 160));
await P2.getByRole('link', { name: 'Entrar a la sala de pujas' }).first().click();
await P2.waitForLoadState('load');
comprobar('al volver a la sala, vuelve a contar', await esperar(async () => (await conteoSala(P1)).includes('2 CONECTADOS'), 20000));
await M.waitForTimeout(800);
await M.screenshot({ path: path.join(SALIDA, '03b-martillero-presencia.png'), fullPage: true });
await P1.setViewportSize({ width: 375, height: 800 });
await P1.waitForTimeout(600);
const usoSala = await revisarUsabilidad(P1, 375);
comprobar('sala a 375 px con el contador: sin desborde', !usoSala.desborde, `desborde:${usoSala.desborde}`);
await P1.screenshot({ path: path.join(SALIDA, '03c-sala-presencia-375.png'), fullPage: true });
await P1.setViewportSize({ width: 1440, height: 900 });

// Espectador: desde la ficha, «Ver en el sitio» (la página pública del remate).
const [E] = await Promise.all([cA.waitForEvent('page'), A.getByRole('link', { name: 'Ver en el sitio' }).click()]);
await E.waitForLoadState('load');
const espectadorAntes = (await texto(E)).includes('PRÓXIMO REMATE');
comprobar('espectador: abre la página pública antes de que empiece (PRÓXIMO)', espectadorAntes);

// ══ EN VIVO ════════════════════════════════════════════════════════════════════════════════════════════════
console.log('\n— En vivo');
const panel = (p) => p.locator('.sala-panel');
const estadoSala = (p) => texto(p, '.sala-panel');
const abrio = await esperar(async () => {
    await panel(P1).locator('.sala-puja__rapida').first().click();
    return panel(P1).locator('.sala-puja__boton').isEnabled();
}, 100000);
comprobar('el primer lote abre a su hora y la sala lo muestra sin recargar', abrio);
comprobar('espectador: la página que esperaba pasa sola a EN VIVO (sin que la recargue)',
    await esperar(async () => (await texto(E)).includes('REMATE EN CURSO'), 25000), (await texto(E)).slice(0, 120));

await panel(P1).locator('.sala-puja__boton').click();
await P1.locator('.sala-modal__confirmar').click();
comprobar('postor 1 puja con el modal y va ganando', await esperar(async () => (await texto(P1, '.sala-estado__titulo')) === 'Vas ganando', 8000));
comprobar('postor 2 ve «Te superaron» sin recargar', await esperar(async () => (await texto(P2, '.sala-estado__titulo')) === 'Te superaron', 5000));
comprobar('martillero ve la puja y quién va ganando sin recargar', await esperar(async () => (await texto(M)).includes('Postor de prueba 1'), 6000), (await texto(M)).slice(0, 400));

await M.locator('textarea').first().fill('Última llamada para el lote 1');
await M.getByRole('button', { name: 'Publicar mensaje' }).click();
comprobar('los postores ven el mensaje del martillero sin recargar',
    await esperar(async () => (await texto(P1)).includes('Última llamada para el lote 1') && (await texto(P2)).includes('Última llamada para el lote 1'), 5000),
    'panel: ' + (await texto(M)).slice(0, 200));

await panel(P2).locator('.sala-puja__rapida').first().click();
await panel(P2).locator('.sala-puja__boton').click();
await P2.locator('.sala-modal__confirmar').click();
comprobar('postor 2 puja y pasa a ganar; postor 1 ve «Te superaron»',
    await esperar(async () => (await texto(P2, '.sala-estado__titulo')) === 'Vas ganando' && (await texto(P1, '.sala-estado__titulo')) === 'Te superaron', 6000));
await P1.waitForTimeout(1500); // animación de entrada
await P1.screenshot({ path: path.join(SALIDA, '03-sala-postor1-en-vivo.png'), fullPage: true });
await M.waitForTimeout(1500); // animación de entrada
await M.screenshot({ path: path.join(SALIDA, '04-martillero-en-vivo.png'), fullPage: true });

// ══ CIERRE ANTICIPADO por el martillero, con los postores y un espectador mirando ═══════════════════════════
console.log('\n— Cierre anticipado');
await M.getByRole('button', { name: 'Cerrar este lote ahora' }).click();
comprobar('martillero: el modal dice a quién se adjudica y por cuánto', (await texto(M, '.admin-modal')).includes('Postor #2'));
await M.locator('.admin-modal textarea').fill('Prueba de cierre anticipado');
const t0 = Date.now();
await M.getByRole('button', { name: 'Cerrar lote' }).click();

const cerrada = async (p) => {
    const t = await estadoSala(p);
    return !t.includes('PUJA ABIERTA') && (t.includes('CERRADO') || t.includes('ADJUDICADO'));
};
const vistoP1 = await esperar(() => cerrada(P1), 5000);
const msP1 = Date.now() - t0;
const vistoP2 = await esperar(() => cerrada(P2), 5000);
const msP2 = Date.now() - t0;
comprobar('postor 1 ve el lote CERRADO en segundos, sin recargar', vistoP1, `${msP1} ms · ${(await estadoSala(P1)).slice(0, 90)}`);
if (!vistoP1 || process.env.DEPURAR) {
    // Qué tiene la sala en memoria y qué entregan el JSON y el endpoint en este mismo instante.
    const volcado = await P1.evaluate(async () => {
        const d = window.Alpine.$data(document.querySelector('.sala-panel'));
        const slug = location.pathname.split('/')[2];
        const leer = async (u) => { try { const r = await fetch(u + '?t=' + Date.now(), { cache: 'no-store', headers: { Accept: 'application/json' } }); return { http: r.status, cuerpo: r.ok ? await r.json() : null }; } catch (e) { return { error: String(e) }; } };
        const resumen = (e) => e && e.lotes ? { generado: e.generado_en_ms, remate: e.estado, lote1: { id: e.lotes[0].id, estado: e.lotes[0].estado, cierra: e.lotes[0].cierra_en_ms } } : e;
        const json = await leer('/tiempo-real/' + slug + '.json');
        const php = await leer('/remates/' + slug + '/estado');
        return {
            sala: { loteId: d.loteId, generado: d.estado.generado_en_ms, lote: { id: d.lote.id, estado: d.lote.estado, cierra: d.lote.cierra_en_ms }, ahora: Math.round(d.ahora), fallos: d.fallos },
            json: { http: json.http, ...resumen(json.cuerpo) },
            php: { http: php.http, ...resumen(php.cuerpo) },
        };
    }).catch((e) => ({ error: String(e) }));
    console.log('      ' + JSON.stringify(volcado));
}
comprobar('postor 2 ve el lote CERRADO en segundos, sin recargar', vistoP2, `${msP2} ms · ${(await estadoSala(P2)).slice(0, 90)}`);
comprobar('ya nadie puede pujar en ese lote (botón deshabilitado)', await panel(P1).locator('.sala-puja__boton').isDisabled().catch(() => true));
comprobar('el espectador ve el lote cerrado sin recargar', await esperar(async () => /CERRADO|ADJUDICADO/.test(await texto(E)), 6000));
await P1.waitForTimeout(1500); // animación de entrada
await P1.screenshot({ path: path.join(SALIDA, '05-sala-postor1-cerrado.png'), fullPage: true });

// Pasado el margen (5 s): adjudicado, ganador y perdedor lo saben sin recargar; la sala pasa al lote 2.
// Cambio de lote evidente (21/09): el resultado del lote 1 PARA CADA POSTOR sigue a la vista al pasar al lote 2.
const avisoLote = (p) => texto(p, '.sala-avisos--panel [data-aviso-lote]');
comprobar('el ganador ve «Te adjudicaste el lote 1» (en verde) al pasar al lote 2',
    await esperar(async () => (await avisoLote(P2)).includes('Te adjudicaste el lote 1')
        && (await P2.locator('.sala-avisos--panel [data-aviso-lote]').getAttribute('class')).includes('es-ganado'), 20000),
    (await avisoLote(P2).catch(() => '')).slice(0, 160));
comprobar('el otro postor ve que el lote 1 se adjudicó a otro postor', await esperar(async () => (await avisoLote(P1)).includes('se adjudicó a otro postor'), 20000));
comprobar('el aviso dice qué se remata ahora y a qué hora abre', await esperar(async () => /Ahora se remata el lote 2: .+, abre a las \d{2}:\d{2}\./.test(await avisoLote(P1)), 5000),
    (await avisoLote(P1).catch(() => '')).slice(0, 200));
comprobar('la cabecera del panel y la franja nombran el lote', await esperar(async () =>
    (await texto(P1, '.sala-panel__estado')).startsWith('LOTE 2 DE 2 ·') && (await texto(P1, '.sala-ticker__estado')).startsWith('LOTE 2 DE 2 ·'), 5000));
comprobar('la sala pasa sola al lote 2, que todavía no abre', await esperar(async () => (await estadoSala(P1)).includes('PUJA AÚN NO ABRE'), 20000),
    (await estadoSala(P1)).slice(0, 120));
comprobar('el martillero ve el lote 1 adjudicado', await esperar(async () => /ADJUDICADO/i.test(await texto(M)), 20000));
comprobar('el espectador ve el resultado', await esperar(async () => /ADJUDICADO|CERRADO/.test(await texto(E)), 20000));
await P1.waitForTimeout(1500); // animación de entrada
await P1.screenshot({ path: path.join(SALIDA, '06-sala-postor1-lote2.png'), fullPage: true });

// ══ El martillero, de vuelta a su inicio: el remate sigue en vivo (lote 2 por venir) ════════════════════════
await M.locator('.admin-lateral__item', { hasText: 'Dashboard' }).first().click();
await M.waitForLoadState('load');
comprobar('martillero: su inicio sigue mostrando la subasta de prueba con el panel a un clic', (await M.locator('[data-mi-remate]', { hasText: folio }).count()) === 1);

// ══ Borrar la subasta con los postores en la sala: tienen que enterarse ════════════════════════════════════
console.log('\n— Borrar con la sala abierta');
await A.locator('.admin-lateral__item', { hasText: 'Subastas' }).first().click();
await A.waitForLoadState('load');
A.on('dialog', (d) => d.accept().catch(() => {}));
await A.locator('.admin-pruebas__tabla tr', { hasText: folio }).getByRole('button', { name: 'Borrar' }).click();
await A.waitForSelector('.admin-aviso--ok');
comprobar('admin: borra la subasta de prueba', (await texto(A, '.admin-aviso--ok')).includes('borrada'));
comprobar('postor en la sala: ve que el remate ya no está disponible, sin recargar', await esperar(async () => (await texto(P1)).includes('ya no está disponible'), 15000),
    (await estadoSala(P1)).slice(0, 120));
comprobar('postor en la sala: ya no puede pujar y tiene una salida a los remates publicados',
    await panel(P1).getByRole('link', { name: 'Ver remates publicados' }).isVisible() && (await panel(P1).locator('.sala-puja__boton').count()) === 0);
comprobar('espectador: ve que el remate ya no está disponible, sin recargar', await esperar(async () => (await texto(E)).includes('NO DISPONIBLE'), 15000));
await P1.waitForTimeout(1500); // animación de entrada
await P1.screenshot({ path: path.join(SALIDA, '08-sala-remate-borrado.png'), fullPage: true });
for (const { c } of postores) await c.close();
await E.close();

// ══ Cancelar un remate con un postor esperando en la sala ════════════════════════════════════════════════
console.log('\n— Cancelar con la sala abierta');
await A.fill('.admin-pruebas__acciones input[name=inicio]', '10');
await A.fill('.admin-pruebas__acciones input[name=lotes]', '1');
await A.fill('.admin-pruebas__acciones input[name=postores]', '1');
await A.locator('[data-martillero-prueba]').selectOption({ label: 'M. Ossandón' });
await A.getByRole('button', { name: 'Crear subasta de prueba' }).click();
await A.waitForSelector('[data-postores-prueba]');
const fila2 = A.locator('[data-postor-prueba]').first();
await fila2.getByRole('button', { name: /Copiar la clave/ }).click();
const clave2 = (await portapapeles(A)).trim();
const enlace2 = await enlaceIngreso(fila2.getByRole('link', { name: 'Ingresar como este postor' }));
const { c: cQ, p: Q } = await entrar(enlace2, clave2);
await Q.getByRole('link', { name: 'Entrar a la sala de pujas' }).first().click();
await Q.waitForLoadState('load');
comprobar('postor espera en la sala: PRÓXIMO, la puja aún no abre', (await estadoSala(Q)).includes('PUJA AÚN NO ABRE'));
await A.getByRole('button', { name: 'Cancelar remate' }).first().click();
await A.locator('textarea[name=motivo]').fill('Prueba de cancelación');
await A.locator('.admin-modal').getByRole('button', { name: 'Cancelar remate' }).click();
await A.waitForLoadState('load');
comprobar('admin: cancela el remate', (await texto(A)).includes('cancelado'));
comprobar('postor en la sala: ve REMATE CANCELADO sin recargar', await esperar(async () => (await estadoSala(Q)).includes('REMATE CANCELADO'), 8000), (await estadoSala(Q)).slice(0, 100));
comprobar('postor en la sala: sin formulario de puja y con salida a «Mi cuenta»',
    (await panel(Q).locator('.sala-puja__boton').count()) === 0 && await panel(Q).getByRole('link', { name: 'Ir a Mi cuenta' }).isVisible());
await Q.waitForTimeout(1500); // animación de entrada
await Q.screenshot({ path: path.join(SALIDA, '09-sala-remate-cancelado.png'), fullPage: true });
await panel(Q).getByRole('link', { name: 'Ir a Mi cuenta' }).click();
await Q.waitForLoadState('load');
const cuentaQ = await texto(Q);
comprobar('«Mi cuenta» dice que el remate fue cancelado, no «habilitado para pujar»',
    cuentaQ.includes('REMATE CANCELADO') && !cuentaQ.includes('HABILITADO PARA PUJAR') && !cuentaQ.includes('CÓMO CONSTITUIRLA') && !cuentaQ.includes('Entrar a la sala'), cuentaQ.slice(0, 200));
await Q.waitForTimeout(1500); // animación de entrada
await Q.screenshot({ path: path.join(SALIDA, '10-cuenta-remate-cancelado.png'), fullPage: true });
await cQ.close();

// ══ Usabilidad del inicio del martillero en el celular ═════════════════════════════════════════════════════
{
    const c = await ventana(375);
    const p = await c.newPage();
    await p.goto(URL, { waitUntil: 'load' });
    await p.locator('.pub-cabecera__menu-boton').click();
    await p.locator('.pub-menu__acceso', { hasText: 'Ingresar' }).click();
    await p.getByRole('link', { name: 'Acceso administradores' }).click();
    await p.fill('input[name=usuario]', 'mossandon@colliers.test');
    await p.fill('input[name=password]', CLAVE_INTERNA);
    await p.getByRole('button', { name: 'Ingresar' }).click();
    await p.waitForLoadState('load');
    comprobar('martillero en el celular: entra y ve «Tus remates»', (await texto(p, 'h1')).includes('Tus remates'));
    await p.waitForTimeout(500);
    const u = await revisarUsabilidad(p, 375);
    comprobar('martillero: su inicio a 375 px sin desborde y táctiles ≥ 44', !u.desborde && u.pequenos.length === 0, `desborde:${u.desborde} ${u.pequenos.slice(0, 3).join('; ')}`);
    await p.screenshot({ path: path.join(SALIDA, '07-martillero-inicio-375.png'), fullPage: true });
    await c.close();
}

await navegador.close();
console.log(fallas ? `\n${fallas} falla(s)` : '\nTodo OK');
process.exit(fallas ? 1 : 0);
