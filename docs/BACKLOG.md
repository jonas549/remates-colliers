# BACKLOG — Remates Colliers · Fase 3

> **Registro vivo del avance.** Última actualización: **2026-10-22** (última tanda de Jonas: enlaces de cabecera y pie, horarios de visita, rol Editor y 2FA del panel; en local, sin push).
> Se actualiza en el mismo commit que termina cada tarea (contrato de trabajo, `CLAUDE.md` §4).
> Si este archivo y `CLAUDE.md` §8/§10 no coinciden, **manda este archivo**.

**Criterio de `[x]`:** solo lo terminado y **verificado ejecutándolo** (pruebas, arnés o recorrido en
navegador). Leer el código no cuenta. Lo que solo se puede comprobar en el servidor queda `[ ]` con la
nota *(verifica Jonas en el sandbox)*.

---

## Resumen

| Bloque | Nombre | Estado | Avance |
|---|---|---|---|
| A | Entorno y servidor | **Completo** | 5/5 |
| T | Traspaso del diseño a Blade | **Completo** | 12/12 |
| B | Base del proyecto Laravel | **Completo** | 20/20 |
| C | Modelo de datos | **Completo** | 12/12 |
| J | Motor de subastas en tiempo real ⚠️ | En progreso | 31/35 |
| D | Autenticación y registro de postores | En progreso | 14/17 |
| K | Sala de puja conectada al motor real | En progreso | 20/21 |
| I | Remates y lotes + panel del martillero | **Completo** | 20/20 |
| V | Configuración autoadministrable y SMTP | En progreso | 9/11 |
| G | Postores | **Completo** | 8/8 |
| H | Garantías | En progreso | 10/12 |
| M | Notificaciones | En progreso | 8/9 |
| N | Sitio público | En progreso | 25/26 |
| L | Streaming | En progreso | 1/4 |
| E/F | Componentes y estructura del panel | En progreso | 3/5 |
| O | Reportes | En progreso | 8/9 |
| P | Seguridad | En progreso | 4/12 |
| Q | QA y carga | Pendiente | 0/6 |
| R | Despliegue a producción | Pendiente | 0/9 |
| S | Documentación | Pendiente | 0/4 |

## Orden de ejecución acordado

```
T → B → C → J(núcleo) → D → K → I → V → G → H → M → N → L → E/F → O → P → Q → R → S
```

Primero lo visible para mostrarlo al cliente; después lo riesgoso (J) lo antes posible.
A está fuera de la secuencia: lo hizo Jonas antes de empezar.

**QA de Jonas en el sandbox (17/09):** reporte y 48 capturas en `docs/qa/2026-09-17-sandbox/`. 37 casos pasaron. Corregidos: INC-2 (el JSON no reflejaba la apertura del lote siguiente) y OBS-4 (contador de la pestaña RECHAZADA). INC-1 (correo confirmado antes de aprobar) e INC-3 (sesión única por navegador) son comportamiento esperado. Pendiente del QA: el ciclo de pujas hasta la adjudicación, que Jonas retoma con `colliers:remate-demo`.

**QA de Jonas en el sandbox (20/09): ciclo completo de punta a punta.** Funciona; salieron 8 puntos, ordenados por él.
Orden acordado: **correcciones (1, 6, 8) → registro (5) → subasta de prueba (3) → ayudas del formulario (4) → usuarios
internos (2) → habilitación configurable (7)**.

| # | Qué | Estado |
|---|---|---|
| 1 | El martillero no podía abrir su panel en vivo | **Hecho (20/09)**: el listado le muestra solo sus remates, la ficha la ve en modo lectura y el 403 de un remate ajeno dice de quién es |
| 6 | «Cerrar sesión» desaparecía con el mouse encima | **Hecho (20/09)**: `.boton-enlace:hover` lo pintaba del azul del propio fondo (`#16265a`) |
| 8 | El cierre anticipado adjudicaba sin decir a quién | **Hecho (20/09)**: el modal nombra al adjudicatario y el monto, o avisa que quedará desierto, y que no se deshace |
| 5 | El registro de postor pierde lo escrito y los adjuntos al fallar la validación | **Hecho (20/09)**: los adjuntos se conservan entre intentos, el formulario dice qué falta y lleva el foco al error; un envío más pesado que `post_max_size` ahora se explica en vez de volver en blanco |
| 3 | Botón «Crear subasta de prueba» en `/admin/subastas` | **Hecho (20/09)**: franja propia (fuera de producción) que crea el remate publicado con lotes, postores habilitados y la clave común; con enlaces a la sala y botón para borrarlo con todo su rastro |
| 4 | «Inicio del remate» y «cierre de garantías» sin explicación en el formulario | **Hecho (20/09)**: ayuda bajo cada fecha y un resumen en palabras que se arma solo y avisa si el cierre de garantías queda después del inicio |
| 2 | No hay dónde crear usuarios internos (hoy solo `colliers:crear-usuario` por terminal) | **Hecho (20/09)**: pantalla **«06 Usuarios»** en el menú lateral: crear, editar, activar/desactivar, restablecer clave, desbloquear y cerrar sesiones |
| 7 | Inscripción por remate vs. postor habilitado una vez, configurable | **Hecho (20/09)**: interruptor en Configuración → Garantías, **apagado por defecto**, con el monto de la habilitación única **vacío** hasta que Colliers lo fije. Activarlo sin monto no se permite |

**Dónde vamos (18/10):** T, B, C, I y G cerrados; J, D, K, V, H, M, N y O hechos y probados en local, con lo que solo se
prueba en el sandbox pendiente de Jonas (plan en `docs/progreso/2026-09-21.md` §11). E/F empezado («06 Usuarios»).
18/10, QA de Jonas: favicon, cabecera y pie hacia el sitio corporativo y **presencia en la sala**, en local y sin push
(detalle en `docs/progreso/2026-10-18.md`). Siguiente: lo que salga del sandbox → L (streaming) → resto de E/F → P → Q → R → S.
OPcache sigue apagado en el sandbox: el camino de la puja y la presencia no ejecutan Laravel donde no hace falta
(`docs/RENDIMIENTO-SIN-OPCACHE.md`).
**20/10: sitio público en inglés** (ver N), completo en local y sin push: mecanismo `/en`, 886 cadenas en `lang/es` y
`lang/en`, formatos por idioma, conmutador en todas las cabeceras públicas y documentos «(in Spanish)». Falta que Jonas
revise el glosario y las cadenas dudosas, y que el cliente confirme la línea de la versión que rige.

**21/10, QA de Jonas sobre el sandbox (18 hallazgos en 4 grupos, en local y sin push):** grupo 1 hecho (K: aviso de
conexión con Pujar bloqueado, y el ganador ve su adjudicación en la sala cerrada y en Mi cuenta). Grupo 2 hecho (P:
HTTPS forzado por la aplicación, cabeceras con CSP, Turnstile en el registro; el límite de pujas solo se investigó y
espera la decisión de Jonas). El 2FA queda fuera de la tanda (Jonas lo confirma con el cliente). Grupo 3 hecho (L: la
sala sin video avisa que la transmisión no comienza; N: condición de garantía en inglés, descripción y Open Graph por
remate; G: el dashboard explica las cuentas que esperan el correo). Grupo 4 hecho (hover en el listado, «+0,3% sobre la
base», promedio de pujas con un decimal, página propia para el 405, buscador de Postores sin cortar). Decisiones
cerradas por Jonas el mismo día: el límite de pujas queda en 30/min; el porcentaje sin «sobre la base»; el video llega
en el estado y aparece sin recargar; la imagen para compartir es un archivo reemplazable. Pendiente de Jonas: claves de
Turnstile y la imagen de 1200 × 630 de Colliers. Detalle en `docs/progreso/2026-10-21.md`.

**22/10, última tanda de Jonas (en local, sin push; un commit por punto):** (2) cabecera y pie como el sitio corporativo (ver N); (3) ficha sin el bloque de visitas vacío y «Coordinar visita» junto a las bases (ver N); (4) **rol Editor**, con los permisos propuestos por Jonas **a confirmar con el cliente** (ver E/F); (1) **segundo factor por correo** para Administración al entrar al panel (ver P). ⚠️ **Antes del push: el correo saliente del sandbox tiene que funcionar**, o nadie de Administración entra al panel (salida de emergencia: `php artisan colliers:codigo-ingreso correo` en la terminal del servidor).

> Los bloques G a S tienen tareas derivadas de las reglas confirmadas (`CLAUDE.md` §3–§6). El detalle
> fino se completa al llegar a cada bloque; no se agrega funcionalidad que no esté definida.

---

## A — Entorno y servidor · Completo

Hecho y verificado por Jonas (15/09).

- [x] Hosting BanaHosting (cPanel/LiteSpeed) con PHP 8.4.24 y Composer 2.10.2
- [x] Base MariaDB y usuario creados
- [x] Deploy key de GitHub con alias SSH `github-colliers`
- [x] Script `~/scripts/deploy-colliers.sh` (reset, composer, migrate, cachés) con log
- [x] Cron de deploy cada 5 minutos

## T — Traspaso del diseño a Blade · Completo

Escritorio 1:1 (1120/1280/1366/1440) verificado con el arnés; tablet/móvil sin desborde ni táctiles < 44 px.
Corrida completa el 15/09; control del listado y filtros el 16/09 (detalle en `docs/PROGRESO.md` §1.1).

- [x] B0: Laravel 13, Vite + Alpine, fuentes locales, `.gitignore`, `.htaccess` (handler comentado)
- [x] Arnés de comparación visual y de usabilidad móvil (`tools/comparar/`)
- [x] Login
- [x] Registro de postor (corregido el `grid-column: span 2` que desbordaba)
- [x] Estado de cuenta (5 variantes)
- [x] Listado de remates, filtros verificados con `filtros-interaccion.mjs` (42/42 el 16/09)
- [x] Detalle de remate próximo
- [x] Detalle de remate en vivo
- [x] Sala de puja (móvil: barra fija inferior + hoja de puja)
- [x] Admin: dashboard, subastas, postores, reportes (adaptación móvil)
- [x] Recorrido «aprobar garantía» a 375 px automatizado
- [x] Raíz invertida: `/` es el listado, `/remates` redirige; `/revision` solo en local

## B — Base del proyecto Laravel · En progreso

- [x] Comandos `colliers:instalar`, `colliers:puede-desplegar`, `colliers:diagnostico` (con pruebas)
- [x] Programador: latido y cola por minuto con `--stop-when-empty`
- [x] ~~Clave de acceso al sandbox~~ **quitada el 21/09 a pedido de Jonas**: el sandbox es de acceso libre. Se mantiene `X-Robots-Tag: noindex, nofollow` fuera de producción (`NoIndexar`) *(`InfraestructuraTest::test_el_sitio_es_de_acceso_libre_y_fuera_de_produccion_no_se_indexa`)*
- [x] Páginas de error propias: 403, 404, 419, 429, 500, 503
- [x] **Página propia para el 405** *(QA de Jonas 21/10, 4.4: un GET a `/admin/usuarios/crear`, que solo recibe POST/PUT, mostraba la página de Symfony en inglés)*: «Esta página no se abre directamente» con la misma plantilla que la 404, en el idioma de la URL; dentro del panel, el botón vuelve al panel *(`InfraestructuraTest`; `qa-visibles.mjs`)*
- [x] Traducciones al español en `lang/es/`
- [x] `.env.example` completo y sin secretos
- [x] `users` con rol, estado y cambio de clave obligatorio (migración aditiva)
- [x] `php artisan test` 13/13 (16/09)
- [x] Push a `main` con aprobación de Jonas (16/09, `ce52a89`; segundo push `4bd54c0` con C, hook y handler)
- [x] Conectar el servidor: clone, `.env`, `APP_KEY` generada y respaldada, migrate, `colliers:instalar`, administrador creado *(Jonas, 16/09)*
- [x] Script de deploy con `colliers:puede-desplegar` y `colliers:instalar` *(Jonas, 16/09)*
- [x] Cron de `schedule:run` cada minuto y cron de deploy cada 5 minutos activos *(Jonas, 16/09)*
- [x] Handler PHP 8.4 aplicado en el servidor: `application/x-httpd-ea-php84` *(Jonas, 16/09)*
- [x] Sandbox responde en https *(Jonas, 16/09; la clave de acceso se quitó el 21/09)*
- [x] `colliers:diagnostico` sin errores ni avisos, latido del cron a 38 s *(Jonas en el sandbox, 16/09)*
- [x] Handler versionado en `public/.htaccess`: sitio carga tras el deploy de `4bd54c0` y `git status` limpio *(Jonas en el sandbox, 16/09)*
- [x] Hook pre-commit (`.githooks/pre-commit`) que verifica que `public/build` corresponde a los assets del commit (5 casos probados, 16/09)
- [x] Fortify en español (Bloque D, 16/09)
- [x] `maatwebsite/excel` para exportaciones *(4.0, registrado solo al exportar; Bloque O, 17/09)*

