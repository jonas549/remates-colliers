# PROGRESO — Remates Colliers · Fase 3

> Punto de retome. Última actualización: **2026-10-23.**
> Leer junto con `CLAUDE.md` (reglas) y **`docs/BACKLOG.md` (estado vivo de cada tarea)**.
> El detalle de cada jornada está en `docs/progreso/AAAA-MM-DD.md`.

---

## 0. Para retomar en 30 segundos

- **Estado (23/10):** T, B, C, I, G y L completos. J, D, K, V, H, M, N, O, P y E/F hechos y probados en local; lo que
  falta de ellos se prueba en el sandbox (Jonas) o espera al cliente. Q, R y S sin empezar. Estado fino: `docs/BACKLOG.md`.
- **Todo subido a `main` el 23/10** (aprobado por Jonas), incluido este cierre: nada local sin push. El push anterior fue
  el 22/10 a las 19:54 UTC hasta `86ddaa6`.
- **237 pruebas PHPUnit**: SQLite 237 (235 + 2 saltadas, `composer test`) y MySQL 237/237 (`composer test:mysql`, con el
  MySQL 8.4 de Laragon levantado a mano). Navegador al 23/10: `recorrido-roles` 58/58, `registro-acceso` 33/33.
- 🛑 **OPcache es BLOQUEANTE para el remate en vivo** (22/10): sin OPcache se pierden pujas válidas en el cierre y ningún
  margen lo evita (`docs/RENDIMIENTO-SIN-OPCACHE.md` §9, BACKLOG R). Lo activa Jonas; después se mide en el sandbox. La
  «puja en vuelo» es una propuesta **condicionada** a que esa medición vuelva a perder pujas: no implementarla antes.
- **23/10:** documentos de estado al día, límite de envíos del registro (20/h por IP), L y parte de P verificados,
  temporales viejos de `bootstrap/cache` borrados. Detalle: `docs/progreso/2026-10-23.md`.
- **22/10:** cabecera y pie como el sitio corporativo, ficha sin bloque de visitas vacío, **rol Editor** (permisos a
  confirmar con el cliente, en `App\Autenticacion\Permisos`), **2FA por correo para Administración** (emergencia:
  `php artisan colliers:codigo-ingreso correo`), reCAPTCHA v2 en lugar de Turnstile; noche: 16 regiones en el registro y
  medición por tramos de la puja. Los recorridos que entran como administración leen el código del log local
  (`tools/comparar/segundo-factor.mjs`). Detalle: `docs/progreso/2026-10-22.md`.
- **Historial corto:** 21/10 QA de Jonas sobre el sandbox (18 hallazgos, `docs/progreso/2026-10-21.md`); 20/10 sitio
  público en inglés (textos en `lang/es` y `lang/en` con las mismas claves, `tools/comparar/ingles.mjs`); 18/10 favicon,
  menú y presencia en la sala; 21/09 reintento de conexión en la puja, sandbox sin clave y recorrido de los tres roles.
- **Pendiente de Jonas:** OPcache y medición en el sandbox; claves `RECAPTCHA_SITE_KEY` / `RECAPTCHA_SECRET_KEY`; probar
  el código del 2FA y crear un Editor; sala con video y presencia; log de la CSP; `post_max_size` 64M /
  `upload_max_filesize` 20M si falta; imagen de 1200 × 630 de Colliers. Con el cliente: ver «Decisiones pendientes» del BACKLOG.
- **Antes de reportar algo como listo:** `node tools/comparar/recorrido-roles.mjs` (los tres roles con clics) en verde.
- **Criterio de Jonas (21/09), para todo el proyecto:** si algo necesita que alguien escriba una URL o sepa algo que la
  pantalla no dice, está mal. Los recorridos en navegador tampoco escriben URL: hacen clic, copian y pegan.
- **Decisiones vigentes:** el **PDF de reportes** está fuera del alcance; la **habilitación única** está construida,
  apagada y con el monto vacío; **«cerrar el remate completo» lo decide Jonas después (no implementar)**; el cambio de lote
  en la sala se destacó el 21/09 a su pedido.
- Antes de tocar el camino de la puja: leer `docs/RENDIMIENTO-SIN-OPCACHE.md`.
- Antes de correr recorridos en navegador: **uno a la vez** y cerrar Chrome al terminar. Detener `php artisan serve` deja
  vivo el `php -S` hijo: cerrar lo que escuche en el puerto antes de levantar otro.

## 1. Jornadas

