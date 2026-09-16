# PROGRESO — Remates Colliers · Fase 3

> Punto de retome. Última actualización: **2026-09-16, cierre de jornada.**
> Leer junto con `CLAUDE.md` (reglas) y **`docs/BACKLOG.md` (estado vivo de cada tarea)**.
> El detalle de cada jornada está en `docs/progreso/AAAA-MM-DD.md`.

---

## 0. Para retomar en 30 segundos

- **Estado:** T y C completos. B 18/19, J 24/29, D 10/12, K 10/14 (ver `docs/BACKLOG.md`). 75/75 pruebas.
- **GitHub:** `origin/main` = `0d9ae3c` (hasta el Bloque D). **Solo en local:** `6ae4366` (Bloque K) y el registro
  del día. No hacer push sin visto bueno explícito de Jonas.
- **Esperando a Jonas:** `APP_ENV=staging` en el sandbox, cabeceras de LiteSpeed, OPcache, primer ingreso del
  administrador y límites del sandbox (lista en `docs/progreso/2026-09-16.md` §6).
- **Mañana:** revisar esas salidas → remate de demostración y pruebas en el sandbox (arnés no destructivo) →
  Bloque **I**.
- Antes de correr el arnés visual, **avisar a Jonas para que libere memoria** y correr un proceso por ancho.

## 1. Jornadas

| Fecha | Archivo | Resumen |
|---|---|---|
| 2026-09-15 | [`docs/progreso/2026-09-15.md`](progreso/2026-09-15.md) | Bloque T cerrado (11 pantallas 1:1), infraestructura de deploy (B) |
| 2026-09-16 | [`docs/progreso/2026-09-16.md`](progreso/2026-09-16.md) | Servidor conectado, hook de assets, C completo, J núcleo con concurrencia real, D, K |

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