## C — Modelo de datos · Completo

Verificado el 16/09: 14 pruebas nuevas (27/27 en total) en SQLite y en MySQL 8.4 de Laragon; ciclo
migrate → seed → reset → migrate con datos en ambos motores. El servidor usa **MariaDB**: la primera
corrida real es el `migrate` del deploy.

- [x] Postores fuera de `users`: `postores` + `empresas` + `postor_documentos`; RUT cifrado con índice ciego (HMAC con subclave de APP_KEY), teléfono, estado de cuenta, `datos_extra` extensible
- [x] `remates`: folio, slug, título, estado, inicio, cierre de garantías, duración y pausa, incremento y % propios (opcionales), video de YouTube, martillero
- [x] `lotes`: remate, orden, datos del activo + `atributos` extensibles, precio base, `abre_en`/`cierra_en`, precio actual, ganador, total de pujas, cierre (cuándo, motivo, quién), lote de origen
- [x] `lote_imagenes`, `lote_visitas`, `documentos` (del remate o de un lote) *(documentos sin datos de ejemplo: no hay archivos)*
- [x] `garantias`: por remate, monto/%/base fijados al crear, estado, medio, comprobante, quién revisó y cuándo, motivo de rechazo
- [x] `pujas`: lote, postor, monto, hora de recepción con microsegundos, IP, user agent; el modelo impide editar y borrar
- [x] `puja_intentos`: intentos rechazados con motivo y detalle *(la escritura fuera de la transacción se prueba en J)*
- [x] `adjudicaciones`: lote (único), ganador, puja, monto, cierre, motivo, estado
- [x] `access_logs`, `configuraciones` (defectos del acta sembrados por `colliers:instalar`, sin pisar cambios), `notificaciones_log`
- [x] Fechas en UTC (cast `FechaUtc`, probado con hora de Santiago); todas las migraciones con `down()` probado con datos
- [x] Seeder de desarrollo con el catálogo del prototipo (garantía 10 % e incremento del acta); se niega a correr en producción *(las vistas siguen con `App\Demo` hasta K/N)*
- [x] Migraciones aplicadas por el deploy automático en MariaDB 11.4.13, las 6 en `Ran` *(Jonas en el sandbox, 16/09)*

## J — Motor de subastas en tiempo real ⚠️ · En progreso

Leer `CLAUDE.md` §5 antes de empezar. **No se cierra sin pruebas de concurrencia automatizadas.**

Verificado el 16/09: `MotorPujasTest` (22 pruebas; 49/49 en total en SQLite y MySQL 8.4) y `tools/concurrencia/prueba.php`
contra Apache de Laragon (mod_php multihilo, OPcache) + MySQL 8.4: 4 corridas con 20 postores y 2 con 40, todas en verde
tras corregir la propia prueba. Latencia con 20 pujas simultáneas: mediana ~300 ms (se serializan en el bloqueo del lote).

**Validación de la puja**
- [x] Endpoint `POST /remates/{remate}/lotes/{lote}/pujas` con transacción, `lockForUpdate` sobre el lote y 5 reintentos ante deadlock
- [x] Remate disponible, lote abierto, cuenta aprobada (releída de la base en cada puja), garantía aprobada del remate
- [x] Monto ≥ precio base (primera) o actual + incremento; el postor no es quien va ganando; solo enteros de pesos
- [x] Validez por hora de recepción (middleware global `HoraRecepcion`, antes de esperar el bloqueo)
- [x] Rate limiting sobre el endpoint (30 por minuto por postor, en `config/colliers.php`)
- [x] Intentos rechazados registrados fuera de la transacción, con motivo, detalle, IP y user agent
- [x] **Cada rechazo dice exactamente qué pasó** *(OBS-7 del QA en el sandbox, 17/09: pujar después del cierre respondía «Este remate no está disponible para pujar»)*: el lote cerrado informa su hora de cierre aunque el remate ya esté finalizado; el lote que no abre, su hora de apertura; el monto bajo, la puja mínima; la cuenta, si está sin confirmar, en revisión, rechazada o bloqueada; la garantía, si falta inscripción, está pendiente, en revisión o rechazada

**Temporizador y cierre**
- [x] Endpoint de sincronización de reloj (`GET /hora`) *(el cronómetro del navegador es K)*
- [x] Cierre perezoso idempotente + margen de liquidación configurable; sin anti-sniping (una puja nunca toca `cierra_en`)
- [x] Adjudicación automática; lote desierto; detectores: puja rechazada por cierre, endpoint de estado y `colliers:liquidar` cada minuto
- [x] Paso al siguiente lote: horario fijo, cada lote abre a su `abre_en`; la apertura se materializa como el cierre (idempotente) y **reescribe el JSON en cada transición**, aunque nadie tenga la sala abierta (cron cada minuto, sala, panel, endpoint de estado y ficha pública); el remate se finaliza al liquidar el último *(corregido el 17/09 por el QA del sandbox, INC-2)*
- [x] Cierre manual de emergencia: endpoint para administrador o el martillero del remate *(la pantalla es del Bloque I)*

**Difusión en tiempo real**
- [x] Interfaz de emisión abstraída (`App\Subastas\Difusion\Emisor`; implementación JSON estático; Pusher = otra clase)
- [x] Escritura atómica (temporal + rename) bajo bloqueo de archivo por remate; el archivo nunca retrocede (probado con 300 pujas concurrentes)
- [x] Cabeceras sin caché y sin ETag; bloqueos y temporales no descargables (403) *(verificado en Apache local)*
- [x] Cabeceras sin caché servidas por **LiteSpeed**: HTTP/2 200, `no-store, no-cache, must-revalidate, max-age=0`, sin ETag, 403 en `.htaccess` *(Jonas en el sandbox, 17/09)*
- [x] Eventos: puja nueva, cierre de lote, **apertura del siguiente** (se publica aunque no haya pujas ni espectadores), mensaje del martillero
- [x] Estado público sin identidades: «Postor #N» por orden de garantía *(supuesto vigente)*
- [x] Reconexión recuperando el estado actual (`GET /remates/{remate}/estado`, que además liquida lo vencido) *(el cliente es K)*

**QA obligatorio**
- [ ] Medición de límites del sandbox (EP, CPU, `max_execution_time`, cron, HTTP saliente). **OPcache: APAGADO** (Jonas, 17/09; no se activa desde cPanel). Núcleos efectivos: `php tools/sandbox/medir-servidor.php https://rematescolliers.sandboxdelta.com` *(Jonas)*

**Sin OPcache** (17/09, `docs/RENDIMIENTO-SIN-OPCACHE.md`). Medido con Apache de Laragon fijado a 1 y 2 núcleos, sin OPcache:
10 pujas simultáneas pasaban de 670 ms (con OPcache) a 5,7 s, y la hora de recepción se sellaba hasta 7,3 s tarde.
- [x] Hora de recepción desde `REQUEST_TIME_FLOAT` (antes de arrancar Laravel): sello a 0,15 s del envío con 10 simultáneas en 1 núcleo (antes 4,5 s)
- [x] `public/hora.php` sin framework para sincronizar el reloj: 15 ms con 10 simultáneas (antes 4 s); `/hora` queda de respaldo
- [x] Menos consultas por puja (configuración recordada 2 s, resumen con una lectura del alias)
- [x] Escenario D en `tools/concurrencia/prueba.php` (pujas 400 ms antes de T + liquidaciones en T + margen): verde sin OPcache en 1 núcleo, 0 pujas perdidas
- [x] Herramientas: `tools/rendimiento/medir.php` (local, con y sin OPcache, N núcleos) y `tools/sandbox/medir-servidor.php` (servidor real, solo GET)
- [x] Dos pujas del mismo monto en el mismo instante: solo una gana (5 rondas × 20 simultáneas)
- [x] Puja bajo el incremento / sin garantía / después del cierre: rechazadas
- [x] Vaciar la caché a mitad del remate no altera el estado (`optimize:clear` en PHPUnit, `cache:clear` en concurrencia)
- [x] El ganador registrado coincide con la última puja válida (ráfagas y cierre disputado por 20 liquidaciones)
- [x] Simulación de 20 postores en paralelo (Apache de Laragon + **MySQL 8.4**)
- [x] Concurrencia contra **MariaDB real** del sandbox: 3 postores simultáneos al mismo monto, gana uno solo, sin doble adjudicación, confirmación en ~1,0–1,2 s *(Jonas, 17/09)*
- [ ] En el sandbox: paso automático al siguiente lote y cierre anticipado con ganador real *(el demo tenía un solo lote; `colliers:remate-demo` ahora crea 3 por defecto)*
- [x] **Latencia del JSON tras el cierre** *(QA del sandbox, 17/09: el feed mostró el lote abierto durante un minuto)*. Aprobado A + B: **(A)** el espectador deduce el cierre con la hora del servidor y cambia el estado al segundo, sin pedirle nada al servidor; **(B)** pasado el margen, **un solo** aviso al endpoint de estado, con espera al azar y porcentaje de espectadores configurables desde el panel, que materializa la adjudicación y reescribe el JSON en segundos. Si el servidor responde mal (por ejemplo 429), el cliente se rinde y espera al cron, que sigue como respaldo *(`tools/comparar/cierre-espectador.mjs`, 13 comprobaciones)*
- [x] Comando `colliers:remate-demo`: remate marcado `es_demostracion`, postores aprobados con garantía y claves aleatorias mostradas una vez; se niega con APP_ENV=production (16/09)
- [x] **Reintento de conexión en la puja** *(21/09, aprobado por Jonas; riesgo de R)*: solo en `POST …/pujas` y solo ante errores de conexión (1040, 1203, 1226, 2002, 2006), cada 0,5 s hasta 1,5 s. La hora de recepción no cambia (sellada por PHP antes de Laravel). **Margen de liquidación seguro = 5 s** (reintento + procesamiento sin OPcache): el panel exige confirmar un margen menor y lo advierte mientras siga bajo; `colliers:diagnostico` también. *(`ConexionPujaTest` 7; `ConexionPujaMysqlTest` 2 con una conexión rechazada de verdad: la puja llega antes del cierre, la base vuelve después y la puja entra con su hora; `panel-configuracion.mjs`)*
- [ ] Sandbox con `APP_ENV=staging` para poder crear el remate de demostración *(Jonas en el servidor)*
- [ ] Prueba del transporte en el sandbox con espectadores simulados, sobre el remate de demostración
- [x] Bloqueo de deploy con remate en curso o por comenzar (30 min antes; `colliers:puede-desplegar` sale 75)

## D — Autenticación y registro de postores · En progreso

Verificado el 16/09: `AutenticacionTest` (17 pruebas; 70/70 en total). Arnés visual: Login, Registro y Estado de cuenta
en **0 %** en escritorio (1120–1440), estos dos últimos ya con sesión real; admin 0,005–0,01 % (el rol dice
«Administración» y no «Administradora»). Pantallas sin diseño: `tools/comparar/sin-original.mjs` sin desborde ni
táctiles < 44 px en 375/760/1120/1440, y revisadas a ojo.

