# BACKLOG — Remates Colliers · Fase 3

> **Registro vivo del avance.** Última actualización: **2026-09-17**.
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
| B | Base del proyecto Laravel | En progreso | 18/19 |
| C | Modelo de datos | **Completo** | 12/12 |
| J | Motor de subastas en tiempo real ⚠️ | En progreso | 30/34 |
| D | Autenticación y registro de postores | En progreso | 10/12 |
| K | Sala de puja conectada al motor real | En progreso | 14/15 |
| I | Remates y lotes + panel del martillero | Pendiente | 0/8 |
| V | Configuración autoadministrable y SMTP | Pendiente | 0/10 |
| G | Postores | Pendiente | 0/5 |
| H | Garantías | Pendiente | 0/7 |
| M | Notificaciones | Pendiente | 0/7 |
| N | Sitio público | Pendiente | 0/9 |
| L | Streaming | Pendiente | 0/4 |
| E/F | Componentes y estructura del panel | Pendiente | 0/3 |
| O | Reportes | Pendiente | 0/7 |
| P | Seguridad | Pendiente | 0/8 |
| Q | QA y carga | Pendiente | 0/6 |
| R | Despliegue a producción | Pendiente | 0/8 |
| S | Documentación | Pendiente | 0/4 |

## Orden de ejecución acordado

```
T → B → C → J(núcleo) → D → K → I → V → G → H → M → N → L → E/F → O → P → Q → R → S
```

Primero lo visible para mostrarlo al cliente; después lo riesgoso (J) lo antes posible.
A está fuera de la secuencia: lo hizo Jonas antes de empezar.

**Dónde vamos:** T y C cerrados. B completo salvo `maatwebsite/excel` (va en O). J, D y K hechos y probados en local
(K en navegador real contra el motor); faltan sus verificaciones en el sandbox. 17/09: OPcache apagado en el sandbox →
camino de la puja optimizado por código (`docs/RENDIMIENTO-SIN-OPCACHE.md`). Plan del 17/09 (Jonas): seguir de corrido
**K → I → V → G → H → M → N**, commits locales, push al terminar N.

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
- [x] Clave de acceso al sandbox (middleware, formulario, límite de intentos, noindex)
- [x] Páginas de error propias: 403, 404, 419, 429, 500, 503
- [x] Traducciones al español en `lang/es/`
- [x] `.env.example` completo y sin secretos
- [x] `users` con rol, estado y cambio de clave obligatorio (migración aditiva)
- [x] `php artisan test` 13/13 (16/09)
- [x] Push a `main` con aprobación de Jonas (16/09, `ce52a89`; segundo push `4bd54c0` con C, hook y handler)
- [x] Conectar el servidor: clone, `.env`, `APP_KEY` generada y respaldada, migrate, `colliers:instalar`, administrador creado *(Jonas, 16/09)*
- [x] Script de deploy con `colliers:puede-desplegar` y `colliers:instalar` *(Jonas, 16/09)*
- [x] Cron de `schedule:run` cada minuto y cron de deploy cada 5 minutos activos *(Jonas, 16/09)*
- [x] Handler PHP 8.4 aplicado en el servidor: `application/x-httpd-ea-php84` *(Jonas, 16/09)*
- [x] Sandbox responde en https y pide la clave de acceso *(Jonas, 16/09)*
- [x] `colliers:diagnostico` sin errores ni avisos, latido del cron a 38 s *(Jonas en el sandbox, 16/09)*
- [x] Handler versionado en `public/.htaccess`: sitio carga tras el deploy de `4bd54c0` y `git status` limpio *(Jonas en el sandbox, 16/09)*
- [x] Hook pre-commit (`.githooks/pre-commit`) que verifica que `public/build` corresponde a los assets del commit (5 casos probados, 16/09)
- [x] Fortify en español (Bloque D, 16/09)
- [ ] `maatwebsite/excel` para exportaciones *(se hace en O)*

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

**Temporizador y cierre**
- [x] Endpoint de sincronización de reloj (`GET /hora`) *(el cronómetro del navegador es K)*
- [x] Cierre perezoso idempotente + margen de liquidación configurable; sin anti-sniping (una puja nunca toca `cierra_en`)
- [x] Adjudicación automática; lote desierto; detectores: puja rechazada por cierre, endpoint de estado y `colliers:liquidar` cada minuto
- [x] Paso al siguiente lote: horario fijo, cada lote abre a su `abre_en`; el remate se finaliza al liquidar el último
- [x] Cierre manual de emergencia: endpoint para administrador o el martillero del remate *(la pantalla es del Bloque I)*

