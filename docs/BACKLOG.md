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
| B | Base del proyecto Laravel | En progreso | 15/19 |
| C | Modelo de datos | Pendiente | 0/11 |
| J | Motor de subastas en tiempo real ⚠️ | Pendiente | 0/23 |
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

**Dónde vamos:** T cerrado y en GitHub. B casi cerrado: servidor conectado por Jonas (16/09); falta
`colliers:diagnostico` en el sandbox y verificar el handler versionado tras el próximo push. **En curso: C.**

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
- [x] Push a `main` con aprobación de Jonas (16/09, `ce52a89`)
- [x] Conectar el servidor: clone, `.env`, `APP_KEY` generada y respaldada, migrate, `colliers:instalar`, administrador creado *(Jonas, 16/09)*
- [x] Script de deploy con `colliers:puede-desplegar` y `colliers:instalar` *(Jonas, 16/09)*
- [x] Cron de `schedule:run` cada minuto y cron de deploy cada 5 minutos activos *(Jonas, 16/09)*
- [x] Handler PHP 8.4 aplicado en el servidor: `application/x-httpd-ea-php84` *(Jonas, 16/09)*
- [x] Sandbox responde en https y pide la clave de acceso *(Jonas, 16/09)*
- [ ] `colliers:diagnostico` sin fallas y latido OK en el sandbox *(verifica Jonas en el sandbox)*
- [ ] Handler versionado en `public/.htaccess` (el deploy revierte cambios manuales); verificar el sitio tras el push *(verifica Jonas en el sandbox)*
- [x] Hook pre-commit (`.githooks/pre-commit`) que verifica que `public/build` corresponde a los assets del commit (5 casos probados, 16/09)
- [ ] Fortify en español *(se hace en D)*
- [ ] `maatwebsite/excel` para exportaciones *(se hace en O)*

## C — Modelo de datos · Pendiente

- [ ] `users`: RUT cifrado + índice ciego, teléfono, estado de validación, datos extensibles (tablas propias del postor)
- [ ] `remates`: nombre, fecha, estado, identificador de streaming, incremento propio (opcional)
- [ ] `lotes`: remate, datos del activo, precio base, orden, estado, `cierra_en`, precio actual, ganador
- [ ] `lote_imagenes`, documentos, visitas
- [ ] `garantias`: postor, remate, monto, estado, comprobante, quién aprobó y cuándo
- [ ] `pujas`: lote, postor, monto, timestamp de servidor, IP, user agent (solo crece)
- [ ] `puja_intentos`: intentos rechazados con motivo
- [ ] `adjudicaciones`: lote, ganador, monto final, fecha de cierre
- [ ] `access_logs`, `configuraciones`, `notificaciones_log`
- [ ] Todas las fechas en UTC; todas las migraciones con `down()`
- [ ] Seeders de desarrollo con datos realistas (reemplazan `App\Demo\RematesDemo`)

## J — Motor de subastas en tiempo real ⚠️ · Pendiente

Leer `CLAUDE.md` §5 antes de empezar. **No se cierra sin pruebas de concurrencia automatizadas.**

**Validación de la puja**
- [ ] Endpoint con transacción y `lockForUpdate` sobre la fila del lote, reintentos ante deadlock
- [ ] Remate en curso, lote abierto, postor aprobado, garantía aprobada
- [ ] Monto ≥ actual + incremento; el postor no es quien va ganando
- [ ] Validez por hora de recepción en el servidor
- [ ] Rate limiting sobre el endpoint
- [ ] Intentos rechazados registrados fuera de la transacción

**Temporizador y cierre**
- [ ] Endpoint de sincronización de reloj
- [ ] Cierre perezoso idempotente + margen de liquidación configurable (sin anti-sniping)
- [ ] Adjudicación automática; lote desierto
- [ ] Paso al siguiente lote (cuando haya varios)
- [ ] Cierre manual de emergencia desde el panel del martillero

**Difusión en tiempo real**
- [ ] Interfaz de emisión abstraída (JSON estático; alternativa Pusher)
- [ ] Escritura atómica del estado por remate; cabeceras sin caché en LiteSpeed
- [ ] Eventos: puja nueva, cierre de lote y apertura del siguiente, mensaje del martillero
- [ ] Reconexión recuperando el estado actual

**QA obligatorio**
- [ ] Medición de límites del sandbox (EP, CPU, `max_execution_time`, cron, HTTP saliente)
- [ ] Dos pujas del mismo monto en el mismo instante: solo una gana
- [ ] Puja bajo el incremento / sin garantía / después del cierre: rechazadas
- [ ] Vaciar la caché a mitad del remate no altera el estado
- [ ] El ganador registrado coincide con la última puja válida
- [ ] Simulación de 20 postores en paralelo (Apache/Nginx de Laragon + MariaDB)
- [ ] Prueba del transporte en el sandbox con espectadores simulados
- [ ] Bloqueo de deploy con remate en curso (`colliers:puede-desplegar`)

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