- [x] Registro de postor (Fortify): campos del diseño, RUT con dígito verificador y único, empresa (una empresa = una cuenta), documentos JPG/PNG/PDF ≤ 5 MB en disco privado
- [x] Verificación de correo; al verificar, la cuenta pasa de `registrado` a `en_revision`
- [x] Login de postor (`/ingresar`, correo o RUT) y de administración (`/admin/ingresar`), separados; cada uno rechaza al otro rol
- [x] Recuperación de contraseña por correo; cambio de la propia (exige la actual, cierra las demás sesiones); cambio obligatorio (`debe_cambiar_clave`)
- [x] Restablecer la clave de otra cuenta desde administración (clave temporal mostrada una vez, cambio obligatorio, cierra sus sesiones) *(endpoint; el botón va en la ficha, Bloque G)*
- [x] Registro de accesos (ingresos, fallos con motivo, bloqueos, salidas, registro, verificación, cambios de clave)
- [x] Bloqueo tras 5 intentos durante 15 minutos con el contador en la tabla `users` (vaciar la caché no desbloquea); desbloqueo desde administración
- [x] Sesiones activas y cierre remoto (una, o todas las demás); nunca se cierra una sesión ajena por id
- [x] Middleware de rol (`/admin` para admin y martillero; acciones de cuentas solo admin) y policy de documentos; consultas filtradas por dueño en lo existente *(G y H lo extienden a postores y garantías)*
- [x] Pantallas que el diseño no tiene, con el diseño del Login: acceso de administradores, recuperar y restablecer contraseña, verificar correo, cambiar contraseña, confirmar contraseña, sesiones activas
- [x] **Bug del sandbox (17/09): el ingreso de administración «solo se refrescaba», sin mensaje.** La clave pasaba (`ingreso` en `access_logs`) y la sesión con usuario existía en la base, pero el navegador no la presentó en la petición siguiente; la causa exacta de eso en el servidor está sin confirmar. Corregido para que nunca falle en silencio: (1) la clave del sandbox no expulsa una sesión autenticada y le devuelve su cookie; (2) tras ingresar, si la sesión no llega, vuelve al acceso con el motivo y anota en el log el diagnóstico (nombres de cookies, esquema, host, configuración de sesión); (3) la clave del sandbox responde 403 con motivo a las peticiones JSON (antes, un redirect que el panel mostraba como «Listo.») y explica en `/acceso` por qué la pide; (4) sesión vencida en acciones por fetch: 419 con mensaje en español; (5) el login muestra cualquier error. *(`AutenticacionTest`, `InfraestructuraTest`, `tools/comparar/ingreso-servidor.mjs`; los puntos 1 y 3 se fueron con la clave de acceso el 21/09)*
- [ ] En el sandbox: el primer administrador entra por `/admin/ingresar` y cambia su clave temporal *(Jonas en el sandbox; si vuelve a fallar, la pantalla lo dice y el log `storage/logs/laravel-AAAA-MM-DD.log` trae «Ingreso sin sesión»)*
- [x] **El registro no pierde nada al fallar la validación** *(QA del 20/09, punto 5)*: los documentos válidos se guardan en el disco privado y se reponen en el intento siguiente (`DocumentosDelRegistro`), el formulario abre con un resumen de lo que falta y el foco en el primer error, cada documento dice «Ya adjuntado: archivo» y el botón pasa a «Reemplazar». La contraseña nunca vuelve al navegador (Laravel la excluye a propósito) y el aviso lo dice. `colliers:limpiar-temporales` (programador, 04:10) borra los de registros abandonados a las 24 h *(`AutenticacionTest` 3 pruebas nuevas; `registro-acceso.mjs` 6 comprobaciones nuevas)*
- [x] **Un envío más pesado que `post_max_size` se explica** *(misma causa raíz)*: PHP descarta el cuerpo entero —campos, archivos y token—, así que el formulario volvía vacío o con un 419 «sesión expirada». `SubidaDemasiadoGrande` (antes del CSRF) responde la página 413 diciendo cuánto pesó el envío y cuánto acepta el servidor. `colliers:diagnostico` avisa si `post_max_size` no alcanza para el registro completo (3 documentos de 5 MB) ni para los documentos de 20 MB del panel
- [x] **Región del registro: las 16 regiones** *(QA de Jonas, 22/10: había 4 y «Otra»)*: de Arica y Parinacota a Magallanes, de norte a sur, y «Otra / extranjero» al final (`RegionesChile::delRegistro()`, la misma lista que ordena los filtros del sitio). Se guarda el nombre corto, **el mismo formato que ya tenían los registros** («Metropolitana», «Biobío», «Otra»): los existentes no cambian. Ahora se valida contra la lista (antes aceptaba cualquier texto). Sin región elegida de antemano («Elige tu región»): con Arica primera, una opción por defecto habría dejado datos equivocados. Los nombres de región no se traducen en `/en` (decisión del 20/10). La comuna sigue como texto libre y no se valida contra la región: el selector dependiente queda propuesto a Jonas *(`AutenticacionTest`; `registro-acceso.mjs` 33/33 eligiendo la región como una persona; `ingles.mjs` en verde)*
- [ ] Correos reales de verificación y recuperación: hoy `MAIL_MAILER=log` (el enlace queda en `storage/logs`); SMTP en el Bloque V
- [ ] **Subir `post_max_size` y `upload_max_filesize` en el sandbox** *(Jonas, cPanel → MultiPHP INI Editor)*: hoy el local tiene 12 MB / 8 MB y el registro necesita ~21 MB; con menos, el envío no llega nunca. Recomendado: `post_max_size=64M`, `upload_max_filesize=20M`. Verificar después con `colliers:diagnostico`

## K — Sala de puja conectada al motor real · En progreso

Verificado el 16/09: `SalaTest` (5; 75/75 en total) y `tools/comparar/sala-real.mjs` en navegador real contra el motor
(26/26, dos corridas, 0 errores en el log): dos postores a la vez + un tercero + móvil, reloj del equipo adelantado
7 min, reconexión sin red y cierre con adjudicación. Arnés 1:1 con `?demo=1` (datos del prototipo, solo local):
interacción idéntica; escritorio 0,05 % en 1280–1440 y 1,9 % en 1120 por el aviso de fuente oficial (acta, ver abajo).

- [x] Estado inicial desde el servidor (precio, ganador, `cierra_en`); solo entra el postor con cuenta y garantía del remate aprobadas
- [x] Botones de puja rápida CLP 100k / 500k / 1M (desde `configuraciones`)
- [x] Campo «Otro» respetando el incremento mínimo (validación en pantalla y en el motor)
- [x] Modal de confirmación obligatorio antes de registrar la puja; sin doble envío
- [x] Indicador «vas ganando» / «te superaron» (y «sin posturas» cuando nadie ha pujado)
- [x] Actualización en tiempo real (JSON estático cada ~1 s; respaldo al endpoint de estado; reconexión al volver la red o la pestaña)
- [x] Cronómetro sincronizado con `/hora` compensando la latencia (probado con el equipo 7 min adelantado)
- [x] Aviso visible: «Precio y cronómetro oficiales: el video tiene 10–30 s de retraso» *(a 1120 px ocupa una segunda línea: diferencia deliberada con el diseño)*
- [x] Mensajes de rechazo del motor en español, más sesión vencida (419), exceso de pujas (429) y sin conexión
- [x] Cierre: el formulario desaparece en `cierra_en` y el navegador dispara la liquidación pasado el margen; resultado para ganador y perdedores
- [x] Varios lotes: «LOTE N DE M» en la cabecera, la ficha (dirección, datos, precio base) sigue al lote vigente y un aviso cuenta cómo terminó el anterior *(sin diseño: aviso mínimo con los tokens de la sala; ver Decisiones)*
- [x] Mensaje del martillero en la sala, sin recargar: dentro del panel de puja en escritorio y sobre el video bajo 1120 px *(sin diseño, mismo criterio)*
- [x] Textos del resultado neutros («Colliers te informará sobre tu garantía según las bases») mientras el cliente no defina qué pasa con la garantía del perdedor
- [x] Verificado en navegador real (17/09): `sala-real.mjs` 32/32, incluido un remate de dos lotes con mensaje del martillero en escritorio y 375 px
- [x] **La sala nunca muestra «PUJA ABIERTA» en un remate que ya no se puede pujar** *(21/09)*. Reproducido con los tres roles: el cierre anticipado del martillero llega a los postores en ~1–2 s sin recargar (luego la adjudicación y el paso al lote 2). Lo que sí fallaba: con el remate **borrado** la sala seguía como si nada, y con el remate **cancelado** la sala, al llegar la hora, habría calculado «PUJA ABIERTA» por reloj. Ahora dice «REMATE NO DISPONIBLE» / «REMATE CANCELADO», quita el formulario y ofrece la salida (remates publicados / «Mi cuenta»); el nombre del postor en la cabecera lleva a «Mi cuenta» *(`tools/comparar/recorrido-roles.mjs`: administrador, martillero, dos postores y un espectador en sesiones separadas, solo con clics; 48/48 en tres corridas seguidas)*
- [x] **El cambio de lote es evidente** *(Jonas, 21/09: cerró el lote 1 con «Cerrar este lote ahora», el lote 2 abrió a su hora y la sala no dejó claro que había cambiado de lote)*: la franja y la cabecera del panel nombran el lote («LOTE 2 DE 2 · PRÓXIMO · PUJA AÚN NO ABRE»); el aviso «CAMBIO DE LOTE · AHORA LOTE 2 DE 2» va primero, dice el resultado del lote anterior **para ese postor** («Te adjudicaste el lote 1 en $X…», en verde; «se adjudicó a otro postor»; «cerró sin posturas») y qué se remata ahora, con la hora de apertura o «en vivo». Antes, al pasar al lote 2, el ganador del lote 1 dejaba de ver que lo había ganado *(`sala-real.mjs` con un remate de dos lotes en escritorio y 375 px; `recorrido-roles.mjs` 50/50)*
- [x] **Presencia en la sala** *(QA de Jonas, 18/10)*: el postor ve cuántos están conectados («2 CONECTADOS», en la línea del conteo de posturas); el martillero y la administración ven además **quiénes**, en «En la sala ahora» del panel en vivo: nombre y apellido del postor o, si puja por una empresa, la razón social. Nada de RUT ni correo, y sin marca de habilitación (a la sala solo entra quien está habilitado). Sin websockets ni procesos persistentes: latido cada 15 s a `public/presencia.php`, **sin Laravel** (como `hora.php`), con un token firmado (HMAC derivado de `APP_KEY`) que la sala entrega al abrir; la presencia va a un archivo por remate en `storage/app/presencia` (no a la base: un latido no abre conexiones). Activo = latido en los últimos 45 s; al cerrar la pestaña, `sendBeacon` lo baja al instante. El JSON público lleva **solo el número** (`presencia.conectados`), escrito bajo el mismo bloqueo del emisor; los nombres salen de `GET /admin/subastas/{id}/presencia`, solo para administración y el martillero del remate, que el panel consulta cada 15 s. Los vencidos se limpian al construir el estado y en el cron de cada minuto (`colliers:liquidar`). Lógica en `App\Subastas\PresenciaSala`, sin dependencias, compartida por Laravel y el script *(`PresenciaSalaTest` 7, incluido el script en un proceso aparte que no carga ni Composer ni Laravel; `recorrido-roles.mjs` con 8 comprobaciones nuevas: dos postores ven «2 CONECTADOS», el martillero los ve por nombre, uno sale y baja a 1, vuelve y sube, el navegador del postor no recibe el nombre del otro, y la sala a 375 px sin desborde; `sala-real.mjs` y `panel-remates.mjs` sin regresiones)*
- [x] **Indicador de conexión y aviso de reconexión** *(QA de Jonas 21/10, 1.1: cortó la red 10 s a 390 px, otro postor pujó y la sala siguió en el precio viejo con «Te superaron», sin aviso)*: si el estado no llega en 5 s (cuatro sondeos perdidos) o el navegador queda sin red, una banda roja «SIN CONEXIÓN. El precio y el cronómetro pueden no estar actualizados. Pujar queda bloqueado hasta reconectar.» se pega arriba en todos los anchos; precio y cronómetro se atenúan, la barra del celular dice «SIN CONEXIÓN» y Pujar (panel, hoja, barra y confirmación del modal) queda bloqueado. Las consultas tienen un límite de 5 s para que una red colgada sin error no deje la sala esperando. Al volver el estado, todo se restablece solo *(`sala-real.mjs`: corte con `setOffline` en escritorio y a 375 px, y red caída **sin** evento «offline» bloqueando el JSON: la banda aparece sola en ~5,7 s y se va en < 1 s al volver)*
- [x] **El ganador ve su adjudicación después del cierre** *(QA 21/10, 1.2: lote 1 adjudicado y lote 2 desierto; el ganador entraba después y solo leía «LOTE 2 DE 2 · CERRADO SIN POSTORES»)*: con varios lotes y todos terminados, la franja dice «REMATE FINALIZADO» y el resultado lista cada lote para ese postor con los textos existentes (`lote_ganado` en verde, `lote_de_otro`, `lote_sin_posturas`); título `ganado_titulo` si ganó alguno, «Remate finalizado» si no. **Mi cuenta**: bloque «TUS ADJUDICACIONES» («Te adjudicaste el lote X en $Y…», enlace al remate), y con el remate terminado la banda deja de decir «Estás habilitado para pujar»: «REMATE FINALIZADO · Te adjudicaste la propiedad» o «El remate terminó». Mi cuenta materializa antes los lotes vencidos (cierre perezoso) *(`SalaTest` nuevo con dos lotes; `sala-real.mjs`: ganador y perdedor al cerrar, el ganador recarga la sala y sigue viendo su lote, y llega a Mi cuenta con un clic; 55/55)*
- [x] **«Base $185.000.000 · +7,3%»** *(QA de Jonas 21/10, 4.2: con el precio en $120.300.000 decía «+0% sobre el mínimo»)*: un decimal (`sobreBase` en `formato.js`, con la coma o el punto del idioma), y si aun así redondea a cero estando por encima, el monto («+$100.000»). Sin texto al final, por decisión de Jonas *(`qa-visibles.mjs`: «Base $185.000.000 · +7,3%» y «Starting $185,000,000 · +7.3%» con el remate del seeder)*
- [ ] Probar la sala en el sandbox (LiteSpeed + MariaDB) sobre el remate de demostración, **incluida la presencia** (`presencia.php` bajo LiteSpeed y la lista del panel) *(Jonas)*

