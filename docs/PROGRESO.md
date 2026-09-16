# PROGRESO — Remates Colliers · Fase 3

> Punto de retome. Última actualización: **2026-09-15, cierre de jornada.**
> Leer junto con `CLAUDE.md` (reglas, decisiones y backlog). Este archivo dice **dónde quedamos**.

---

## 0. Para retomar en 30 segundos

- Rama `main`, **17 commits locales por delante de `origin/main`. Nada de eso está en GitHub.**
- Bloque T **cerrado y verificado**. B-infra **terminado y probado** (13/13 pruebas).
- **El arnés completo YA se corrió el 15/09** (todas las pantallas + filtros + recorridos), con todo en
  verde (detalle en §1). Jonas lo quiere correr mañana: es una repetición de control, no hay nada
  pendiente de verificar.
- **Siguiente acción: esperar el visto bueno de Jonas para el push.** No hacer push sin su aprobación
  explícita en el mensaje del día.
- Antes de correr el arnés, **avisar a Jonas para que libere memoria** (el equipo se queda sin RAM).

---

## 1. Qué se hizo hoy (2026-09-15)

### 1.1 Bloque T: pantallas terminadas y verificadas

Diferencia medida con `tools/comparar/` contra el prototipo (corrida completa del 15/09).
**Escritorio** = anchos 1120, 1280, 1366, 1440 (1:1 estricto).
**Tablet/móvil** = 375, 759, 760, 1024, 1119: el prototipo es solo referencia; se exige sin scroll
horizontal y áreas táctiles ≥ 44 px. **Las 105 comprobaciones de tablet/móvil pasaron.**

| Pantalla | Ruta local | Variantes | Diferencia en escritorio |
|---|---|---|---|
| Login | `/ingresar` | 1 | 0 % |
| Registro de postor | `/registro` | 1 | 0 % |
| Estado de cuenta | `/mi-cuenta?estado=` | 5 | 0 % |
| Listado de remates | `/` | 4 sesiones | 0,30 – 0,33 % (*) |
| Detalle remate próximo | `/remates/militares` | 4 sesiones | 0,008 – 0,011 % |
| Detalle remate en vivo | `/remates/apoquindo` | 4 sesiones | 0,013 – 0,017 % |
| Sala de puja | `/remates/apoquindo/sala` | 1 | 0,015 – 0,020 % |
| Admin · dashboard | `/admin` | 1 | 0 % |
| Admin · subastas | `/admin/subastas` | listado, formulario, modal de cierre | 0 % |
| Admin · postores | `/admin/postores` | listado, ficha | 0 – 0,001 % |
| Admin · reportes | `/admin/reportes` | 1 | 0 % |

(*) El listado difiere solo por decisiones aprobadas, revisadas imagen por imagen: grupo «Garantía
requerida» oculto, «Coordinar visita» en vez de «Agendar visita», sin botón de favoritos. Los detalles
y la sala difieren por remuestreo de fotos y el contador de «personas viendo» quitado.

En tablet/móvil los porcentajes altos del admin (20–55 %) son las adaptaciones aprobadas
(barra superior + menú deslizable, tarjetas, tablas con primera columna fija), no errores.

**Listado a 760 px: 22 % de diferencia, deliberada** (detectada en la corrida de control del 16/09).
En tablet (760–1119) el prototipo muestra los botones **Grilla / Tabla**, pero no funcionan: fuerza la
grilla y la tabla nunca aparece. Laravel los oculta desde `0925424` (`.listado__vista { display: none; }`
en `resources/css/paginas/listado.css`). A 760 px los botones del prototipo bajan a una segunda fila
(62 px), así que todo lo que sigue queda desplazado y la diferencia se acumula. A 1024 y 1119 px caben en
la misma fila y la diferencia es ≈ 0,35 %. Sin scroll horizontal y 0 táctiles < 44 px en todas.