**Difusión en tiempo real**
- [x] Interfaz de emisión abstraída (`App\Subastas\Difusion\Emisor`; implementación JSON estático; Pusher = otra clase)
- [x] Escritura atómica (temporal + rename) bajo bloqueo de archivo por remate; el archivo nunca retrocede (probado con 300 pujas concurrentes)
- [x] Cabeceras sin caché y sin ETag; bloqueos y temporales no descargables (403) *(verificado en Apache local)*
- [x] Cabeceras sin caché servidas por **LiteSpeed**: HTTP/2 200, `no-store, no-cache, must-revalidate, max-age=0`, sin ETag, 403 en `.htaccess` *(Jonas en el sandbox, 17/09)*
- [x] Eventos: puja nueva, cierre de lote (la apertura del siguiente va por horario en el mismo estado), mensaje del martillero
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
- [ ] Concurrencia contra **MariaDB real** del sandbox, sobre el remate de demostración (decisión de Jonas, 16/09; el login HTTP ya existe)
- [x] Comando `colliers:remate-demo`: remate marcado `es_demostracion`, postores aprobados con garantía y claves aleatorias mostradas una vez; se niega con APP_ENV=production (16/09)
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
- [ ] En el sandbox: el primer administrador entra por `/admin/ingresar` y cambia su clave temporal *(Jonas en el sandbox)*
- [ ] Correos reales de verificación y recuperación: hoy `MAIL_MAILER=log` (el enlace queda en `storage/logs`); SMTP en el Bloque V

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
- [ ] Probar la sala en el sandbox (LiteSpeed + MariaDB) sobre el remate de demostración *(Jonas)*

## I — Remates y lotes, incluido el panel del martillero · Pendiente

- [ ] CRUD de remates (datos, fecha, estado, identificador de YouTube)
- [ ] CRUD de lotes (activo, precio base, orden, `cierra_en`)
- [ ] Imágenes, documentos descargables y visitas por lote
- [ ] Incremento propio por remate (opcional; si no, el global)
- [ ] Publicar / cancelar según la máquina de estados *(pendiente de aprobación del cliente)*
- [ ] Panel del martillero: seguimiento en vivo y cierre anticipado
- [ ] Un remate que no se concreta no se reabre: se crea uno nuevo
- [ ] Usable desde el celular

## V — Configuración autoadministrable y SMTP · Pendiente

- [ ] Incremento mínimo (global y por remate), CLP 100.000 por defecto
- [ ] Porcentaje de garantía, 10 % por defecto
- [ ] Margen de liquidación, 2 s por defecto
- [ ] Intentos de login y duración del bloqueo
- [ ] Plazos: cierre de garantías antes del remate, tiempo de revisión
- [ ] Datos bancarios para garantías
- [ ] Fuente y valor de la UF (solo referencia visual)
- [ ] Textos legales y enlaces
- [ ] SMTP completo con botón de prueba de envío
- [ ] Filtro «Garantía requerida» del listado activable *(hoy oculto)*

## G — Postores · Pendiente

- [ ] Listado y ficha de postores conectados a datos reales
- [ ] Revisión de cuenta: aprobar / rechazar
- [ ] Correo de rechazo cuando corresponde
- [ ] Bloquear / desbloquear cuenta *(pendiente de la máquina de estados)*
- [ ] Aprobación usable desde el celular

## H — Garantías · Pendiente

- [ ] Monto calculado como % del valor mínimo (configurable en V)
- [ ] Instrucciones de pago externo (vale a la vista o transferencia); **sin pasarela**
- [ ] Carga de comprobante por el postor
- [ ] Estados: pendiente, en revisión, aprobada, rechazada
- [ ] Aprobación manual por un administrador, con registro de quién y cuándo
- [ ] Aprobar una garantía desde el celular con datos reales
- [ ] Destino posterior (devolución / imputación / ejecución) *(pendiente con el cliente)*

## M — Notificaciones · Pendiente

- [ ] Envío por la cola del cron (`--stop-when-empty`), registrado en `notificaciones_log`
- [ ] Cuenta aprobada / rechazada
- [ ] Garantía aprobada / rechazada
- [ ] Adjudicación: al adjudicatario y al administrador
- [ ] Suscripción «avísame»
- [ ] Recordatorio antes del remate
- [ ] Plantillas en español