## I — Remates y lotes, incluido el panel del martillero · Completo

Verificado el 17/09: `RematesAdminTest` (13; 92/92 en total) y `tools/comparar/panel-remates.mjs` en navegador real (36/36):
crear y publicar con el formulario del diseño, documento, segundo lote, foto, visita, cancelar desde el listado, panel en
vivo con mensaje y cierre anticipado, dashboard con datos reales, y usabilidad (sin desborde, táctiles ≥ 44 px) de
dashboard, subastas, ficha, lote y panel en vivo a 375/760/1120/1440 px. Pantallas sin diseño (ficha, lote, panel en vivo)
con los componentes del panel. Migración aditiva: `lotes.nota_cierre`, `remates.motivo_cancelacion`, `remates.remate_origen_id`.

- [x] Remates: crear (formulario del diseño, crea el remate con su primer lote), ficha de edición con datos, horario, duración, pausa, martillero y video de YouTube; folio `R-AAAA-NNN` y slug automáticos
- [x] Lotes: crear y editar activo, ficha extensible (`atributos`), precio base, duración propia; horario fijo recalculado al guardar
- [x] Reordenar lotes («↑ Subir» / «↓ Bajar» en la ficha): renumera, reprograma horarios y publica el JSON; bloqueado al abrir el primer lote *(faltaba; detectado en el QA del 17/09 — `RematesAdminTest`, `panel-remates.mjs`)*
- [x] Fotos (reducidas a 1920 px, foto principal, eliminar), documentos (público o solo con garantía aprobada; disco privado) y horarios de visita por lote
- [x] Incremento y porcentaje de garantía propios por remate (vacío = global)
- [x] Publicar (revisa lo que falta y lo lista) y cancelar con motivo antes de comenzar *(supuesto: máquina de estados en revisión, ver Decisiones)*
- [x] Panel del martillero (`/admin/subastas/{id}/en-vivo`): precio, cronómetro, ganador e historial con la identidad detrás de «Postor #N», mensaje a la sala y cierre anticipado con motivo, sin recargar; mismo transporte que la sala (JSON estático + `hora.php`)
- [x] «Crear remate nuevo» desde uno cerrado o cancelado: borrador nuevo con los lotes no adjudicados, fotos y documentos; el original no se toca
- [x] Dashboard y contador de pendientes del menú con datos reales (sin remates de demostración)
- [x] Usable desde el celular (hoja de acciones del listado, formularios en una columna, táctiles ≥ 44 px)
- [x] `colliers:crear-usuario admin|martillero correo nombre` con clave temporal de un solo uso *(InfraestructuraTest, 17/09)*. La pantalla de usuarios internos es el punto 2 del QA del 20/09
- [x] **El martillero trabaja con sus remates** *(QA del 20/09, punto 1)*: el listado del panel solo trae los remates a su cargo (antes ofrecía «Panel en vivo» de cualquiera y terminaba en 403), la ficha de los suyos la ve en **modo lectura** (horario, lotes, fotos y documentos; sin formularios ni acciones) y un remate ajeno responde 403 **diciendo de quién es y dónde están los suyos** (el 403 ahora muestra el motivo cuando quien aborta lo deja). Crear, editar, publicar, cancelar y mover lotes siguen siendo de administración *(`RematesAdminTest` 2 pruebas nuevas; `panel-remates.mjs` 5 comprobaciones nuevas en navegador real)*
- [x] **El cierre anticipado dice a quién se adjudica y por cuánto** *(QA del 20/09, punto 8)*: el modal nombra al adjudicatario con su alias y el monto, o avisa que el lote quedará **desierto** si nadie pujó, y que la acción no se deshace *(`RematesAdminTest::test_el_modal_de_cierre_anticipado_dice_a_quien_y_por_cuanto`; `panel-remates.mjs`)*
- [x] **Las fechas del formulario se explican solas** *(QA del 20/09, punto 4)*: ayuda bajo «Inicio del remate» (abre el primer lote), «Duración» (sin extensiones) y «Cierre de garantías» (va antes del inicio; vacío = N horas antes), más un resumen en lenguaje natural que se arma mientras se escribe: «Los postores pueden inscribirse y constituir la garantía hasta el … El remate abre el … y cada lote dura N minutos, sin extensiones», con aviso si el cierre de garantías quedó después del inicio. La ficha de edición lleva las mismas ayudas *(diferencia deliberada con el prototipo, como REGIÓN y el % de garantía; `panel-remates.mjs` 3 comprobaciones nuevas)*
- [x] **«Crear subasta de prueba»** *(QA del 20/09, punto 3)*: franja aparte en `/admin/subastas`, solo para administradores y **solo fuera de producción**. Crea un remate publicado con lotes, horario a los minutos que se indiquen (12 por defecto), cierre de garantías unos minutos antes, y postores aprobados con garantía aprobada; la clave común se muestra **una sola vez** junto a los enlaces a la ficha pública y a la sala («Mi cuenta» no lista los remates de prueba a propósito). Queda marcado `es_demostracion`: fuera del listado real, del sitio público, de los reportes y de los correos. «Borrar» lo elimina con sus postores, su bitácora y su JSON. `colliers:remate-demo` usa el mismo servicio (`App\Remates\RemateDePrueba`) *(`RematesAdminTest` 3 pruebas nuevas; `panel-remates.mjs` 8 comprobaciones nuevas, incluida la usabilidad a 375 px y un postor de prueba entrando a la sala)*
- [x] **La experiencia de prueba de punta a punta, sin escribir una URL** *(Jonas, 21/09: «Mi cuenta» del postor de prueba decía «Sin inscripciones» y «Bloqueado» siendo falso, y la clave estaba escondida en una frase)*: «Mi cuenta» muestra su remate de prueba con la garantía aprobada y «Entrar a la sala de pujas»; la ficha de la prueba tiene cada postor con correo y clave en la misma fila, botones Copiar, aviso de que la clave se muestra una vez, «Generar clave nueva» e «Ingresar como este postor» (acceso con el correo escrito, y si hay otra sesión abierta lo explica y ofrece cerrarla o copiar el enlace para una ventana privada). Remates y postores de prueba separados de los reales: no se cruzan inscripciones y no cuentan en Postores, dashboard ni reportes. De paso: «1 (global)» en vez de «10 (global)» en el % de garantía (`Formato::campo`) *(`PostoresDePruebaTest` 6; `panel-remates.mjs` reescrito sin URL: del panel a una puja real con el modal)*
- [x] **La subasta de prueba siempre tiene martillero a cargo** *(Jonas, 21/09: «el martillero no puede trabajar»)*: con un solo martillero activo se asigna solo; con varios, la franja pregunta cuál (obligatorio); sin ninguno, no se crea y la franja lleva a «06 Usuarios». La ficha muestra al martillero junto a los postores, con su correo, «Copiar» e «Ingresar como este martillero» (acceso de administración con el correo escrito). `colliers:remate-demo` con la misma regla y `--martillero=correo` *(`MartilleroTest`, `RemateDemoTest`, `tools/comparar/recorrido-roles.mjs`: administrador, martillero, dos postores y un espectador en sesiones separadas, solo con clics; 48/48 en tres corridas seguidas)*
- [x] **Inicio del martillero: «Tus remates»** *(21/09)*: al entrar ve sus remates —también los de prueba a su cargo, marcados PRUEBA—: en vivo ahora (con el lote que corre y su cierre), próximos y últimos cerrados, cada uno con «Abrir panel en vivo». Ya no ve el resumen general (traía nombres y RUT de postores). En «Subastas» también ve sus subastas de prueba *(`MartilleroTest`; recorrido de los tres roles; usable a 375 px)*
- [x] **Folio de la subasta de prueba demasiado largo** *(sandbox, 21/09: 500 con `1406 Data too long for column 'folio'`)*: `PRUEBA-` + sufijo sumaba 22 caracteres en un `VARCHAR(20)`; ahora `PR-` + sufijo (18). También rompía `colliers:remate-demo`, que usa el mismo servicio. SQLite no valida largos, por eso las pruebas pasaban: nuevo **`composer test:mysql`**, la suite completa contra MySQL estricto, que reproducía el error antes de la corrección (3 fallas) y queda en 163/163
- [x] **«Cerrar sesión» legible con el mouse encima** *(QA del 20/09, punto 6)*: `.boton-enlace:hover` lo dejaba del color del fondo marino de la barra *(`panel-remates.mjs` compara el color del texto con el del fondo)*

## V — Configuración autoadministrable y SMTP · En progreso

Reestructurada el 17/09 a pedido de Jonas: Configuración pasó de una pantalla larga a **9 pantallas**, desplegadas como
**sub-ítems del menú lateral** bajo «05 Configuración» (`/admin/configuracion/{seccion}`), cada una con lo suyo y
guardando solo sus claves. Verificado con `ConfiguracionTest` (13) y `tools/comparar/panel-configuracion.mjs`
(28 comprobaciones en navegador real, usabilidad a 375/760/1120/1440).

Secciones: Remates y pujas · Garantías · Correo (SMTP) · Plantillas de correo · Notificaciones · **Registro de correos** ·
Seguridad · Sitio · Sistema (solo lectura).