Corrida de control del 16/09 (listado): escritorio 0,297–0,331 %; 375 px ≈ 0,61 %; 759 px ≈ 0,36 %;
760 px ≈ 22 % (lo anterior); 1024/1119 px ≈ 0,33–0,36 %. Filtros 42/42, interacción idéntica salvo
favoritos, `php artisan test` 13/13. Un proceso por ancho: pico de Chrome 2,2 GB, Node 309 MB, PHP 207 MB.

**Pruebas de interacción (15/09, todas OK):**

| Script | Qué verifica | Resultado |
|---|---|---|
| `filtros-interaccion.mjs` | 20 comprobaciones a 1440 px y 22 a 375 px: grupos, orden de regiones, comunas desde datos y por región, cada filtro, limpiar, estado vacío, panel lateral móvil | Todo OK |
| `listado-interaccion.mjs` | 11 puntos contra el prototipo (carga, cargar más, orden, cerrados, búsqueda vacía, tabla, ocultar filtros, panel tablet) | Idéntico salvo favoritos (quitado a propósito) |
| `sala-interaccion.mjs` | 11 puntos contra el prototipo (estado, validación, puja rápida, modal, estado final) | Idéntico |
| `recorrido-garantia.mjs` | Aprobar una garantía a 375 px desde tarjeta y desde ficha | OK |

Además: raíz invertida (`/` = listado, `/remates` redirige 301 a `/`, índice de revisión en
`/revision` **solo con `APP_ENV=local`**).

### 1.2 B-infra: qué quedó hecho

Todo probado con `php artisan test` (13/13) y `php artisan optimize` funciona.

- **`colliers:instalar`** (idempotente, corre después de `migrate`): carpetas de storage, enlace
  `public/storage`, primer administrador desde `COLLIERS_ADMIN_*` (solo si no hay ninguno y la clave
  tiene ≥ 12 caracteres; obliga a cambiarla en el primer ingreso).
- **`colliers:puede-desplegar`**: sale con 0 = desplegar, **75 = no desplegar**. Hoy bloquea si existe
  `storage/app/bloquear-deploy`. El bloqueo por remate en curso se agrega en el Bloque J.
- **`colliers:diagnostico`**: PHP ≥ 8.3 y extensiones, APP_KEY, APP_DEBUG, zona UTC, locale es,
  base de datos, migraciones pendientes, tablas, administrador, permisos, enlace de storage,
  `public/build/manifest.json`, vendor, latido del cron (< 180 s) y cola `database`. Sale con 1 si hay
  fallas críticas.
- **Programador** (`routes/console.php`): latido cada minuto en `storage/framework/latido-programador`
  y `queue:work --stop-when-empty --max-time=50 --tries=3 --backoff=60` cada minuto sin solaparse.
- **Clave de acceso al sandbox**: middleware `AccesoSandbox` sobre todo el grupo `web` (incluido
  `/admin`). Formulario en `/acceso`, 10 intentos por minuto por IP, cookie `colliers_acceso` con HMAC
  de la clave (firmada con APP_KEY), cabecera `X-Robots-Tag: noindex, nofollow`. Vacía = sitio abierto.
- **Páginas de error** propias en español: 403, 404, 419, 429, 500, 503.
- **Traducciones** `lang/es/`: validation, auth, passwords, pagination.
- **`.env.example`** completo, comentado y sin secretos.
- **Migración aditiva** `2026_09_15_000001_agregar_rol_y_estado_a_users`: `rol`, `estado`,
  `debe_cambiar_clave`.
- **`config/colliers.php`**: admin, acceso, bloqueo de deploy, latido, zona de visualización.

### 1.3 Commits locales (no están en GitHub)

Del más antiguo al más nuevo:

