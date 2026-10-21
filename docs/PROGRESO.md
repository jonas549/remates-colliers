# PROGRESO — Remates Colliers · Fase 3

> Punto de retome. Última actualización: **2026-10-21.**
> Leer junto con `CLAUDE.md` (reglas) y **`docs/BACKLOG.md` (estado vivo de cada tarea)**.
> El detalle de cada jornada está en `docs/progreso/AAAA-MM-DD.md`.

---

## 0. Para retomar en 30 segundos

- **Estado (21/09):** T, C, I y G completos; B completo. J, D, K, V, H, M, N y O hechos y probados en local.
  E/F empezado: la pantalla **«06 Usuarios»**.
- **179 pruebas PHPUnit**: en SQLite (`php artisan test`, 2 se saltan) y en MySQL (`composer test:mysql`, 179/179).
  Recorridos en navegador real al día (`tools/comparar/*.mjs`).
- **21/09:** folio de la subasta de prueba corregido (500 en el sandbox), suite contra MySQL, análisis del
  `1040 Too many connections` del hosting y **reintento de conexión en la puja** con margen seguro de 5 s vigilado por el
  panel. Detalle en `docs/progreso/2026-09-21.md`.
- **21/09, noche:** martillero a cargo de la subasta de prueba e inicio «Tus remates», **sandbox sin clave de acceso**
  (noindex fuera de producción), la sala respeta un remate borrado o cancelado, **cambio de lote evidente**, y el
  recorrido de los tres roles `tools/comparar/recorrido-roles.mjs` (50/50).
- **Todo subido a `main` hasta `450ffae`** (aprobado el 21/09). **Locales sin push:** `cb5ea9e` (progreso del 21/09) y lo
  del 18/10.
- **18/10 (QA de Jonas):** favicon, cabecera y pie hacia el sitio corporativo (solo lo que tiene página allí) y
  **presencia en la sala** (contador para el postor; lista con nombres solo en el panel; latido sin Laravel). Detalle y qué
  probar en el sandbox: `docs/progreso/2026-10-18.md`.
- **Mañana (22/09): Jonas prueba en el sandbox** con el plan de `docs/progreso/2026-09-21.md` §11.
- **20/10: sitio público en inglés** completo en local, sin push (`docs/progreso/2026-10-20.md`). Pendiente de Jonas: revisar el glosario y las cadenas dudosas, confirmar con el cliente la línea «la versión en español rige» y el registro de extranjeros sin RUT. Antes de tocar textos del sitio: van en `lang/es` y `lang/en` (mismas claves); `tools/comparar/ingles.mjs` revisa `/en` en navegador.
- **21/10: QA de Jonas sobre el sandbox, 18 hallazgos en 4 grupos**, hechos en local y sin push (un commit por grupo:
  `6dc3a3b`, `080f99e`, `0055101` y el del grupo 4). Pendiente de Jonas: el umbral del límite de pujas (hoy 30/min por
  usuario, cuenta también los rechazados), cargar `TURNSTILE_SITE_KEY` / `TURNSTILE_SECRET_KEY`, una imagen de 1200 × 630
  para compartir si Colliers la quiere, y probar en el sandbox lo de cada grupo (`docs/progreso/2026-10-21.md`). El 2FA
  queda fuera de la tanda. Fuera de la tanda también OPcache, el rol Editor y los enlaces sin destino. Recorrido nuevo de
  esta tanda: `tools/comparar/qa-visibles.mjs`; de seguridad, `tools/comparar/seguridad.mjs`.
- **Antes de reportar algo como listo:** `node tools/comparar/recorrido-roles.mjs` (los tres roles con clics) en verde.
- **Criterio de Jonas (21/09), para todo el proyecto:** si algo necesita que alguien escriba una URL o sepa algo que la
  pantalla no dice, está mal. Los recorridos en navegador tampoco escriben URL: hacen clic, copian y pegan.
- **Pendiente de Jonas en el sandbox:** margen a 5 s y aviso del cierre al 10 %, `colliers:diagnostico` (conexiones),
  `post_max_size` 64M / `upload_max_filesize` 20M si falta, y el recorrido de los tres roles (plan en el registro del 21/09 §11).
- **Siguiente para Claude:** lo que salga de esas pruebas → Bloque L (streaming) → resto de E/F.
- **Decisiones vigentes:** el **PDF de reportes** está fuera del alcance; la **habilitación única** está construida,
  apagada y con el monto vacío; **«cerrar el remate completo» lo decide Jonas después (no implementar)**; el cambio de lote
  en la sala se destacó el 21/09 a su pedido.
- OPcache está **apagado** en el sandbox: leer `docs/RENDIMIENTO-SIN-OPCACHE.md` antes de tocar el camino de la puja.
- Antes de correr recorridos en navegador: **uno a la vez** y cerrar Chrome al terminar.

## 1. Jornadas

| Fecha | Archivo | Resumen |
|---|---|---|
| 2026-09-15 | [`docs/progreso/2026-09-15.md`](progreso/2026-09-15.md) | Bloque T cerrado (11 pantallas 1:1), infraestructura de deploy (B) |
| 2026-09-16 | [`docs/progreso/2026-09-16.md`](progreso/2026-09-16.md) | Servidor conectado, hook de assets, C completo, J núcleo con concurrencia real, D, K |
| 2026-09-17 | [`docs/progreso/2026-09-17.md`](progreso/2026-09-17.md) | Rendimiento sin OPcache, K terminado, I, V, G, H, M, N, manual de pruebas y push |
| 2026-09-21 | [`docs/progreso/2026-09-21.md`](progreso/2026-09-21.md) | Folio (500 en el sandbox), suite contra MySQL, `1040 Too many connections` y reintento en la puja, experiencia de prueba sin URL, martillero y «Tus remates», sandbox sin clave, sala ante remate borrado/cancelado, cambio de lote evidente, recorrido de los tres roles. §11: plan de prueba del 22/09 |
| 2026-10-18 | [`docs/progreso/2026-10-18.md`](progreso/2026-10-18.md) | Favicon, menú y pie hacia el sitio corporativo, presencia en la sala (latido sin Laravel, nombres solo en el panel) |
| 2026-10-20 | [`docs/progreso/2026-10-20.md`](progreso/2026-10-20.md) | Sitio público en inglés: prefijo `/en`, conmutador en todas las cabeceras públicas, 886 cadenas en `lang/es` y `lang/en`, formatos por idioma, documentos «(in Spanish)». Local, sin push |
| 2026-10-21 | [`docs/progreso/2026-10-21.md`](progreso/2026-10-21.md) | QA de Jonas sobre el sandbox en 4 grupos: aviso de conexión y adjudicación visible (sala, Mi cuenta); HTTPS forzado, cabeceras con CSP y Turnstile; sala sin transmisión, condiciones en inglés, cuentas por aprobar y SEO; menores (hover, «sobre la base», promedio, 405, buscador). Local, sin push |
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