- [x] Incremento mínimo (global y por remate), montos de puja rápida, duración por defecto del lote y **pausa entre lotes** (nueva, 17/09)
- [x] Porcentaje de garantía (10 % por defecto), plazos y datos bancarios, con las instrucciones que ve el postor
- [x] Margen de liquidación (2 s por defecto; 5 s recomendado sin OPcache)
- [x] Intentos de login, duración del bloqueo y **duración de la sesión** (15 min a 12 h, 2 h por defecto; antes solo en el `.env`)
- [x] Fuente y valor de la UF, contacto, enlaces y textos legales
- [x] SMTP completo con **dos botones separados** (17/09): «Probar conexión» comprueba DNS, puerto, cifrado y credenciales SIN enviar nada y dice exactamente qué falló; «Enviar correo de prueba» pide la dirección y manda uno real. Los errores de envío usan el mismo diccionario
- [x] El correo de prueba **solo afirma que el servidor aceptó el mensaje**, nunca que llegó, y muestra la **respuesta literal del servidor con su código** («250 2.0.0 Ok: queued as …») y el identificador del mensaje; si el servidor rechaza, el motivo exacto *(corregido el 17/09: antes decía «enviado» sin saberlo)*
- [x] Aviso **antes de intentar** cuando el remitente no es el usuario SMTP autenticado (causa habitual de «553 Sender address rejected» y de los correos que nunca llegan)
- [x] **Registro de correos**: historial completo con búsqueda por destinatario o asunto, filtros por estado, tipo y fechas, exportación a CSV y **cada fila desplegable** con transporte, respuesta literal del servidor, Message-ID, remitente, destinatario, asunto y el error entero. El correo de prueba también queda registrado
- [x] **El estado sale del transporte, no de suponer** *(corregido el 17/09: la bitácora anotaba «enviada» con solo saber que el canal no falló, y con el modo «log» el correo no salía a ninguna parte)*: `aceptada` (el servidor lo recibió, con su respuesta), `registrada` («NO SALIÓ»: quedó en el archivo de registro), `fallida` (con el error), `pendiente` (en la cola) y `sin_verificar` para las filas anteriores a la corrección, que la migración marca así porque no hay forma de saber cuáles salieron
- [x] **Plantillas de correo editables** (16): asunto, cuerpo con variables, texto del botón, vista previa con datos de ejemplo y «Restaurar la original». Migración aditiva `plantillas_correo`; sin fila se usa el texto del código
- [x] **Notificaciones**: qué se envía, a quién y cuándo, con interruptor por correo (los de la cuenta del postor no se pueden apagar), correo de avisos, horas del recordatorio y los últimos 20 envíos de la bitácora
- [ ] Revisión del diseño de las pantallas nuevas por Ángel *(Jonas se las pasa)*
- [ ] SMTP real probado en el sandbox (las dos pruebas y un correo que llegue)

### Decisiones de Jonas sobre los correos (17/09)

- **«Puja superada» queda fuera:** con la cola por cron el correo llega tarde y el aviso ya está en vivo en la sala. En su
  lugar, al cerrar el lote se envía «Resultado del lote» a quienes pujaron y no ganaron.
- **Resumen al administrador:** un solo correo al cerrar el remate, con todos los lotes, adjudicados y desiertos, con
  montos (antes iba uno por lote).
- **Bienvenida:** es el mismo correo de confirmación de correo, ahora editable. No se envían dos al registrarse.

## G — Postores · Completo

> 17/09 (QA del sandbox, OBS-4): las pestañas son estados de la garantía, pero mientras la cuenta no está aprobada las
> acciones van sobre la cuenta; por eso una **cuenta rechazada** ahora cuenta en la pestaña RECHAZADA. Las pestañas de
> Subastas (Todas, En vivo, Próximas, Borradores, Cerradas) sí cubren todos los estados: se revisaron y no tenían el problema.

Verificado el 17/09: `PostoresGarantiasTest` (6; 104/104 en total) y `tools/comparar/recorrido-garantia.mjs` reescrito con
datos reales a 375 px (aprobar garantía desde la tarjeta y desde la ficha, aprobar y rechazar cuentas con motivo; persiste al
recargar). Pantalla del diseño «Admin Postores» ahora solo para administradores (datos personales).

- [x] Listado real: una fila por inscripción (postor + remate) y una por postor sin inscripciones; búsqueda, filtros por estado de la garantía y pestaña «Cuentas por aprobar» (nueva); exportar listado en CSV
- [x] Ficha con datos, domicilio, representante de la empresa, documentos del registro y comprobante descargables
- [x] Revisión de cuenta: aprobar / rechazar con motivo, con quién y cuándo, en la bitácora de accesos
- [x] Bloquear (con motivo) / desbloquear cuenta *(supuesto de la máquina de estados propuesta: aprobado ↔ bloqueado)*
- [x] Aprobación usable desde el celular (tarjetas, botones ≥ 44 px, ficha a pantalla completa)
- [x] **El buscador de Postores no se corta** *(QA de Jonas 21/10, 4.5: «Buscar por nombre, RUT o» con la sexta pestaña)*: placeholder «Nombre, RUT o remate» (la lupa ya dice buscar; la etiqueta accesible sigue completa) y 24 px a los lados en lugar de 32 *(`qa-visibles.mjs` mide el texto contra el campo en 375, 760, 1120, 1280, 1366 y 1440 px: el más justo, 143 de 162 px)*
- [x] Correo de aprobación, rechazo (con motivo), bloqueo y desbloqueo de la cuenta *(Bloque M, 17/09)*
- [x] **«Cuentas por aprobar» con el mismo criterio en el dashboard y en Postores** *(QA de Jonas 21/10, 3.3: el dashboard decía 0 y la pestaña 1, una cuenta «Sin confirmar correo»)*: una cuenta sin el correo confirmado todavía no se puede aprobar, así que el indicador sigue contando solo las aprobables y lo dice: «1 más espera confirmar su correo» / «N más esperan confirmar su correo». La pestaña de Postores las sigue mostrando, porque desde ahí se pueden rechazar *(`PostoresGarantiasTest`; `qa-visibles.mjs`: el número del dashboard más los que esperan el correo coincide con la pestaña)*

## H — Garantías · En progreso

Verificado el 17/09: `PostoresGarantiasTest`, `recorrido-garantia.mjs` y `tools/comparar/cuenta-postor.mjs` (9/9: subir comprobante
desde el celular, reenviar uno rechazado, cuenta aprobada con acceso a la sala a 375–1440 px).

- [x] Inscripción del postor en un remate (desde «Mi cuenta»; en el detalle público, Bloque N): garantía pendiente con el % vigente sobre la suma de precios base, fijado al crearla
- [x] Instrucciones de pago externo con los datos bancarios de Configuración (vale a la vista o transferencia); **sin pasarela**. Sin datos configurados: «escríbenos»
- [x] Carga de comprobante por el postor (PDF/JPG/PNG ≤ 10 MB, disco privado, medio), solo antes del cierre de garantías; reenvío tras rechazo conservando el anterior
- [x] Estados: pendiente, en revisión, aprobada, rechazada (motivo visible para el postor)
- [x] Aprobación y rechazo manual por un administrador, con quién y cuándo; exige cuenta aprobada; no se rechaza una aprobada con el remate en curso
- [x] Aprobar una garantía desde el celular con datos reales
- [x] «Mi cuenta» con datos reales: remate, monto, plazo, estado, inscripciones múltiples y acceso a la sala *(textos del destino de la garantía neutros)*
- [x] **«¿Puede pujar?» en una sola clase** *(QA del 20/09, punto 7)*: `App\Postores\Habilitacion` responde la condición del acta (registrado + cuenta aprobada + garantía aprobada de ESE remate), que estaba repetida en el motor de pujas, la sala, la ficha pública, el catálogo, los documentos reservados y el estado de la cuenta. Sin cambio de comportamiento y sin consultas nuevas en el camino de la puja *(`HabilitacionTest`, 4 pruebas; `sala-real.mjs` en navegador real contra el motor)*
- [x] **Modo «postor habilitado una vez para todos los remates»** *(decisión de Jonas, 20/09: interruptor apagado por defecto y monto configurable, vacío, para poder mostrarle la opción a Colliers y que ellos definan el monto)*. Configuración → Garantías: «Cómo se habilita un postor» (por remate / habilitación única) y «Monto de la habilitación única (CLP)». El panel **no deja activar el modo sin monto**: una garantía que no pertenece a un remate no tiene precio base que porcentuar. Con el modo activo, el postor constituye **una** garantía (`garantias.alcance = unico`, migración aditiva) que la administración aprueba como cualquier otra y que lo habilita en todos los remates; «Mi cuenta» lo explica y deja de pedir inscripción por remate, el plazo de un remate no la vence, y el feed numera «Postor #N» de los que pujan sin garantía de ese remate por el orden de su primera puja. Volver al modo por remate no borra nada: cada garantía sigue valiendo para su remate *(`HabilitacionTest` 8 pruebas; `tools/comparar/habilitacion-unica.mjs`, 9 comprobaciones en navegador real, incluidas la validación del monto, la puja en un remate donde nunca se inscribió y la vuelta atrás)*
- [x] **«Mi cuenta» con un remate cancelado dice la verdad** *(21/09)*: antes seguía diciendo «Estás habilitado para pujar… cuando comience la transmisión» y ofrecía subir el comprobante. Ahora: «REMATE CANCELADO», el motivo, que no se realizará, el paso 4 en «Remate cancelado», sin formulario de comprobante y con «Ver remates publicados» *(`PostoresDePruebaTest::test_mi_cuenta_de_un_remate_cancelado_no_dice_habilitado`; recorrido de los tres roles)*
- [ ] **Monto de la habilitación única**: lo fija Colliers desde el panel cuando decida usar el modo *(hoy vacío, modo apagado)*
- [ ] Destino posterior (devolución / imputación / ejecución) *(pendiente con el cliente)*

## M — Notificaciones · En progreso

> 17/09: el texto de todos los correos salió del código y pasó a **plantillas editables** (Configuración → Plantillas de
> correo). Cambios de envío decididos por Jonas: «Resultado del lote» a quienes pujaron y no ganaron (en vez de un correo
> por cada puja superada) y **un** resumen a la administración al cerrar el remate (antes uno por lote).

Verificado el 17/09: `NotificacionesTest` (5; 109/109 en total) con cola síncrona y correo en memoria, y en local con la cola
real `database` + `queue:work --stop-when-empty` (encola 1 trabajo, lo envía y la bitácora queda «enviada»). Tabla nueva
`suscripciones` (migración aditiva).

- [x] Envío por la cola del cron (`--stop-when-empty`, 3 reintentos) y bitácora `notificaciones_log` (enviada / fallida con el error, destinatario, asunto y registro relacionado)
- [x] Cuenta aprobada / rechazada / bloqueada / desbloqueada
- [x] Garantía aprobada / rechazada (con motivo) y comprobante recibido
- [x] Adjudicación: al adjudicatario y a la administración (al correo de avisos de Configuración o a todos los administradores); lote desierto, solo a la administración; marca `notificado_*` en la adjudicación. Los remates de demostración no envían correos
- [x] «Avísame»: `POST /avisame` (remates nuevos o un remate), aviso al publicar un remate, enlace de baja en cada correo *(sin doble confirmación, supuesto; el formulario del sitio es del Bloque N)*
- [x] Recordatorios (`colliers:recordatorios` cada 10 min, una sola vez por destinatario): N horas antes del inicio a inscritos con garantía aprobada y a suscriptores del remate, «falta tu garantía» a inscritos sin aprobar, y 48 h antes del cierre de garantías a suscriptores generales
- [x] Plantillas en español (asunto «… · Remates Colliers», saludo, pie con el contacto de Configuración y textos del correo base traducidos en `lang/es.json`)
- [ ] Envío real por el SMTP de Colliers y entrega en bandeja (no spam) *(Jonas en el sandbox)*

## N — Sitio público · En progreso

Verificado el 17/09: `SitioPublicoTest` (4; 113/113 en total), `filtros-interaccion.mjs` con datos reales (todo OK a 1440 y
375 px) y `tools/comparar/sitio-publico.mjs` (33/33: listado, detalle próximo y cerrado, «Avísame», calendario, detalle en vivo
que se actualiza solo con una puja real, inscripción desde el detalle a 375 px, usabilidad de listado, detalles y login a
375–1440 px). Ya no se usan `App\Demo\RematesDemo` ni `DetalleDemo` (quedan para el índice de revisión y `?demo=1`).