| Hash | Contenido |
|---|---|
| `8adf07e` | CLAUDE.md: desarrollo local, sin push hasta aprobación |
| `56166da` | Índice de revisión en la raíz; listado pasa a `/remates` durante T |
| `0925424` | Listado de remates (4 variantes de sesión) |
| `f3eef76` | Fotos de muestra reducidas a 1920 px (34 MB → 4,2 MB); el arnés las sirve también al prototipo |
| `b288fb6` | Detalle de remate próximo y en vivo |
| `17e93a6` | Sala de puja verificada |
| `11fc209` | Admin dashboard + recorrido móvil «aprobar garantía» |
| `3ee0b4d` | Admin subastas (listado, formulario, modal de cierre; menú de acciones móvil) |
| `5f0f774` | Admin postores (listado y ficha) |
| `7596d11` | Admin reportes |
| `36f53b1` | Alcance de funciones del diseño aplicado (favoritos fuera, contador fuera, visita → contacto, lupa → buscador) |
| `a4631af` | Mapa: mosaicos OpenStreetMap en gris claro |
| `2545c35` | Fotos reemplazadas por imágenes Unsplash License (`public/img/demo/CREDITOS.md`) |
| `d97d5be` | Listado: filtros adicionales conectados (región/comuna desde datos, «visita programada» quitado, garantía oculta) |
| `6ce19ab` | Bloque B: infraestructura de deploy (comandos, programador, clave de sandbox, errores, es, `.env.example`, migración de users) |
| `53dc315` | La raíz pasa a ser el listado; `/remates` redirige; `/revision` solo en local |
| `c50ebeb` | Bloque T cerrado: `filtros-interaccion.mjs`, ajuste de `listado-interaccion.mjs`, CLAUDE.md actualizado |

Más este archivo (`docs/PROGRESO.md`), en su propio commit.

### 1.4 Qué está en GitHub y qué solo en local

- **En GitHub (`origin/main`)**, lo que el cron del servidor desplegaría hoy:
  `9eea48a` B0 + arnés + Login · `2596a4d` Registro · `43f683e` Estado de cuenta.
  Esa versión todavía tiene las fotos de origen desconocido y **no tiene clave de acceso**.
- **Solo en local**: los 17 commits de §1.3 (+ este). Todo lo de listado, detalles, sala, admin,
  alcance, mapa, fotos Unsplash, filtros y B-infra.
- **Nunca en GitHub** (ignorado): `.env`, carpetas del prototipo, zips, actas `.docx`,
  `tools/comparar/salida/`, `storage/framework/latido-programador`.

---

## 2. Estado actual

| Bloque | Estado |
|---|---|
| A — Entorno y servidor | **Completo** (hecho por Jonas) |
| T — Diseño a Blade | **Completo y verificado** (falta solo el push y que Jonas lo revise en el sandbox) |
| B — Base Laravel | **En progreso**: infraestructura completa. Faltan Fortify (se hace en D), `maatwebsite/excel` (en O) y confirmar el handler PHP en `.htaccess` (lo hace Jonas en el servidor) |
| C, J, D, K, I, V, G, H, M, N, L, E/F, O, P, Q, R, S | **Pendientes** |

### Qué falta para cerrar el Bloque T

Técnicamente nada: todas las casillas de T están marcadas en `CLAUDE.md` con verificación ejecutada.
Queda solo:

1. (Opcional, pedido por Jonas) repetir la corrida del arnés como control.
2. Visto bueno de Jonas → push.
3. Jonas revisa las pantallas en el sandbox (lo que no se puede comprobar en local).

---

## 3. Qué se hace mañana, en orden

### Paso 1 — Arnés de control (filtros y regresión)

1. **Pedir a Jonas que cierre navegadores y libere memoria. Esperar su confirmación.**
2. Levantar los dos servidores desde la raíz del proyecto, en segundo plano:
   - `php artisan serve --port=8000`
   - `php -S 127.0.0.1:8081 -t .` (prototipos; necesita internet)
3. Correr:
   - `node tools/comparar/filtros-interaccion.mjs` → debe terminar en «Todo OK».
   - `npm run comparar -- listado` → escritorio ≈ 0,30 % (diferencias deliberadas de §1.1).
   - `node tools/comparar/listado-interaccion.mjs` → idéntico salvo `guardado: "sin botón"`.
   - Si Jonas quiere la corrida completa: cada pantalla con `npm run comparar -- <pantalla>`
     (`login registro cuenta listado detalle-proximo detalle-vivo sala admin-dashboard admin-subastas
     admin-postores admin-reportes`). Tarda más de 10 minutos: correrlo en segundo plano.
   - `php artisan test` → 13/13.