## N — Sitio público · Pendiente

- [ ] Listado con datos reales y filtros desde los datos
- [ ] Detalle de remate próximo y en vivo con datos reales
- [ ] Calendario `.ics`
- [ ] Mapa (OpenStreetMap en gris)
- [ ] Documentos descargables
- [ ] Recomendados
- [ ] Enlace al canal de YouTube de Colliers
- [ ] Contacto «Coordinar visita»
- [ ] Idioma EN/ES *(pendiente de decisión de Jonas)*

## L — Streaming · Pendiente

- [ ] Iframe de YouTube embebido por remate
- [ ] Identificador del video cargado desde el panel
- [ ] Aviso de retraso del video (10–30 s) visible en pantalla
- [ ] Estado sin transmisión (antes de empezar / video no disponible)

## E/F — Componentes y estructura del panel · Pendiente

Se completan a medida que las pantallas lo pidan.

- [ ] Componentes Blade comunes extraídos de las pantallas de T
- [ ] Estructura del panel (navegación, permisos por rol)
- [ ] Tablas y formularios reutilizables, adaptados a móvil

## O — Reportes · Pendiente

- [ ] Desempeño comercial: precio base vs. final, % de sobreprecio, tasa de venta
- [ ] Participación: postores registrados, activos, pujas por remate
- [ ] Dinámica: tiempo de cierre por remate
- [ ] Generales: volumen total transado, desempeño por categoría de activo
- [ ] Exportación con `maatwebsite/excel`
- [ ] Comparación entre eventos
- [ ] Exportar PDF *(pendiente de decisión de Jonas)*

## P — Seguridad · Pendiente

- [ ] Revisión de policies y consultas filtradas por dueño
- [ ] Rate limiting en login, registro y pujas
- [ ] RUT cifrado con índice ciego verificado
- [ ] Cabeceras de seguridad en LiteSpeed
- [ ] Carga de archivos (comprobantes, documentos) validada y fuera de `public`
- [ ] Sin secretos en el repositorio público
- [ ] 2FA *(pendiente con el cliente: ¿solo administradores o también postores?)*
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
- [ ] `.env` de producción: `APP_DEBUG=false`, `COLLIERS_ACCESO_CLAVE` vacía
- [ ] Handler PHP 8.4 activo
- [ ] Respaldo de `APP_KEY` y `.env` fuera del servidor
- [ ] Respaldo periódico de la base
- [ ] Bloqueo de deploy con remate en curso activo en el script
- [ ] Verificación con `colliers:diagnostico`
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
- 16/09: la concurrencia contra MariaDB se prueba en el sandbox sobre el remate de demostración.

### De Jonas

- [ ] Sala con varios lotes: se implementó un aviso mínimo («El lote 1 se adjudicó en $X. Ahora se remata el lote 2.») y «LOTE N DE M» en la cabecera. ¿Sirve o se diseña una transición? (17/09)
- [ ] Mensaje del martillero en la sala: se muestra en una franja amarilla dentro del panel de puja (escritorio) y sobre el video (móvil). ¿Sirve? (17/09)

- [x] Nombre exacto del handler PHP 8.4 en cPanel/LiteSpeed: `application/x-httpd-ea-php84` (16/09)
- [ ] Subir el acta del 25/08 al material local de referencia
- [ ] Idioma EN/ES (Bloque N): hoy se ve y no funciona
- [ ] Exportar a PDF (Bloque O): hoy se ve y no funciona
- [ ] Activar el filtro «Garantía requerida» (hoy oculto; pasa a ajuste del panel en V)

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

### Del cliente (no bloquean; se anota y se sigue)

- [ ] Campos exactos del registro de postor (hoy: ~20 del diseño, estructura extensible)
- [ ] Máquina de estados única (propuesta enviada el 15/09, ver `CLAUDE.md` §7)
- [ ] Cierre anticipado: ¿adjudica la mejor puja o anula el lote?
- [ ] Varios lotes: ¿garantía por remate o por lote, y sobre qué base el 10 %?
- [ ] Varios lotes: ¿el siguiente abre al cierre del anterior + pausa, u horario fijo?
- [ ] ¿Se enmascara la identidad de los postores en el feed? (el diseño usa «Postor #N»)
- [ ] Garantía del perdedor: ¿devolución? (el diseño lo afirma; el acta no lo define)
- [ ] 2FA: ¿solo administradores o también postores?
- [ ] Lista exacta de gráficos y columnas de los reportes