- [x] Listado con datos reales (sin borradores, cancelados ni remates de demostración) y filtros generados desde los datos; UF de Configuración; hero con el próximo remate real
- [x] **Hover en «Ocultar filtros», «Grilla» y «Tabla»** *(QA de Jonas 21/10, 4.1)*: el fondo celeste de los demás botones del listado; la vista activa se oscurece *(`qa-visibles.mjs`)*
- [x] Detalle de remate próximo, en vivo y cerrado con datos reales (ficha, antecedentes, fotos, varios lotes); bloqueo y botón según la cuenta y la garantía de ESE remate
- [x] Detalle en vivo que solo lee el JSON estático (cada 2 s) y `hora.php`: los espectadores no ejecutan el framework *(docs/RENDIMIENTO-SIN-OPCACHE.md)*
- [x] «Constituir la garantía» inscribe al postor desde el detalle (Bloque H)
- [x] Calendario `.ics`
- [x] Mapa (OpenStreetMap en gris) con las coordenadas del lote
- [x] Documentos descargables: públicos para todos; reservados solo con garantía aprobada del remate
- [x] Recomendados (próximos y en vivo)
- [x] Enlace al canal de YouTube de Colliers (Configuración; se oculta si está vacío) y «Compartir»
- [x] **Descripción y vista previa al compartir** *(QA de Jonas 21/10, 3.4: no había meta description ni Open Graph)*: cada ficha de remate lleva `description`, `og:title`, `og:description`, `og:url` (sin filtros ni parámetros) y `og:image` con la **foto principal real del lote** (`twitter:card` grande). La descripción dice dirección, comuna y tipo, precio base y, según el estado, la fecha de inicio, «en vivo» o el resultado; en inglés, en inglés. El listado y el resto de las páginas públicas usan los valores del sitio (`sitio.seo`) y el logo de Colliers. El panel no los lleva *(`IdiomaTest`; `qa-visibles.mjs`: la imagen de la ficha existe y carga)*
- [x] **Favicon** *(QA de Jonas, 18/10)*: el ícono de activosespeciales.colliers.cl (original de 512 px) en `favicon.ico` (16/32/48), PNG de 32, 180 (apple-touch), 192 y 512 y `site.webmanifest`, enlazados en `x-layouts.base`, que es el único `<head>`: el panel, el acceso, la sala y las páginas de error lo envuelven. `.htaccess` sirve el manifiesto como `application/manifest+json` *(verificado en local: sitio, acceso y panel con sesión; 179 pruebas)*
- [x] **Cabecera y pie hacia el sitio corporativo** *(QA de Jonas, 18/10)*: los ítems que no son de la aplicación enlazan a la página que existe en activosespeciales.colliers.cl, en pestaña nueva (`target="_blank" rel="noopener"`): Servicios, Nosotros, Política de privacidad, Términos de uso y las redes que publica ese sitio (LinkedIn, Instagram, YouTube). Sin página allí quedan **sin enlace**, con el mismo aspecto: Expertos, Propiedades, Investigación, Artículos, Carreras, Accionistas, Cookies, Accesibilidad y X; nada va a la portada como relleno. Los enlaces de Configuración → Sitio (privacidad, términos, bases) **mandan cuando tienen valor**; vacíos, la página corporativa (las bases no tienen: sin valor, sin enlace). URL base en `config/colliers.php` → `sitio_produccion` con `COLLIERS_SITIO_PRODUCCION` (por defecto la de producción: no hace falta tocar el servidor). El menú del panel no cambió *(`SitioPublicoTest::test_menu_y_pie_enlazan_al_sitio_corporativo_solo_donde_hay_pagina`)*
- [x] **Cabecera y pie sin enlaces muertos** *(Jonas, 22/10)*: revisado el sitio corporativo antes de borrar. Cabecera: **Expertos** enlaza a `activosespeciales.colliers.cl/expertos/` en pestaña nueva; **Propiedades** e **Investigación** (404 allá) se quitaron, también del menú móvil. Pie, igual al de activosespeciales.colliers.cl: «Contacto:» con el correo de Configuración → Sitio (allá muestran un correo personal; decisión de Jonas), LinkedIn, **Facebook** (nuevo), Instagram, YouTube, Política de privacidad y Términos de Uso. Se quitaron Nosotros (no está en su pie; sigue en la cabecera), Artículos, Carreras, Accionistas, X, Cookies y Accesibilidad. «Bases y condiciones» del pie de trámites (404, registro, mi cuenta, baja) **solo aparece si hay enlace en Configuración → Sitio** *(`SitioPublicoTest`; capturas antes y después con `tools/comparar/antes-despues.mjs`)*
- [x] **Ficha: horarios de visita solo si hay** *(Jonas, 22/10)*: sin horarios cargados el bloque «Horarios de visita» no aparece (antes decía «Sin horarios publicados»), en el remate próximo y en vivo. «Coordinar visita» salió del bloque y va junto a «Bases y condiciones», visible siempre mientras el remate no cierra (en uno cerrado no tiene sentido coordinar una visita) *(`SitioPublicoTest::test_horarios_de_visita_solo_si_hay_y_coordinar_visita_siempre_junto_a_las_bases`; capturas a 1440 y 375 px)*
- [x] Contacto «Coordinar visita» y «Contactar al ejecutivo» con el correo de Configuración
- [x] «Avísame» en el listado y en el detalle; foto y datos del próximo remate real en el Login y demás pantallas de acceso
- [x] **El espectador que espera en la página de un remate próximo lo ve empezar sin recargar** *(21/09)*: antes la cuenta llegaba a cero y la página se quedaba quieta. Ahora se recarga sola al comenzar, cada espectador con una espera al azar de hasta 15 s (cada recarga es una petición al servidor). Con el remate borrado con la página abierta: «NO DISPONIBLE» *(recorrido de los tres roles)*
- **Sitio público en inglés** *(pedido de Jonas, 20/10: solo el exterior; el panel, los correos y el modelo de datos no cambian)*
  - [x] **Mecanismo:** idioma por prefijo (`/en/…`, el español sin prefijo). Las rutas públicas y las de Fortify se registran dos veces (`routes/web.php`, nombres `en.x`) y `App\Support\UrlLocalizada` hace que `route('x')` dé la del idioma actual, así que vistas, redirecciones y Fortify no cambian. El middleware global `EstablecerIdioma` toma el idioma de la URL, también en los errores sin ruta. Conmutador EN / ES (el activo resaltado; en el menú móvil, «English»/«Español») por `/idioma/{es|en}?a=…`: misma página y consulta, guarda la cookie `idioma`, nunca lleva a otro sitio. La cookie solo actúa al entrar por la portada `/`. `lang` y `hreflang` (es, en, x-default) en el `<head>` del sitio público. Los correos salen siempre en español y con enlaces en español (`User::preferredLocale()`, `AvisoColliers::$locale`). La puja por `/en` conserva el reintento de conexión *(`IdiomaTest`, 9 pruebas)*
  - [x] **Cadenas migradas a `lang/es/`** sin cambiar el español: 886 textos en `sitio`, `listado`, `remate`, `sala`, `cuenta`, `acceso`, `errores` y `catalogo`. Los del JavaScript van por `t()` (`resources/js/modulos/textos.js`) desde un bloque JSON del layout. Lo que es dato (tipos, ocupación, estados, opciones del registro) se guarda igual y solo se traduce al mostrarlo. Verificado: foto del HTML de 322 páginas y respuestas antes y después (solo cambian el bloque JSON, los `value` de las opciones del registro y espacios), 196 pruebas, y los recorridos de los tres roles, la sala real, la cuenta, los filtros, el listado, el registro y el ingreso en navegador
  - [x] **Inglés escrito** (`lang/en/`, las 886 cadenas, con el glosario aprobado). Inglés neutro; errores y rechazos de puja cortos y directos. No se traducen regiones, comunas, direcciones ni lo que carga el administrador (título, descripción, ficha, condiciones de la garantía, datos bancarios, motivos), ni los asuntos de los `mailto` a Colliers *(Jonas, 20/10)*
  - [x] **Formatos por idioma** (`App\Support\Formato`, `formato.js`): en inglés, miles con coma (`$142,000,000`), fechas con el mes en letras («9 Sep 2026, 12:00»), 24 h y «(Santiago time)». El español no cambia; el panel y los correos siguen en español. Tipo de propiedad y ocupación se traducen al mostrarlos (el dato guardado no cambia). «Postor #N» se muestra como «Bidder #N» (el JSON público no cambia). En español, los rechazos dicen «(hora de Santiago)» en vez de «(hora de Chile)» *(Jonas, 20/10)*
  - [x] **Conmutador también en las cabeceras de ingreso, registro, mi cuenta, errores y la sala** *(Jonas, 20/10)*: la misma pieza del diseño (`x-publico.idioma`). El idioma que no está activo va en el gris del diseño y tiene área táctil de 44 px bajo 1120
  - [x] **Documentos «(in Spanish)»** en la ficha y en los enlaces a las bases. La línea «la versión en español es la que rige» es la cadena `remate.documentos.version_que_rige` (inglés; vacía en español): Jonas confirma el texto con el cliente
  - Verificado: 200 pruebas (`IdiomaTest`: montos, fechas, alias, documentos, conmutador, ninguna clave sin traducir). La foto del HTML en español solo cambia en el conmutador y en «hora de Santiago». La foto en inglés no tiene claves sin traducir ni montos con formato chileno. `tools/comparar/ingles.mjs` a 375, 768 y 1440 px: sin desborde, táctiles ≥ 44, conmutador de ida y vuelta, ingreso por `/en` y sala. Recorrido de los tres roles en español OK. La sala a 1440 sigue 1:1 (0,05 %); ingreso y listado sin cambio frente al commit anterior al idioma (la diferencia con el prototipo es la de siempre: fotos y datos)
  - Corregido de paso: el nombre del postor en la cabecera de la sala medía 22 px de alto en tablet (ya pasaba en español)
  - [x] **Condiciones del remate: el párrafo de la garantía en inglés** *(QA de Jonas 21/10, 3.2)*: salía de Configuración, solo en español. Mientras el texto sea el de fábrica, sale de `remate.secciones.condicion_garantia` en cada idioma; si Colliers lo edita en el panel, se muestra tal cual en los dos (regla del 20/10: el contenido del panel va en español). La ayuda del campo en el panel lo explica *(`IdiomaTest`; `qa-visibles.mjs`: los tres párrafos en inglés)*
  - [ ] Lista de términos del negocio revisada por Jonas (glosario base aprobado el 20/10)

## L — Streaming · Pendiente

- [ ] Iframe de YouTube embebido por remate
- [ ] Identificador del video cargado desde el panel
- [ ] Aviso de retraso del video (10–30 s) visible en pantalla
- [x] **Estado sin transmisión** *(QA de Jonas 21/10, 3.1: sin video, la sala era un recuadro negro)*: sin video cargado, el reproductor dice «La transmisión aún no comienza», que se puede pujar igual con el precio y el cronómetro oficiales del panel, que el video aparecerá ahí sin recargar, y enlaza al canal de Colliers (el de Configuración o, si está vacío, el del pie). Un video cargado que todavía no empieza o ya no está disponible lo informa el propio reproductor de YouTube. **El video viaja en el estado público** (`video` en el JSON, solo un id válido; +20 bytes): cargarlo, cambiarlo o quitarlo en el panel llega a la sala abierta en ~1 s, sin recargar; el iframe se crea una vez por video (no se recarga con cada sondeo) y al quitarlo vuelve el aviso *(Jonas, 21/10)* *(`SalaTest`; `qa-visibles.mjs` a 1440 y 375 px: sin desborde y el enlace de 44 px dentro del reproductor; video puesto, cambiado y quitado con la sala abierta, una sola carga del reproductor, sin recargar la página, sin bloqueos de la CSP, JSON de 916 bytes)*

## E/F — Componentes y estructura del panel · En progreso

Se completan a medida que las pantallas lo pidan.