4. Detener los servidores y reportar números comparados con §1.1.

### Paso 2 — Push (solo con aprobación explícita de Jonas)

1. `git status` limpio y `git ls-files | grep -E "(^|/)\.env$"` sin resultados.
2. `git push origin main` (desde la máquina local se usa `origin` normal).
3. **Sin `--force`. Nunca reescribir historial.**
4. Recordar: el cron del servidor despliega en ≤ 5 min. Si el servidor ya tiene el script viejo,
   desplegará sin clave de acceso configurada hasta que exista el `.env` → coordinar con el paso 3.

### Paso 3 — Conectar el servidor (entregar a Jonas pasos numerados, sin explicaciones largas)

Jonas pidió exactamente esto, en este orden:

1. Clonar con el alias SSH **`github-colliers`** (hay otro proyecto en ese servidor: **no usar
   `github.com`** en la URL). Forma: `git@github-colliers:jonas549/remates-colliers.git` en
   `/home/oywadfan/remates-colliers`.
2. `.env` completo para pegar en `nano`: dominio `https://rematescolliers.sandboxdelta.com`,
   `APP_ENV=production`, `APP_DEBUG=false`, `DB_CONNECTION=mysql`, `SESSION_SECURE_COOKIE=true`,
   `LOG_LEVEL=warning`, `COLLIERS_ACCESO_CLAVE`, `COLLIERS_ADMIN_*`. Nombre de base y usuario: están en
   la memoria local de Claude (no se versionan; el repo es público). Contraseñas: las pone Jonas.
3. `APP_KEY` aparte, con advertencia de respaldo (ver §5).
4. Comandos en orden: `composer install --no-dev --optimize-autoloader` → `php artisan key:generate`
   → `php artisan migrate --force` → permisos de `storage` y `bootstrap/cache` → `php artisan
   storage:link` → `php artisan colliers:instalar` → `php artisan optimize` →
   `php artisan colliers:diagnostico`.
5. Bloque exacto para `~/scripts/deploy-colliers.sh`:
   - **Antes** de `git reset --hard`: `php artisan colliers:puede-desplegar`; si sale 75, terminar sin
     desplegar. Tolerar que el comando no exista (primer deploy).
   - **Después** de `migrate --force`: `php artisan optimize:clear` → `php artisan colliers:instalar`
     → `php artisan optimize`.
   - Además, **una línea de cron** cada minuto:
     `cd /home/oywadfan/remates-colliers && php artisan schedule:run >> /dev/null 2>&1`
     (usar la ruta completa del binario PHP 8.4 del servidor).
6. Document Root en cPanel: `/home/oywadfan/remates-colliers/public`.

Después: Jonas verifica en el sandbox; `colliers:diagnostico` debe mostrar el latido del cron OK tras
2–3 minutos.

---

## 4. Decisiones pendientes

### 4.1 De Jonas (me las debe a mí)

- **Visto bueno para el push** (bloquea el paso 2).
- **Nombre exacto del handler PHP 8.4 en cPanel/LiteSpeed**, para descomentar `public/.htaccess`
  (Jonas dijo que eso lo configura él al conectar).
- **Subir el acta del 25/08** al material local de referencia.
- **Idioma EN/ES** (Bloque N) y **exportar a PDF** (Bloque O): hoy se ven en la interfaz y no funcionan.
- **Activar el filtro «Garantía requerida»**: queda oculto (`$mostrarFiltroGarantia = false` en
  `resources/views/remates/index.blade.php`); pasará a ser un ajuste del panel en el Bloque V.

### 4.2 Del cliente (no bloquean; se anota y se sigue)

