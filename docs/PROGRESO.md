# PROGRESO — Remates Colliers · Fase 3

> Punto de retome. Última actualización: **2026-09-20, cierre de jornada.**
> Leer junto con `CLAUDE.md` (reglas) y **`docs/BACKLOG.md` (estado vivo de cada tarea)**.
> El detalle de cada jornada está en `docs/progreso/AAAA-MM-DD.md`.

---

## 0. Para retomar en 30 segundos

- **Estado (20/09, cierre):** T, C, I y G completos; B completo. J, D, K, V, H, M, N y O hechos y probados en local.
  E/F empezado: la pantalla **«06 Usuarios»**.
- **163 pruebas PHPUnit** en verde y los recorridos en navegador real al día (`tools/comparar/*.mjs`).
- **Jonas probó el ciclo completo en el sandbox el 20/09** y trajo 8 puntos: los ocho están atendidos (el 7 a medias,
  por decisión suya). Detalle en `docs/progreso/2026-09-20.md` y en la tabla del comienzo de `docs/BACKLOG.md`.
- **Subido a `main` hasta `31db759`** (9 commits, con su aprobación del 20/09). **`bf27a21` queda local**: es la
  habilitación única configurable y la salida del PDF; espera su visto bueno.
- **Siguiente para Jonas en el sandbox:**
  1. Subir `post_max_size` y `upload_max_filesize` (cPanel → MultiPHP INI Editor; recomendado 64M / 20M). Sin eso, un
     registro con tres documentos no llega nunca: PHP descarta el envío entero. Confirmar con `colliers:diagnostico`.
  2. Probar el ciclo de pujas con el botón **«Crear subasta de prueba»** de `/admin/subastas` (ya no hace falta la
     terminal) y crear al martillero desde **«06 Usuarios»**.
- **Siguiente para Claude:** lo que salga de esas pruebas → Bloque L (streaming) → resto de E/F.
- **Decisiones cerradas el 20/09:** el aviso de cambio de lote queda como está; el **PDF de reportes sale del alcance**
  (no está en el acta); la **habilitación única** se construyó configurable, apagada y con el monto vacío, para poder
  mostrársela a Colliers. Si la eligen, fijan el monto en Configuración → Garantías.
- OPcache está **apagado** en el sandbox: leer `docs/RENDIMIENTO-SIN-OPCACHE.md` antes de tocar el camino de la puja.
- Antes de correr recorridos en navegador: **uno a la vez** y cerrar Chrome al terminar.

## 1. Jornadas

| Fecha | Archivo | Resumen |
|---|---|---|
| 2026-09-15 | [`docs/progreso/2026-09-15.md`](progreso/2026-09-15.md) | Bloque T cerrado (11 pantallas 1:1), infraestructura de deploy (B) |
| 2026-09-16 | [`docs/progreso/2026-09-16.md`](progreso/2026-09-16.md) | Servidor conectado, hook de assets, C completo, J núcleo con concurrencia real, D, K |
| 2026-09-17 | [`docs/progreso/2026-09-17.md`](progreso/2026-09-17.md) | Rendimiento sin OPcache, K terminado, I, V, G, H, M, N, manual de pruebas y push |
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
- **`tools/rendimiento/medir.php` y `tools/concurrencia/prueba.php` recrean su base** (`colliers_concurrencia`): nunca
  apuntarlos al sandbox. Para el sandbox: `tools/sandbox/medir-servidor.php` (solo GET).