- [x] **Usuarios internos: «06 Usuarios»** *(QA del 20/09, punto 2)*. Pantalla nueva, sin diseño, armada con los
  componentes del panel y **solo para administradores**. Crea administradores y martilleros con **clave temporal de un
  solo uso** (misma regla que `colliers:crear-usuario`, que se conserva para el primer arranque del servidor), edita
  nombre, correo y rol, **activa y desactiva** (nunca borra: quedan remates y bitácora a su nombre; al desactivar se
  cierran sus sesiones), restablece la clave, desbloquea tras intentos fallidos y cierra sesiones. Reglas: nadie se
  desactiva ni se cambia el rol a sí mismo, y siempre queda un administrador activo. Muestra el rol, el estado, los
  remates a cargo y el último ingreso. Los postores siguen en «03 Postores», con sus datos personales
  *(`UsuariosInternosTest`, 7 pruebas; `tools/comparar/panel-usuarios.mjs`, 14 comprobaciones en navegador real
  incluidas 375/760/1120/1440 px)*
- [ ] Componentes Blade comunes extraídos de las pantallas de T
- [x] Estructura del panel (navegación, permisos por rol): **permisos en un solo lugar**, `App\Autenticacion\Permisos` (22/10). Las rutas del panel piden `permiso:x` (middleware `Permiso`), el menú lateral y los botones preguntan lo mismo; Configuración tiene permiso por sección. Administración y martillero quedan exactamente como estaban *(`RolEditorTest::test_administracion_y_martillero_no_cambian`; `recorrido-roles.mjs` 57/57)*
- [x] **Rol Editor** *(Jonas, 22/10; ⚠️ **permisos a confirmar con el cliente**: se ajustan agregando o quitando `editor` en `Permisos::MAPA`, sin tocar rutas ni vistas)*. **Puede:** crear y editar remates y lotes, fotos, documentos y horarios de visita, «Crear remate nuevo», publicar y **despublicar** (nuevo: un remate próximo sin inscritos vuelve a borrador y sale del sitio; con inscritos no, porque hay garantías en curso, y la ficha dice que lo cancela administración; publicar de nuevo no repite el aviso «remate nuevo» a los suscriptores), y Configuración → **Sitio** (UF, contacto, enlaces y textos). **No puede:** Dashboard (entra directo a Subastas: el resumen trae datos de postores), Postores, cuentas y garantías, Reportes, panel en vivo y sala del martillero, cancelar o «Cerrar ahora», subastas de prueba (crean postores con clave), Usuarios ni el resto de Configuración. Menú y botones lo esconden; las rutas responden 403. Se crea desde «06 Usuarios» (o `colliers:crear-usuario editor`); el seeder trae `editor@colliers.test`. Sin 2FA por ahora (es solo para Administración, ver P) *(`RolEditorTest` 6 pruebas, 24 rutas prohibidas una por una; `tools/comparar/rol-editor.mjs` 17/17 en navegador real, incluido el menú a 375 px)*
- [ ] Tablas y formularios reutilizables, adaptados a móvil

## O — Reportes · En progreso

Verificado el 17/09: `ReportesTest` (5; 122/122 en total, remates generados con el motor de pujas y el liquidador reales) y
`tools/comparar/reportes.mjs` en navegador real (13/13): KPIs y tabla con los remates cerrados del seeder, cambio de
período, descargas XLSX/CSV y usabilidad a 375/760/1120/1440 px. Excluye remates de demostración y cancelados. Sin datos
personales: los postores van como «Postor #N» (administradores y martilleros pueden verlos). Unidad del reporte: el lote
terminado; con un lote por remate, cada fila es el remate del diseño *(supuesto, ver Decisiones)*.

- [x] Desempeño comercial: precio base vs. final, % de sobreprecio, tasa de venta (por remate/lote y KPIs del período)
- [x] Participación: postores registrados (cuentas aprobadas), con garantía aprobada, activos, adjudicatarios, garantías rechazadas; pujas por remate
- [x] Dinámica: minutos entre la primera y la última puja por remate (gráfico con los 12 más recientes y columna «Cierre»)
- [x] Generales: volumen total adjudicado y desempeño por categoría de activo (volumen, sobreprecio, tasa)
- [x] Exportación con `maatwebsite/excel` 4.0: CSV (punto y coma + BOM, abre bien en Excel de Chile) y XLSX; libro completo con 6 hojas y un XLSX por exportable. El paquete **no se autodescubre**: se registra solo al exportar, para no sumar archivos a cada petición sin OPcache
- [x] Comparación entre eventos: tabla por remate del período elegido; períodos por mes, trimestre, año o todo el historial *(supuesto: el diseño no tiene una pantalla de comparación lado a lado)*
- [x] **Promedio de pujas con un decimal** *(QA de Jonas 21/10, 4.3: «Promedio de 0 por lote» con pujas en el período)*: «Promedio de 1,7 por remate»; si queda bajo 0,05, «Menos de 0,1 por lote» *(`ReportesTest`; `qa-visibles.mjs`)*
- [x] **Exportar PDF: fuera del alcance** *(decisión de Jonas, 20/09: no está en el acta; si el cliente lo pide, se agrega)*. Se quitó el botón «Descargar PDF», que se veía y no hacía nada. Quedan CSV y XLSX
- [ ] Probar las descargas en el sandbox (extensiones `zip`, `xmlwriter`, `iconv`, `simplexml`, `xmlreader`, `zlib`: `colliers:diagnostico` ahora las revisa)

## P — Seguridad · En progreso