- Campos exactos del registro de postor (hoy: ~20 del diseño, estructura extensible).
- Máquina de estados única (propuesta enviada el 15/09; ver `CLAUDE.md` §7).
- Cierre anticipado: ¿adjudica la mejor puja o anula el lote?
- Varios lotes: ¿garantía por remate o por lote, y sobre qué base el 10 %?
- Varios lotes: ¿el siguiente abre al cierre del anterior + pausa, u horario fijo?
- ¿Se enmascara la identidad de los postores en el feed? (el diseño usa «Postor #N»)
- Garantía del perdedor: ¿devolución? (el diseño lo afirma; el acta no lo define)
- 2FA: ¿solo administradores o también postores?
- Lista exacta de gráficos y columnas de los reportes.

---

## 5. Cosas que no se pueden olvidar

### Respaldar

- **`APP_KEY` del servidor**: cifra el RUT (Bloque C) y firma la cookie de acceso. Si se pierde o se
  regenera, los datos cifrados quedan ilegibles. Guardarla fuera del servidor apenas se genere.
  **No correr `key:generate` dos veces en el servidor.**
- **`.env` del servidor** completo (fuera del repo).
- La base MariaDB, cuando haya datos reales.

### Reglas de Jonas vigentes

- **Sin push hasta su aprobación explícita.** Cada push despliega en ≤ 5 min.
- **Nunca reescribir historial ni `push --force`.**
- **Nunca commitear `.env`.** El repositorio es público: tampoco nombres de base, usuarios ni claves en
  archivos versionados.
- **No configurar GitHub Actions.**
- **Handler PHP de `.htaccess`: queda comentado.** El servidor es terreno de Jonas.
- **Clonar en el servidor con `github-colliers`, no con `github.com`.**
- **Avisar antes de correr el arnés** para que libere memoria.
- Decisiones reversibles y de bajo riesgo: se toman y se reportan. Se pregunta solo por reglas de
  negocio, modelo de datos o acciones irreversibles.
- Migraciones **solo aditivas** (nunca renombrar ni borrar columnas).

### Trampas del entorno (ya nos pasaron)

- **Bash con comillas dobles y backticks** ejecuta los backticks como comandos. Para scripts con
  Markdown o código: escribir un archivo con la herramienta Write y ejecutarlo; nunca `node -e "..."`
  con backticks.
- **`sed` desde bash se come las barras invertidas** en PHP (`\App\...`). Editar PHP con la
  herramienta Edit.
- **Windows**: el enlace `public/storage` no lo detecta `is_dir`; usar `file_exists`/`is_link`.
- **SQLite local** no soporta `select version()`; usar `getServerVersion()`.
- **Playwright**: usar `channel: 'chrome'` (la descarga de Chromium falla); `waitUntil: 'load'`, no
  `networkidle` (el iframe de YouTube nunca queda inactivo); `clock.setFixedTime`, no `pauseAt`.
- **Comentarios CSS con `*/` dentro** (por ejemplo en rutas) rompen el build de Vite.
- **Memoria**: las fotos a 6000 px tumbaban los servidores; ya están a 1920 px. Con navegadores
  abiertos el arnés se cae.
- **Vite no se compila en el servidor**: si se toca CSS/JS, `npm run build` y commitear `public/build`.
- **`artisan serve` atiende una petición a la vez**: sirve para el arnés, **nunca** para pruebas de
  concurrencia del Bloque J (usar Apache/Nginx de Laragon + MariaDB).
- **Datos de demo del listado**: el filtro de fecha usa la cuenta regresiva (`delta`), no el texto de
  la fecha; Colina aparece en «Próximos 30 días» aunque su texto diga 07-10. Desaparece con datos
  reales (Bloque C).
- La ruta `/revision` depende de `app()->isLocal()`: con la caché de rutas del servidor
  (`APP_ENV=production`) no existe, que es lo buscado.

### Después de conectar el servidor

- Siguiente bloque según el orden: **C (modelo de datos)** → **J núcleo** (con pruebas de concurrencia
  obligatorias) → D.
- Medir en el sandbox antes de cerrar J: procesos simultáneos (EP), CPU, `max_execution_time`,
  frecuencia mínima de cron, HTTP saliente.
- Implementar en J el bloqueo de deploy con remate en curso dentro de `colliers:puede-desplegar`.
