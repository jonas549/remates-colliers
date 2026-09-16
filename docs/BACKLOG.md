# BACKLOG — Remates Colliers · Fase 3

> **Registro vivo del avance.** Última actualización: **2026-09-16**.
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
| B | Base del proyecto Laravel | En progreso | 17/19 |
| C | Modelo de datos | **Completo** | 12/12 |
| J | Motor de subastas en tiempo real ⚠️ | En progreso | 23/27 |
| D | Autenticación y registro de postores | Pendiente | 0/7 |
| K | Sala de puja conectada al motor real | Pendiente | 0/9 |
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
| R | Despliegue a producción | Pendiente | 0/7 |
| S | Documentación | Pendiente | 0/4 |

## Orden de ejecución acordado

```
T → B → C → J(núcleo) → D → K → I → V → G → H → M → N → L → E/F → O → P → Q → R → S
```

Primero lo visible para mostrarlo al cliente; después lo riesgoso (J) lo antes posible.
A está fuera de la secuencia: lo hizo Jonas antes de empezar.

**Dónde vamos:** T y C cerrados y desplegados (`4bd54c0`, verificado en el sandbox el 16/09). B completo salvo
Fortify (va en D) y `maatwebsite/excel` (va en O). J núcleo hecho y probado en local con concurrencia real; faltan
las verificaciones del sandbox (LiteSpeed, límites, espectadores) y repetir con MariaDB. **Siguiente: D.**

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
- [ ] Fortify en español *(se hace en D)*
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
- [ ] Cabeceras sin caché servidas por **LiteSpeed** *(verifica Jonas en el sandbox)*
- [x] Eventos: puja nueva, cierre de lote (la apertura del siguiente va por horario en el mismo estado), mensaje del martillero
- [x] Estado público sin identidades: «Postor #N» por orden de garantía *(supuesto vigente)*
- [x] Reconexión recuperando el estado actual (`GET /remates/{remate}/estado`, que además liquida lo vencido) *(el cliente es K)*

**QA obligatorio**
- [ ] Medición de límites del sandbox (EP, CPU, `max_execution_time`, cron, HTTP saliente, **OPcache activo**) *(Jonas en el servidor)*
- [x] Dos pujas del mismo monto en el mismo instante: solo una gana (5 rondas × 20 simultáneas)
- [x] Puja bajo el incremento / sin garantía / después del cierre: rechazadas
- [x] Vaciar la caché a mitad del remate no altera el estado (`optimize:clear` en PHPUnit, `cache:clear` en concurrencia)
- [x] El ganador registrado coincide con la última puja válida (ráfagas y cierre disputado por 20 liquidaciones)
- [x] Simulación de 20 postores en paralelo (Apache de Laragon + **MySQL 8.4**)
- [ ] Repetir `tools/concurrencia` con **MariaDB** local (Laragon no la trae instalada)
- [ ] Prueba del transporte en el sandbox con espectadores simulados *(requiere un remate de prueba en el sandbox)*
- [x] Bloqueo de deploy con remate en curso o por comenzar (30 min antes; `colliers:puede-desplegar` sale 75)

## D — Autenticación y registro de postores · Pendiente

- [ ] Registro de postor; validación de RUT (formato y dígito verificador)
- [ ] Verificación de correo
- [ ] Login de postor y login de administrador, separados (Fortify, en español)
- [ ] Recuperación y cambio de contraseña (propia y de otros desde el admin, cerrando sesiones)
- [ ] Registro de accesos; bloqueo tras intentos fallidos con el contador en tabla, no en caché
- [ ] Sesiones activas y cierre remoto
- [ ] Middleware de rol y policies; ninguna consulta por id sin filtrar por dueño

## K — Sala de puja conectada al motor real · Pendiente

- [ ] Estado inicial desde el servidor (precio, ganador, `cierra_en`)
- [ ] Botones de puja rápida CLP 100k / 500k / 1M
- [ ] Campo «Otro» respetando el incremento mínimo
- [ ] Modal de confirmación obligatorio antes de registrar la puja
- [ ] Indicador «vas ganando» / «te superaron»
- [ ] Actualización en tiempo real (consulta ~1 s al JSON estático)
- [ ] Cronómetro sincronizado con el endpoint de reloj
- [ ] Aviso visible: el cronómetro y el precio de la plataforma son la fuente oficial, no el video
- [ ] Mensajes de rechazo en español para cada motivo

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

## S — Documentación · Pendiente

- [ ] Manual del administrador y del martillero
- [ ] Guía del postor
- [ ] Documentación técnica (arquitectura, motor de pujas, tiempo real)
- [ ] Procedimiento de operación del servidor (deploy, bloqueo, respaldo, diagnóstico)

---

## Decisiones pendientes

### De Jonas

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