- [ ] Revisión de policies y consultas filtradas por dueño
- [ ] Rate limiting en login, registro y pujas
- [ ] RUT cifrado con índice ciego verificado
- [x] **Cabeceras de seguridad y CSP** *(QA de Jonas 21/10, 2.2: no había ninguna)*, en la aplicación y no en LiteSpeed (`CabecerasSeguridad`, global: páginas, errores, JSON y descargas): `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin` (YouTube necesita el origen), `Permissions-Policy` sin cámara, micrófono, ubicación ni pagos (sin tocar autoplay ni pantalla completa del video), y CSP: solo el propio dominio, `frame-src` de YouTube (`youtube-nocookie.com`, el que usan sala y ficha, y `youtube.com`), mosaicos de OpenStreetMap, Turnstile solo si está configurado. `'unsafe-eval'` porque Alpine evalúa las expresiones de las vistas; `'unsafe-inline'` solo en estilos (el diseño trae estilos en línea); **sin scripts en línea**: los 6 `onsubmit`/`onchange` del panel (los `confirm()` de borrar) se pasaron a Alpine, porque con la CSP el navegador los habría ignorado y el formulario se habría enviado **sin confirmar**. `report-uri /csp-informe`: lo bloqueado queda en el log de Laravel («CSP bloqueó un recurso»). `COLLIERS_CSP=reporte|apagada` la afloja desde el .env sin deploy *(`SeguridadTest`; `tools/comparar/seguridad.mjs` en Chrome: video de YouTube en ficha y sala, mapa, latido de presencia, estado de la sala, Alpine en listado y registro, los confirm del panel, con un control que confirma que un script en línea sí se bloquea y se detecta; los 16 recorridos en navegador corridos con la CSP activa y 0 bloqueos en el log)*. Los archivos estáticos (JSON del tiempo real, `hora.php`, `presencia.php`) no pasan por Laravel y no llevan estas cabeceras
- [x] **HTTPS forzado por la aplicación** *(QA 21/10, 2.1: http:// respondía 200)*: `ForzarHttps` redirige toda petición http a la misma dirección en https (301 GET/HEAD; 308 el resto, que conserva método y cuerpo), antes de abrir la base o la sesión; `Strict-Transport-Security: max-age=31536000` (sin `includeSubDomains` ni `preload`), y todos los enlaces generados en https. Activo por defecto fuera de local y pruebas (`COLLIERS_FORZAR_HTTPS`). `X-Forwarded-Proto: https` cuenta como https, para no entrar en bucle si el hosting termina el TLS en un proxy. La redirección del servidor la configura Jonas *(`SeguridadTest`)*
- [x] **Captcha en el registro: Google reCAPTCHA v2** («No soy un robot»; *Jonas, 22/10: reemplaza a Turnstile, que se quitó entero*). `App\Support\Recaptcha`: se verifica en el servidor (`siteverify` de Google) antes de validar el resto del formulario; si falla, no se valida nada más ni se guardan adjuntos, y si Google no responde el registro se rechaza (falla cerrado) y queda en el log. Sin `RECAPTCHA_SITE_KEY` y `RECAPTCHA_SECRET_KEY` no se muestra ni se exige (local y pruebas). Idioma del widget según la página (`hl=es`/`hl=en`). **CSP**, solo con claves: `script-src` www.google.com/recaptcha/ y www.gstatic.com/recaptcha/, `frame-src` www.google.com/recaptcha/ y recaptcha.google.com/recaptcha/, y `connect-src` www.google.com/recaptcha/ (el script hace un fetch a `api2/clr` desde la página: lo detectó `seguridad.mjs`). Excepción a «nada de CDN», como lo era Turnstile *(`AutenticacionTest`: sin claves, sin token, token rechazado, Google caído con su entrada en el log y token válido; `SeguridadTest`; en Chrome con las claves de prueba de Google: `seguridad.mjs` 22/22 con la casilla cargada, marcada y el token en el formulario, 0 bloqueos y 0 «CSP bloqueó» en el log; `registro-acceso.mjs` 33/33 con y sin claves; `composer test:mysql` 232/232)*
- [ ] Claves de reCAPTCHA cargadas en el sandbox y registro probado con la casilla *(Jonas: `RECAPTCHA_SITE_KEY` y `RECAPTCHA_SECRET_KEY` en el `.env`; dominio del sandbox registrado en la consola de reCAPTCHA)*
- [ ] HTTPS y cabeceras comprobados en el sandbox: `http://` → 301 a https, HSTS y CSP en las respuestas, sala con video y presencia sin «CSP bloqueó» en el log *(Jonas)*
- [ ] Carga de archivos (comprobantes, documentos) validada y fuera de `public`
- [ ] Sin secretos en el repositorio público
- [x] **Segundo factor por correo para Administración** *(Jonas, 22/10)*. En `/admin/ingresar`, usuario y clave correctos **no abren sesión**: llega un código de 6 dígitos al correo de la cuenta y se pide en «Revisa tu correo» (diseño del Login). Sin el código correcto no hay sesión. El código se guarda como hash, vence en 10 min, sirve una vez y admite 5 intentos; reenvío con 1 minuto de espera y tope de 5 códigos por hora y cuenta (también al volver a escribir la clave), para que no sirva para inundar un buzón. **Recordar el dispositivo** 7 días (casilla; cookie con token al azar, en la base su hash), por dispositivo y **revocable en «Sesiones activas»** (botón OLVIDAR y «Olvidar todos»); también se olvidan al cambiar o restablecer la contraseña, al desactivar la cuenta o al cerrar sus sesiones desde «06 Usuarios». Si el correo falla, la pantalla lo dice («No pudimos enviar el código…»), queda «Fallida» en Registro de correos con el error y en `access_logs`, y no deja entrar; con el correo en modo «registro» fuera de local tampoco se da por enviado. Plantilla «Código de ingreso al panel» editable en Plantillas de correo (el asunto no lleva el código; si alguien quita `{{ codigo }}` del texto, el código va igual). Valores en Configuración → Seguridad (vigencia, intentos, códigos por hora, días de recordar). **A quién se exige: `Permisos::MAPA['dos_pasos']`** (hoy solo `admin`; extenderlo a martillero o editor es agregar el rol ahí). Salida de emergencia si el correo del servidor no funciona: `php artisan colliers:codigo-ingreso correo` (requiere la terminal). Enlace nuevo «Sesiones y contraseña» en el pie del menú lateral del panel (antes no había cómo llegar a esas pantallas sin escribir la dirección) *(`SegundoFactorTest` 12 pruebas; `tools/comparar/dos-pasos.mjs` 18/18 en navegador real, también a 375 px; los recorridos que entran como administración leen el código del correo del log local: `recorrido-roles.mjs` 58/58, `registro-acceso.mjs` 33/33, `ingreso-servidor.mjs` 7/7)*
- [ ] Segundo factor en el sandbox *(Jonas)*: **antes del push**, Configuración → Correo → enviar un correo de prueba y confirmar que llega; después del deploy, ingresar con la clave, recibir el código y entrar; probar «recordar dispositivo» y olvidarlo en «Sesiones activas»
- [ ] Revisión de registros de acceso

## Q — QA y carga · Pendiente

- [ ] Carga: 10 postores concurrentes pujando
- [ ] Carga: espectadores públicos sin tope sobre el JSON estático
- [ ] Remate completo de punta a punta en el sandbox
- [ ] Cierre con varios lotes
- [ ] Arnés visual completo sobre datos reales
- [ ] Pruebas finales de Jonas (sin agentes de QA)

## R — Despliegue a producción · Pendiente

- [ ] Dominio definitivo (subdominio de Colliers) y HTTPS
- [ ] `.env` de producción: `APP_DEBUG=false`, `APP_ENV=production` (así el sitio se indexa: fuera de producción lleva noindex)
- [ ] Handler PHP 8.4 activo
- [ ] Respaldo de `APP_KEY` y `.env` fuera del servidor
- [ ] Respaldo periódico de la base
- [ ] Bloqueo de deploy con remate en curso activo en el script
- [ ] Verificación con `colliers:diagnostico`
- [ ] ⚠️ **Riesgo: la base compartida del hosting deja de aceptar conexiones** *(sandbox, 21/09: `1040 Too many connections` a las 16:14 UTC y `2002 No such file or directory` —MariaDB caída o reiniciando— a las 16:24 UTC, ambos en el cron)*. Si pasa durante un remate: **ninguna puja queda a medias** (la transacción es todo o nada) pero **ninguna puja entra** mientras dure; el postor ve «No pudimos confirmar tu puja», el precio del JSON queda congelado, el cronómetro sigue (`hora.php` no usa la base) y **el lote cierra igual en `cierra_en`** (no hay anti-sniping ni se reabre). Hay que: (1) medir `max_connections`, `max_user_connections` y el reinicio en el sandbox y pedir al hosting de producción el límite por cuenta; (2) definir con Colliers qué dicen las bases si la plataforma falla durante un remate; (3) bajar «espectadores que avisan» del 100 % (cada aviso abre una conexión en el mismo segundo del cierre). Análisis completo en `docs/progreso/2026-09-21.md`. **Mitigado en parte (21/09):** la puja reintenta la conexión hasta 1,5 s (ver J); una caída larga sigue siendo este riesgo
- [ ] ⚠️ **Riesgo: verificar OPcache en el hosting de producción antes de comprometer rendimiento.** Sin OPcache la validez de las pujas se mantiene, pero 10 pujas en el mismo segundo tardan ~4 s en confirmarse con 1 núcleo. Medir con `tools/sandbox/medir-servidor.php` y fijar el margen de liquidación según la latencia (`docs/RENDIMIENTO-SIN-OPCACHE.md` §7)

## S — Documentación · Pendiente

- [ ] Manual del administrador y del martillero
- [ ] Guía del postor
- [ ] Documentación técnica (arquitectura, motor de pujas, tiempo real)
- [ ] Procedimiento de operación del servidor (deploy, bloqueo, respaldo, diagnóstico)

---

## Decisiones pendientes

### Tomadas por Jonas

- 16/09: las pantallas de autenticación que el diseño no tiene reutilizan el diseño del Login.
- 16/09: bloqueo por intentos fallidos de 15 minutos por defecto (5 intentos, según el diseño); editable en V.
- 16/09: el JSON público de estado de remates no pasa por la clave del sandbox (las subastas son públicas).
- 21/09: **el sandbox va con acceso libre**: se quitó la clave de acceso (middleware, pantalla `/acceso`, variable y pruebas). Se mantiene el noindex.
- 16/09: la concurrencia contra MariaDB se prueba en el sandbox sobre el remate de demostración.
- 20/09: el **martillero** ve solo los remates a su cargo (listado y panel en vivo) y la ficha de esos, en modo lectura.
- 20/09: los **usuarios internos** van en una pantalla propia, **«06 Usuarios»** del menú lateral, solo para administradores.
- 20/09: el modo «**postor habilitado una vez para todos los remates**» se construye **después** de que el cliente defina
  sobre qué monto se constituye esa garantía global. Implicaciones informadas: una sola garantía respaldando varios
  remates adjudicados, el monto sin base cuando no hay remate, las bases de cada remate como documento legal, y los
  efectos en «Postor #N», en el cierre de garantías y en los reportes. Mientras tanto se unifica la pregunta «¿puede
  pujar?» en una sola clase (hoy repetida en 8 lugares) para que el interruptor sea un cambio de una línea.

### De Jonas

- [x] Sala con varios lotes: el aviso mínimo («El lote 1 se adjudicó en $X. Ahora se remata el lote 2.») y «LOTE N DE M» **quedan como están** *(Jonas, 20/09)*. El 21/09 pidió que el cambio de lote sea evidente: el aviso se destacó y dice el resultado para cada postor (ver K)
- [ ] **«Cerrar el remate completo»** (hoy el panel cierra el lote que corre y los siguientes abren a su hora fija): **lo decide Jonas después; no implementar todavía** *(21/09)*
- [ ] Mensaje del martillero en la sala: se muestra en una franja amarilla dentro del panel de puja (escritorio) y sobre el video (móvil). ¿Sirve? (17/09)
- [ ] Formulario «Crear subasta»: se agregó el campo REGIÓN (lo necesitan los filtros del sitio) y la garantía pasó de monto a porcentaje (acta). Ficha del remate, formulario de lote y panel del martillero no tienen diseño: se armaron con los componentes del panel (17/09)

- [x] Nombre exacto del handler PHP 8.4 en cPanel/LiteSpeed: `application/x-httpd-ea-php84` (16/09)
- [ ] Subir el acta del 25/08 al material local de referencia
- [x] Idioma EN/ES (Bloque N): **se hace solo para el sitio público** *(Jonas, 20/10)*. Prefijo `/en`, cookie con la última elección, correos siempre en español, sin campos en inglés en el panel, documentos marcados «(in Spanish)». Avance en N
- [ ] Registro de extranjeros sin RUT: el registro exige RUT chileno, región y comuna; en inglés el formulario se entiende, pero un extranjero sin RUT no puede terminarlo *(planteado el 20/10, sin respuesta)*
- [x] Exportar a PDF (Bloque O): **se quita** *(Jonas, 20/09: no está en el acta; es alcance extra)*
- [ ] Reportes (17/09): CSV y XLSX de arriba descargan el desempeño comercial en CSV y el libro completo en XLSX; cada «Descargar» de exportables baja esa hoja en XLSX. ¿Sirve así?
- [ ] Activar el filtro «Garantía requerida» (hoy oculto; pasa a ajuste del panel en V)
- [ ] Imagen para compartir del listado y de las páginas sin foto *(QA 21/10, 3.4)*: Jonas la pidió a Colliers (1200 × 630). **Preparado:** basta reemplazar `public/img/compartir.png` (o subir `compartir.jpg`, que tiene prioridad) y hacer push; las medidas y la tarjeta grande salen del archivo. Hoy es una copia del logo
- [x] Límite de pujas: **queda en 30 por minuto por usuario**, contando también los rechazados *(Jonas, 21/10)*
- [x] Condición de garantía editada en el panel: se muestra tal cual (en español) también en `/en`. **Sin cambios: es lo acordado** el 20/10 *(Jonas, 21/10)*

### Supuestos vigentes (16/09, mientras el cliente no defina; cambiar cualquiera no borra datos)

| Decisión abierta | Supuesto | Cómo se cambia |
|---|---|---|
| Garantía con varios lotes | Por remate; base = suma de precios base de los lotes | `garantias.lote_id` ya existe (nulo): solo lógica |
| Secuencia de lotes | Horario fijo calculado al publicar; duración por remate con valor opcional por lote | Encadenar = recalcular `abre_en`/`cierra_en` al cierre: solo lógica |
| Cierre anticipado | Adjudica la mejor puja (modal del diseño); sin pujas, desierto | Estados como texto: «anulado» es un valor más |
| Persona jurídica | Una empresa = una cuenta, de la persona que actúa por ella | Regla validada en la aplicación, no en la base |
| Unicidad del RUT de la persona | Una persona = una cuenta (índice único en `postores.rut_indice`) | Quitar el índice único: no borra datos, pero no es aditivo |
| Identidad en el feed público | «Postor #N» (orden de inscripción de la garantía), como el diseño | Solo `EstadoRemate::alias()` |
| Mecánica del cierre anticipado | Fija `cierra_en` en ese momento y adjudica por el mismo camino que el cierre por tiempo, pasado el margen | Solo `Liquidador` |
| Edición de un remate publicado (17/09) | Horario, precios, incremento y % de garantía se editan solo antes de que abra el primer lote; título, descripción, martillero y video, siempre | Solo `Remate::condicionesEditables()` |
| Cancelar (17/09) | Borrador o publicado que no ha comenzado, con motivo. Uno en vivo se cierra lote por lote (cierre anticipado). «Cerrar ahora» del listado en un remate próximo = cancelar | Solo `GestionRemates` |
| Garantía en el formulario de creación (17/09) | El diseño pide un monto; manda el acta: se ingresa el **porcentaje** (vacío = global) y el resumen muestra el monto que resulta | Solo la vista |
| Revisión de postores (17/09) | Aprobar/Rechazar del listado actúan sobre la cuenta mientras no esté aprobada y después sobre la garantía; una cuenta bloqueada conserva sus garantías pero no puja ni se inscribe | Solo `RevisionPostores` y la vista |
| Inscripción (17/09) | Requiere cuenta aprobada y remate próximo antes del cierre de garantías; no hay tope de inscripciones por postor | Solo `InscripcionGarantias` |
| Unidad de los reportes (17/09) | Una fila por **lote terminado** (adjudicado o desierto), contado en el período por la hora de cierre del lote en hora de Chile; con un lote por remate es el remate. Tasa de venta = lotes adjudicados / lotes terminados; sobreprecio medio = promedio simple de (final / base − 1) de los adjudicados | Solo `App\Reportes\ReporteRemates` |
| Indicadores de participación (17/09) | Registrados = cuentas aprobadas (o bloqueadas después) hasta el fin del período; con garantía, activos, adjudicatarios y garantías rechazadas = en los remates del período (estado actual de la garantía). «Devueltas» no existe todavía en el modelo | Solo `ReporteRemates` |
| Dinámica de cierre (17/09) | Minutos entre la primera y la última puja del lote, como dice el diseño | Solo `ReporteRemates` |
| Acceso a reportes (17/09) | Administradores y martilleros (no hay datos personales) | Rutas |
| Quién gestiona remates (17/09) | Crear y editar: solo administradores. Martilleros: ven el listado y el panel en vivo de SUS remates | Rutas `rol:admin` |

### Del cliente (no bloquean; se anota y se sigue)

- [ ] Campos exactos del registro de postor (hoy: ~20 del diseño, estructura extensible)
- [ ] Máquina de estados única (propuesta enviada el 15/09, ver `CLAUDE.md` §7)
- [ ] Cierre anticipado: ¿adjudica la mejor puja o anula el lote?
- [ ] Varios lotes: ¿garantía por remate o por lote, y sobre qué base el 10 %?
- [ ] Varios lotes: ¿el siguiente abre al cierre del anterior + pausa, u horario fijo?
- [ ] ¿Se enmascara la identidad de los postores en el feed? (el diseño usa «Postor #N»)
- [ ] Garantía del perdedor: ¿devolución? (el diseño lo afirma; el acta no lo define)
- [ ] Habilitación única: el modo ya está construido y apagado. Si Colliers lo quiere, debe fijar **el monto** en Configuración → Garantías y decidir qué pasa si un postor gana varios remates respaldado por una sola garantía
- [x] 2FA: **solo Administración por ahora** *(Jonas, 22/10)*, preparado para extenderlo por rol (`Permisos::MAPA['dos_pasos']`)
- [ ] **Permisos del rol Editor** (propuesta de Jonas del 22/10, construida así; Jonas la confirma con el cliente). Cambiarla es editar `App\Autenticacion\Permisos::MAPA`
- [ ] Lista exacta de gráficos y columnas de los reportes