| Fecha | Archivo | Resumen |
|---|---|---|
| 2026-09-15 | [`docs/progreso/2026-09-15.md`](progreso/2026-09-15.md) | Bloque T cerrado (11 pantallas 1:1), infraestructura de deploy (B) |
| 2026-09-16 | [`docs/progreso/2026-09-16.md`](progreso/2026-09-16.md) | Servidor conectado, hook de assets, C completo, J núcleo con concurrencia real, D, K |
| 2026-09-17 | [`docs/progreso/2026-09-17.md`](progreso/2026-09-17.md) | Rendimiento sin OPcache, K terminado, I, V, G, H, M, N, manual de pruebas y push |
| 2026-09-21 | [`docs/progreso/2026-09-21.md`](progreso/2026-09-21.md) | Folio (500 en el sandbox), suite contra MySQL, `1040 Too many connections` y reintento en la puja, experiencia de prueba sin URL, martillero y «Tus remates», sandbox sin clave, sala ante remate borrado/cancelado, cambio de lote evidente, recorrido de los tres roles. §11: plan de prueba del 22/09 |
| 2026-10-18 | [`docs/progreso/2026-10-18.md`](progreso/2026-10-18.md) | Favicon, menú y pie hacia el sitio corporativo, presencia en la sala (latido sin Laravel, nombres solo en el panel) |
| 2026-10-20 | [`docs/progreso/2026-10-20.md`](progreso/2026-10-20.md) | Sitio público en inglés: prefijo `/en`, conmutador en todas las cabeceras públicas, 886 cadenas en `lang/es` y `lang/en`, formatos por idioma, documentos «(in Spanish)». Subido |
| 2026-10-22 | [`docs/progreso/2026-10-22.md`](progreso/2026-10-22.md) | Última tanda: enlaces de cabecera y pie, horarios de visita, rol Editor, segundo factor por correo, reCAPTCHA v2 (subido el 22/10 a las 19:54 UTC hasta `86ddaa6`); noche: 16 regiones y medición por tramos de la puja (subido el 23/10) |
| 2026-10-23 | [`docs/progreso/2026-10-23.md`](progreso/2026-10-23.md) | Diagnóstico tras el corte de luz, documentos al día, límite de envíos del registro, L y parte de P verificados, OPcache bloqueante. Subido |
| 2026-10-21 | [`docs/progreso/2026-10-21.md`](progreso/2026-10-21.md) | QA de Jonas sobre el sandbox en 4 grupos: aviso de conexión y adjudicación visible (sala, Mi cuenta); HTTPS forzado, cabeceras con CSP y Turnstile (reemplazado por reCAPTCHA v2 el 22/10); sala sin transmisión, condiciones en inglés, cuentas por aprobar y SEO; menores (hover, «sobre la base», promedio, 405, buscador). Subido el 21/10 |
| 2026-09-20 | [`docs/progreso/2026-09-20.md`](progreso/2026-09-20.md) | Los 8 puntos del QA de Jonas: martillero, cierre anticipado, hover, registro que no pierde nada, subasta de prueba, ayudas del formulario, «06 Usuarios» y `Habilitacion`. Tarde: push, habilitación única configurable y PDF fuera de alcance |

---

## 2. Cosas que no se pueden olvidar

### Respaldar

- **`APP_KEY` del servidor** (Jonas ya la respaldó el 16/09): cifra el RUT y el índice ciego, y firma la cookie de
  acceso. Si se pierde o se regenera, los datos cifrados quedan ilegibles. **No correr `key:generate` dos veces.**
- **`.env` del servidor** completo (fuera del repo).
- La base MariaDB, cuando haya datos reales.

### Reglas de Jonas vigentes

- **Sin push hasta su aprobación explícita.** Cada push despliega en ≤ 5 min.
- **Cada tarea terminada actualiza `docs/BACKLOG.md` en el mismo commit.**
- **Nunca reescribir historial ni `push --force`.**
- **Nunca commitear `.env`.** El repositorio es público: tampoco nombres de base, usuarios ni claves en
  archivos versionados.
- **No configurar GitHub Actions.**
- **Handler PHP:** versionado en `public/.htaccess` dentro de `<IfModule LiteSpeed>` (el deploy revierte cambios manuales).
- **Clonar en el servidor con `github-colliers`, no con `github.com`.**
- **Avisar antes de correr el arnés** para que libere memoria; un proceso por ancho.
- Decisiones reversibles y de bajo riesgo: se toman y se reportan. Se pregunta solo por reglas de
  negocio, modelo de datos o acciones irreversibles. Pantallas que el diseño no tiene: se pregunta.
- Migraciones **solo aditivas** (nunca renombrar ni borrar columnas).

### Trampas del entorno (ya nos pasaron)

- **Bash con comillas dobles y backticks** ejecuta los backticks como comandos. Para scripts con
  Markdown o código: escribir un archivo con la herramienta Write y ejecutarlo; nunca `node -e "..."`
  con backticks. **Heredocs largos en bash también fallan:** usar Write.
- **`sed` desde bash se come las barras invertidas** en PHP (`\App\...`). Editar PHP con la
  herramienta Edit. **Python:** strings con `\App\…` necesitan `r'…'`; mejor un script escrito con Write.
- **Windows**: el enlace `public/storage` no lo detecta `is_dir`; usar `file_exists`/`is_link`.
  `rename` sobre un archivo retenido falla («Acceso denegado»): el emisor reintenta y copia solo en Windows.
- **SQLite local** no soporta `select version()`; usar `getServerVersion()`.
- **Un recorrido que hace clic en «la primera tarjeta» puede estar probando otra cosa**: el martillero del seeder tiene
  varios remates. Elegir siempre por folio o texto visible (21/09: pareció reproducir el bug del cierre y no lo era).
- **SQLite no valida el largo de `VARCHAR`** ni reordena JSON como MySQL: antes de subir algo que escribe en la base, correr
  también `composer test:mysql` (MySQL de Laragon encendido). Así se escapó el folio de 22 caracteres (21/09).
- **Playwright**: usar `channel: 'chrome'` (la descarga de Chromium falla); `waitUntil: 'load'`, no
  `networkidle` (el iframe de YouTube nunca queda inactivo); `clock.setFixedTime`, no `pauseAt`.
- **Comentarios CSS con `*/` dentro** (por ejemplo en rutas) rompen el build de Vite.
- **Memoria**: las fotos a 6000 px tumbaban los servidores; ya están a 1920 px. Con navegadores
  abiertos el arnés se cae.
- **Vite no se compila en el servidor**: si se toca CSS/JS, `npm run build` y commitear `public/build` (el hook lo verifica).
- **`artisan serve` atiende una petición a la vez**: sirve para el arnés y para `sala-real.mjs`, **nunca** para
  concurrencia (usar `tools/concurrencia/servidor.php`: Apache de Laragon + mod_php + OPcache).
- **mod_php es multihilo**: leer `.env` por petición no es seguro entre hilos → configuración en caché.
- **Sesión serializada en JSON** (`config/session.php`); **Fortify `paths`** anidados por la notación de puntos.
- **`tools/concurrencia/prueba.php` recrea su base**: nunca apuntarlo al sandbox.
- **Pantallas protegidas en local**: `/revision/entrar/{rol}` (y `?usuario=correo`), requiere `migrate:fresh --seed`.
- La ruta `/revision` depende de `app()->isLocal()`: con la caché de rutas del servidor no existe, que es lo buscado.
- **Bash de esta herramienta colapsa `\\` en heredocs y en `sed`:** para PHP con espacios de nombres, escribir el script con
  Write (Python con cadenas `r'…'`) o usar Edit. Nunca `sed` con barras invertidas.
- **Arreglos con `+` en PHP conservan la clave de la izquierda:** para sumar URL a un arreglo base usar `array_replace`
  (así se perdieron las acciones de garantía en Postores, detectado por el recorrido en navegador).
- **GD descomprime la foto entera** (~5 bytes por píxel): `App\Support\Imagenes` calcula la memoria antes de abrirla.
- **Cola síncrona en pruebas:** una bitácora «pendiente» se anota ANTES de `notify()` (el envío ocurre dentro).
- **Medición a 375 px puede dar 43,99 px** mientras cargan las fuentes: repetir antes de dar por mala un área táctil.
- **Blade no reconoce una directiva pegada a una palabra:** `texto@endif` queda literal y rompe el compilado. Usar un
  ternario dentro de `{{ }}`.
- **En las pruebas, `assertSessionHasErrors` reinicia la sesión** y envejece el flash: un `get()` posterior ya no ve los
  errores. Las aserciones de sesión van después de la petición siguiente, o se comprueban sobre la página renderizada.
- **`$request->file()` deja en caché los archivos convertidos:** lo que se agregue después con `files->set()` no llega a
  la validación (hay que leer de `$request->files` y descartar esa caché).
- **`UploadedFile::fake()->create('x.pdf')` son bytes al azar** y la regla `mimes` mira el contenido: para probar un
  adjunto guardado y repuesto hace falta un PDF real.
- **Los mensajes de PHPUnit dicen la condición esperada, no la real:** «Failed asserting that '…' contains "X"» es un
  `assertSee` que falló.
- **Borrar un usuario exige borrar su rastro:** `access_logs` y `notificaciones_log` apuntan a `users` con `restrict`.
- **El MySQL de Laragon puede escuchar en el 3307:** `PRUEBAS_DB_PORT=3307 composer test:mysql`.
- **Las pruebas no escriben en el `storage` real:** `Tests\TestCase` da a la presencia una carpeta por proceso; cualquier
  archivo nuevo que escriba el código en `storage/app` necesita lo mismo.
- **`tools/rendimiento/medir.php` y `tools/concurrencia/prueba.php` recrean su base** (`colliers_concurrencia`): nunca
  apuntarlos al sandbox. Para el sandbox: `tools/sandbox/medir-servidor.php` (solo GET).
