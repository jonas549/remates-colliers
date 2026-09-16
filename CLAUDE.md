# CLAUDE.md — Remates Colliers · Fase 3

Plataforma de subastas en vivo para Colliers Chile.
Lee este archivo completo antes de escribir una línea de código.

---

## 1. Qué estamos construyendo

Colliers Chile remata activos inmobiliarios. Esta aplicación es donde los postores **pujan en
vivo** mientras ven al martillero por una transmisión de YouTube embebida.

**Aplicación a medida, sin WordPress** (acta del 25/08). `remates.colliers.cl` queda como página
informativa y **no se integra**: no hay sincronización ni convivencia de datos. **Esta aplicación es
la fuente de verdad del catálogo de remates**, que se construye completo en Laravel como muestra el
diseño. Panel de administración propio, subdominio aparte, plazo comprometido de 30 días.

---

## 2. Documentación de referencia

| Archivo / carpeta | Dónde | Qué es |
|---|---|---|
| `docs/BACKLOG.md` | Repo | **Registro vivo del avance**: bloques, tareas marcables, orden y decisiones pendientes |
| `docs/PROGRESO.md` | Repo | Punto de retome de la última jornada |
| `Reunión Colliers Fase 3 - Preguntas clave .docx` | Local, **fuera del repo** | Acta del 18/08 con las reglas de negocio confirmadas |
| Acta del 25/08 | Pendiente de subir | Aplicación a medida sin WordPress, panel propio, subdominio, 30 días, diseño de Ángel |
| `colliers-subastas-invitado-main/` | Local, **fuera del repo** | Prototipo de la vista de invitado |
| `colliers-subastas-usuario-main/` | Local, **fuera del repo** | Prototipo de la vista logueada y del admin (contiene todo lo de invitado) |

El material de referencia no se versiona: el repositorio es público y el acta es interna del cliente.

### Sobre el diseño — importante

Lo entregó Ángel con Claude Design. **No es HTML/CSS plano:** son plantillas `<x-dc>` con
`{{ variables }}`, `<sc-if>`/`<sc-for>` y una clase `DCLogic` por pantalla, que `support.js`
renderiza en el navegador con React y Babel desde unpkg. Estilos 100% en línea y responsive
calculado en JavaScript (cortes: móvil < 760, tablet 760–1119, escritorio ≥ 1120).
La hoja `_ds/.../styles.css` ("Industry") **sí aplica** reglas base globales (box-sizing,
line-height 1.55, Barlow Condensed en títulos sin fuente explícita).

Por eso el traspaso a Blade es una **reimplementación** (Bloque T):

- **Escritorio (1120, 1280, 1366, 1440): 1:1 estricto**, verificado píxel a píxel con el arnés.
- **Tablet y móvil: el prototipo es referencia visual, no de comportamiento.** El resultado tiene que
  funcionar bien aunque el prototipo no lo haga, **incluido el panel de administración**. Criterio:
  un administrador tiene que poder aprobar una garantía desde el celular.
- Al adaptar se usan los mismos tokens, tipografías y espaciados. Una decisión visual de fondo que no
  se deduzca del prototipo se propone antes, no se inventa.
- No se inventan pantallas. Si falta una que el backlog pide, se anota y se pregunta.
- Si el diseño contradice el acta, **gana el acta**, y se avisa.

### Arnés de comparación visual (`tools/comparar/`)

```
php -S 127.0.0.1:8081 -t .                                 # prototipos (necesita internet)
php artisan serve                                          # aplicación
npm run comparar -- <pantalla>                             # DETALLE=1 para ver zonas y táctiles
```

Genera `tools/comparar/salida/<pantalla>/<variante>/<ancho>-{original,laravel,diff}.png` con el
porcentaje de diferencia por ancho. En anchos < 1120 comprueba además: sin scroll horizontal y áreas
táctiles ≥ 44 px. Usa el Chrome instalado (`channel: 'chrome'`). Una pantalla no se da por terminada
sin correr el arnés.

---

## 3. Reglas de negocio confirmadas por el cliente

Esto está cerrado. No lo reinterpretes.

### Acceso

- Las subastas son **visibles públicamente** (cualquiera puede mirar).
- Para **pujar** hacen falta tres cosas a la vez:
  1. Estar registrado
  2. Que un administrador de Colliers haya **aprobado la cuenta**
  3. Tener la **garantía aprobada** para ese remate

### Registro de postores

- Base de datos **nueva y vacía**. Sin integración con ningún sistema existente de Colliers.
- El usuario se registra desde la web → completa sus datos → solicita habilitación →
  Colliers valida y aprueba → queda habilitado.
- Se envía correo de rechazo cuando corresponde.
- ⚠️ **La lista exacta de campos todavía no la entregó el cliente.** Base provisional: los ~20 campos
  del diseño (persona natural/jurídica, documentos), con estructura extensible.

### Garantías

- **10% del valor mínimo** de la subasta. **Configurable** desde el panel (10% por defecto).
  El diseño muestra ~4%: manda el acta.
- Proceso **100% manual y externo a la plataforma**: vale a la vista o transferencia.
  **No hay pasarela de pago y no se debe implementar ninguna.**
- Un administrador marca manualmente al postor como «garantía aprobada».
- Estados: pendiente, en revisión, aprobada, rechazada.
- En caso de incumplimiento del ganador, pierde la garantía. Es un proceso legal fuera del
  sistema, no automatizado.

### Pujas

- **Moneda: CLP.** No UF. La UF del día se muestra solo como referencia visual.
- **Incremento mínimo: CLP 100.000**, configurable (valor global y por remate).
- Botones de puja rápida: **CLP 100k / 500k / 1M**.
- Campo «Otro» para monto libre, siempre respetando el mínimo.
- **Modal de confirmación obligatorio** antes de registrar cualquier puja.
- El postor ve si va ganando o si lo superaron.
- Actualización en tiempo real de las pujas nuevas.

### Cierre de la subasta

- **Temporizador fijo** (ej. 30 minutos).
- **NO HAY ANTI-SNIPING.** Una puja en el último segundo **no extiende** el tiempo.
  Esto está confirmado en mayúsculas en el acta. No lo implementes «por si acaso».
- **Validez de la puja por hora de recepción en el servidor.** Una puja recibida antes de T es
  válida aunque termine de procesarse después. La adjudicación se materializa en
  T + **margen de liquidación** (configurable, 2 s por defecto) para que terminen las peticiones en
  curso. No es anti-sniping: nadie puede pujar después de T.
- Un administrador puede cerrar anticipadamente.
- Estados del remate según el acta: **Puja → Adjudicado → Cerrado** (máquina de estados completa
  pendiente de revisión, ver §7).
- Al cerrar: el sistema declara ganador automáticamente y notifica al adjudicatario y al
  administrador.
- Si el remate no se concreta, **se crea una subasta nueva**. La misma no se reabre.

### Streaming

- Canal de YouTube **existente y dedicado** de Colliers, embebido por **iframe**.
- El identificador del video se carga por remate desde el panel de administración.
- ⚠️ YouTube Live tiene 10–30 s de retraso inherente. **El cronómetro y el precio de la
  plataforma son la fuente oficial, no el video.** Debe quedar evidente en pantalla.

### Reportes

- **Desempeño comercial:** precio base vs. final, % de sobreprecio, tasa de venta
- **Participación:** postores registrados, postores activos, pujas por remate
- **Dinámica:** tiempo de cierre por remate
- **Generales:** volumen total transado, desempeño por categoría de activo

Exportables y comparables entre eventos.

### Carga esperada

**Máximo 10 postores concurrentes** (correo de Christopher, infraestructura de Colliers; el acta
decía «mínimo 5»). **Los espectadores públicos no tienen tope**: toda decisión de tiempo real debe
aguantar muchos espectadores, no solo 10 postores.

---

## 4. Entorno — esto condiciona todo

El servidor **no es un VPS**. Es hosting compartido.

| | |
|---|---|
| Framework | **Laravel 13** (13.32 al iniciar) + **Fortify** (sin vistas propias; las vistas salen del diseño) |
| Frontend | Blade + CSS propio (sin Tailwind) + Alpine.js, compilado con Vite |
| Hosting | BanaHosting — cPanel / **LiteSpeed** (no Apache, no Nginx) |
| PHP | 8.4.24 |
| Composer | 2.10.2 |
| Base de datos | MariaDB |
| Local | Windows con Laragon |
| Sandbox | `rematescolliers.sandboxdelta.com` |
| Ruta en servidor | `/home/oywadfan/remates-colliers` |
| Document Root | apunta a `/home/oywadfan/remates-colliers/public` |
| Repositorio | `https://github.com/jonas549/remates-colliers` (público) |

### Restricciones duras del entorno

- **NO hay Supervisor.** No se pueden correr procesos persistentes.
- **Las colas van por cron**, con `queue:work --stop-when-empty`. No con un worker permanente.
- **Vite NO se compila en el servidor.** `public/build/` se commitea. Si tocas CSS o JS, compila y
  commitea el build. El hook `.githooks/pre-commit` lo verifica: si el commit toca `resources/css`,
  `resources/js`, `resources/fonts`, `vite.config.js` o `package*.json`, compila y rechaza el commit si
  `public/build` no coincide con lo preparado, o si hay cambios de assets sin preparar. Se activa con
  `git config core.hooksPath .githooks` (lo hace `npm install` con el script `prepare`).
- **El cron de deploy corre `migrate` pero no `db:seed`.** Lo que necesite datos sembrados va en un
  comando idempotente (`colliers:instalar`) que el script de deploy sí ejecuta.
- **LiteSpeed puede requerir forzar el handler de PHP vía `.htaccess`.** El bloque está en
  `public/.htaccess`, **comentado** hasta confirmar el nombre exacto del handler en cPanel.
- **Límites a medir antes de cerrar el Bloque J:** procesos simultáneos (EP), CPU,
  `max_execution_time`, frecuencia mínima de cron, HTTP saliente.

### Deploy

1. Push a `main` en GitHub
2. Un cron en el servidor corre cada 5 minutos `~/scripts/deploy-colliers.sh`
3. El script hace `git reset --hard origin/main`, `composer install`, `migrate --force` y limpia cachés
4. Log en `~/scripts/deploy-colliers.log`

**No hay GitHub Actions.** No los configures.

### Contrato de deploy (conectar el servidor una sola vez)

El servidor se configura **una vez**; desde ahí todo entra por push y cron. Para que siga siendo así:

- **`colliers:puede-desplegar`** se ejecuta antes de actualizar el código. Código de salida 75 = no
  desplegar (el script termina y reintenta en el próximo ciclo). Hoy bloquea con el archivo
  `storage/app/bloquear-deploy`; en el Bloque J se agrega el bloqueo con remate en curso.
- **`colliers:instalar`** se ejecuta después de `migrate`. Idempotente. **Todo paso de instalación futuro
  (configuración por defecto, catálogos, plantillas) se agrega a este comando, nunca como paso manual.**
- **Una sola línea de cron para tareas:** `schedule:run` cada minuto. Toda tarea periódica nueva va en
  `routes/console.php`, nunca como cron adicional. La cola se procesa ahí con `--stop-when-empty`.
- **`colliers:diagnostico`** revisa extensiones, entorno, base, permisos, assets y el latido del cron.
- **Clave de acceso al sandbox:** `COLLIERS_ACCESO_CLAVE` protege todo el sitio (incluido /admin).
- **Migraciones solo aditivas:** crear tablas, agregar columnas nullable o con valor por defecto, índices.
  Nunca renombrar ni eliminar columnas. Si algo cambia de significado, columna nueva y la antigua deprecada.
- `users` guarda credenciales y rol (admin, martillero, postor). Los datos del postor van en tablas propias.

**Bloqueo de deploy obligatorio:** el script no debe desplegar mientras haya un remate en curso
(pendiente de implementar; requiere cambio en el script del servidor).

### Flujo de trabajo

**Todo el desarrollo es local** (entorno y base de datos locales). Se commitea en local, pero
**no se hace push a `main` hasta que Jonas revise y apruebe explícitamente**: cada push lo
despliega el cron del servidor en 5 minutos.

Desarrollas en local → Jonas revisa en local (Laragon, `http://remates-colliers.test`) → Jonas
aprueba → push a `main` → el cron despliega → Jonas verifica en el sandbox.

### Registro vivo del avance: `docs/BACKLOG.md`

**Contrato de trabajo:** cada vez que se termina algo, se actualiza `docs/BACKLOG.md` **en el mismo
commit**: casilla de la tarea, conteo y estado en la tabla resumen, «Dónde vamos» y decisiones
pendientes. Jonas tiene que poder abrir ese archivo y saber dónde vamos sin preguntar.

- `[x]` solo con la tarea verificada ejecutándola (ver §6, «Sobre verificación»).
- Si `docs/BACKLOG.md` y los §8 o §10 de este archivo no coinciden, **manda `docs/BACKLOG.md`**.
- `docs/PROGRESO.md` es el punto de retome de la jornada (qué se hizo, qué sigue mañana); se actualiza al
  cerrar cada día.

---

## 5. El punto crítico del proyecto

**Dos personas pujando en el mismo segundo.**

- **Bloqueo sobre la fila del lote/remate** (`lockForUpdate`), que guarda precio actual, ganador y
  `cierra_en`. No sobre la última puja: en la primera puja no hay fila que bloquear.
  `DB::transaction` con reintentos ante deadlock.
- **MySQL es la fuente de verdad; el canal de tiempo real solo transmite.**
- **La tabla de pujas nunca se borra ni se edita.** Solo pujas aceptadas, con timestamp de servidor,
  IP y user agent.
- **Los intentos rechazados van en una tabla aparte, escrita FUERA de la transacción** (el rollback
  borraría el registro).
- **Cierre perezoso:** un lote está cerrado por definición cuando `ahora ≥ cierra_en`. La
  adjudicación la materializa de forma idempotente el primero que lo detecta (puja, endpoint de
  estado, carga de página o cron). El cron solo afecta la demora de las notificaciones.
- **Toda hora sale del servidor.** El cronómetro del navegador se sincroniza contra un endpoint.
- **Tiempo real: JSON estático servido por LiteSpeed.** En cada puja confirmada se escribe de forma
  atómica (temporal + rename) el estado del remate; los clientes lo consultan cada ~1 s sin ejecutar
  PHP. Capa de eventos **abstraída** para poder cambiar a Pusher sin reescribir. Se valida en el
  sandbox antes de darlo por bueno.
- **Emisión de eventos síncrona, nunca por la cola del cron.**
- **Pruebas de concurrencia con el Apache/Nginx de Laragon y MariaDB real.** Nunca con
  `artisan serve` (atiende una petición a la vez) ni SQLite.

**Este bloque no se marca como terminado sin pruebas de concurrencia automatizadas.**

---

## 6. Reglas técnicas

- **Todo valor de negocio configurable va a tabla, editable desde el panel.** Si hay que hacer un
  deploy para cambiar un número, está mal (ver Bloque V).
- **Fechas en UTC en la base de datos**, mostradas en `America/Santiago`. `APP_TIMEZONE` queda en UTC.
- **RUT cifrado con índice ciego** (hash para búsqueda y unicidad), resuelto en el Bloque C.
- Las migraciones deben ser reversibles: `down()` implementado siempre.
- **Nunca commitear `.env`.** El repositorio es público.
- **Nada de assets de terceros por CDN** (fuentes, librerías JS/CSS viajan en el repo).
  Excepciones aprobadas: iframe de YouTube, mosaicos de mapa de Esri (si sus condiciones comerciales
  lo impiden, OpenStreetMap).
- **Los montos se guardan como enteros en pesos**, nunca como flotantes.
- Commit por bloque o pantalla, con mensaje descriptivo.
- Si un cambio necesita que Jonas corra algo manualmente en el servidor, **avísalo explícitamente**.
- Interfaz y mensajes **en español**.
- **Decisiones reversibles y de bajo riesgo: se toman y se anotan en el reporte.** Se pregunta solo
  por reglas de negocio, cambios al modelo de datos o acciones irreversibles.

### Sobre verificación

- **`[x]` sólo con la funcionalidad probada ejecutándola**, no leyendo el código.
- Lo que no se pueda comprobar en local se deja sin marcar y se anota qué debe probar Jonas en el servidor.
- **Nada de agentes de QA.** Las pruebas finales las hace Jonas directamente.
- **No inventes funcionalidad.** Si algo no está definido, va a «Decisiones pendientes» y sigues.

---

## 7. Decisiones

### Tomadas (15/09)

- **Modelo siempre con lotes.** Un remate de una propiedad es un remate con un lote. Con un solo lote
  la interfaz no muestra navegación entre lotes y se ve como el diseño.
- Transporte de tiempo real: JSON estático (ver §5).
- Margen de liquidación configurable, 2 s por defecto.
- WordPress fuera del alcance.
- Frontend primero (Bloque T), luego lo riesgoso (J núcleo) lo antes posible.
- Mapa: OpenStreetMap con los mosaicos en escala de grises vía CSS (tono cercano al Esri Light Gray del
  prototipo, sin cuenta). CARTO Positron queda como alternativa si hiciera falta (requiere API key).
- **Alcance de funciones del diseño:**
  - DENTRO: suscripción «avísame», recordatorio antes del remate, calendario .ics, mapa, documentos
    descargables, recomendados, enlace al canal de Colliers.
  - FUERA (aplicado en T): favoritos (sin botón), agendar visita (enlace de contacto «Coordinar visita»),
    contador de «personas viendo» (quitado), lupa de la cabecera (lleva al buscador del listado).
  - A DECIDIR: idioma EN/ES (Bloque N) y exportar PDF (Bloque O); en T se ven pero no funcionan.
  - Filtros del listado: fecha, tipo, dormitorios, estacionamiento/bodega, rango de precio, región y
    comuna (región y comuna generadas desde los datos). Quitado «con visita programada». «Garantía
    requerida» queda OCULTO (código conservado, tramos calculados desde los datos); activable en el Bloque V.
- Fotos de muestra: las del prototipo eran de origen desconocido; reemplazadas por fotos Unsplash License
  (créditos en public/img/demo/CREDITOS.md). Las originales siguen en el historial de git del primer push.

### Pendientes con el cliente (no bloquean; se anotan y se sigue)

- Campos exactos del registro de postor.
- **Máquina de estados única** (propuesta enviada el 15/09, en revisión):
  remate `borrador → publicado → en_curso → finalizado | cancelado`;
  lote `programado → abierto → liquidando → adjudicado | desierto`, `adjudicado → cerrado | incumplido`;
  cuenta `registrado → en_revision → aprobado | rechazado`, `aprobado ↔ bloqueado`;
  garantía `pendiente → en_revision → aprobada | rechazada`, `aprobada → devuelta | imputada | ejecutada`.
- Cierre anticipado: ¿adjudica la mejor puja o anula el lote?
- Con varios lotes: ¿garantía por remate o por lote, y sobre qué base el 10%?
- Con varios lotes: ¿el siguiente abre al cierre efectivo del anterior + pausa, u horario fijo?
- ¿Se enmascara la identidad de los postores en el feed? (el diseño usa «Postor #N»)
- ¿Qué pasa con la garantía del perdedor? (el diseño afirma devolución; el acta no lo define)
- ¿2FA solo para administradores o también para postores?
- Lista exacta de gráficos y columnas de los reportes.

---

## 8. Backlog

> **El estado vivo está en `docs/BACKLOG.md`** (ver §4, «Registro vivo del avance»). Esta sección es la
> definición original; si no coincide, manda `docs/BACKLOG.md`.
>
> El Bloque A (entorno, servidor, deploy, cron, repositorio) **ya está completo**.

### BLOQUE T — Traspaso del diseño a Blade (1:1 en escritorio)

- [x] B0: Laravel 13, Vite + Alpine, fuentes locales, `.gitignore`, `.htaccess` (handler comentado)
- [x] Arnés de comparación visual y de usabilidad móvil
- [x] Login
- [x] Registro de postor (corregido el `grid-column: span 2` que desbordaba en pantallas angostas)
- [x] Estado de cuenta (5 variantes)
- [x] Listado de remates (index), filtros verificados con `tools/comparar/filtros-interaccion.mjs`
- [x] Detalle de remate próximo
- [x] Detalle de remate en vivo
- [x] Sala de puja (móvil: barra fija inferior + hoja de puja)
- [x] Admin: dashboard, subastas, postores, reportes (móvil: barra superior + menú deslizable,
      tarjetas en postores/pendientes, tablas con scroll y primera columna fija, pestañas con scroll)
- [x] Recorrido «aprobar garantía» a 375 px automatizado
- [x] Raíz invertida: `/` es el listado, `/remates` redirige; índice de revisión en `/revision` solo en local

### BLOQUE B — Base del proyecto Laravel

- [x] Comandos `colliers:instalar`, `colliers:puede-desplegar`, `colliers:diagnostico` (con pruebas)
- [x] Programador de tareas: latido y cola por minuto con `--stop-when-empty`
- [x] Clave de acceso al sandbox (middleware, formulario, límite de intentos, noindex)
- [x] Páginas de error propias: 403, 404, 419, 429, 500, 503
- [x] Traducciones al español en `lang/es/` (validación, autenticación, contraseñas, paginación)
- [x] `.env.example` completo y sin secretos
- [x] `users` con rol, estado y cambio de clave obligatorio (migración aditiva)

- [ ] `.env`: base de datos, correo, locale `es` (hecho en T), zona de visualización `America/Santiago`
- [ ] Fortify, en español
- [ ] `maatwebsite/excel` para exportaciones
- [ ] Confirmar y activar el handler de PHP 8.4 en `.htaccess`

### BLOQUE C — Modelo de datos

- [ ] `users` — rol, RUT cifrado + índice ciego, teléfono, estado de validación, datos extensibles
- [ ] `remates` — nombre, fecha, estado, identificador de streaming, incremento propio (opcional)
- [ ] `lotes` — remate, datos del activo, precio base, orden, estado, `cierra_en`, precio actual, ganador
- [ ] `lote_imagenes`, documentos, visitas
- [ ] `garantias` — postor, remate, monto, estado, comprobante, quién aprobó y cuándo
- [ ] `pujas` — lote, postor, monto, timestamp de servidor, IP, user agent (solo crece)
- [ ] `puja_intentos` — intentos rechazados con motivo
- [ ] `adjudicaciones` — lote, ganador, monto final, fecha de cierre
- [ ] `access_logs`, `configuraciones`, `notificaciones_log`
- [ ] Todas las fechas en UTC; todas las migraciones con `down()`
- [ ] Seeders de desarrollo con datos realistas

### BLOQUE J — Motor de subastas en tiempo real ⚠️ (núcleo antes que D)

**Leer la sección 5 antes de empezar.**

#### Validación de la puja
- [ ] Endpoint con transacción y `lockForUpdate` sobre la fila del lote
- [ ] Remate en curso, lote abierto, postor aprobado, garantía aprobada
- [ ] Monto ≥ actual + incremento; el postor no es quien va ganando
- [ ] Validez por hora de recepción
- [ ] Rate limiting sobre el endpoint
- [ ] Intentos rechazados registrados fuera de la transacción

#### Temporizador y cierre
- [ ] Endpoint de sincronización de reloj
- [ ] Cierre perezoso idempotente + margen de liquidación configurable
- [ ] Adjudicación automática; lote desierto
- [ ] Paso al siguiente lote (cuando haya varios)
- [ ] Cierre manual de emergencia desde el panel del martillero

#### Difusión en tiempo real
- [ ] Interfaz de emisión abstraída (implementación: JSON estático; alternativa: Pusher)
- [ ] Escritura atómica del estado por remate; cabeceras sin caché en LiteSpeed
- [ ] Eventos: puja nueva, cierre de lote y apertura del siguiente, mensaje del martillero
- [ ] Reconexión recuperando el estado actual

#### QA obligatorio del bloque
- [ ] Medición de límites del sandbox
- [ ] Dos pujas del mismo monto en el mismo instante: sólo una gana
- [ ] Puja bajo el incremento / sin garantía / después del cierre: rechazadas
- [ ] Vaciar la caché a mitad del remate no altera el estado
- [ ] El ganador registrado coincide con la última puja válida
- [ ] Simulación de 20 postores en paralelo (Laragon Apache/Nginx + MariaDB)
- [ ] Prueba del transporte en el sandbox con espectadores simulados
- [ ] Bloqueo de deploy con remate en curso

### BLOQUE D — Autenticación y registro de postores

- [ ] Registro de postor; validación de RUT (formato y dígito verificador)
- [ ] Verificación de correo
- [ ] Login de postor y login de administrador, separados
- [ ] Recuperación y cambio de contraseña (propia y de otros desde el admin, cerrando sesiones)
- [ ] Registro de accesos; bloqueo tras intentos fallidos **con el contador en tabla, no en caché**
- [ ] Sesiones activas y cierre remoto
- [ ] Middleware de rol y policies; ninguna consulta por id sin filtrar por dueño

### BLOQUE K — Sala de puja conectada al motor real
### BLOQUE I — CRUD de remates y lotes, incluido el panel del martillero

### BLOQUE V — Configuración autoadministrable

- [ ] Incremento mínimo (global y por remate)
- [ ] Porcentaje de garantía (10% por defecto)
- [ ] Margen de liquidación (2 s por defecto)
- [ ] Intentos de login y duración del bloqueo
- [ ] Plazos: cierre de garantías antes del remate, tiempo de revisión
- [ ] Datos bancarios para garantías
- [ ] Fuente y valor de la UF
- [ ] Textos legales y enlaces
- [ ] SMTP completo con botón de prueba de envío

### Resto de bloques

G (postores), H (garantías), M (notificaciones), N (sitio público), L (streaming),
E/F (componentes y estructura del panel, a medida que las pantallas lo pidan), O (reportes),
P (seguridad), Q (QA y carga), R (producción), S (documentación). Su detalle se mantiene del
backlog original y se completa al llegar a cada uno.

---

## 9. Orden de ejecución

```
T → B → C → J(núcleo) → D → K → I → V → G → H → M → N → L → E/F → O → P → Q → R → S
```

Primero lo visible para mostrarlo al cliente; después lo riesgoso (J) lo más temprano posible.

---

## 10. Registro de avance

> Resumen histórico. El estado al día está en `docs/BACKLOG.md`.

| Bloque | Estado | Fecha | Notas |
|---|---|---|---|
| A — Entorno y servidor | **Cerrado** | 2026-09-15 | Hecho por Jonas. PHP 8.4.24, Composer 2.10.2, BD, deploy key, script de deploy y cron cada 5 min. |
| T — Diseño a Blade | **Cerrado** | 2026-09-15 | 11 pantallas 1:1 en escritorio; diferencias restantes son decisiones de alcance (favoritos, contador, filtros). Móvil y tablet sin desborde ni táctiles < 44 px. |
| B — Base Laravel | Infra lista | 2026-09-15 | Contrato de deploy, comandos, cron, clave de sandbox, errores y es. Pendiente: Fortify (D), maatwebsite/excel (O). |
| C — Modelo de datos | Pendiente | | |
| J — Motor de subastas | Pendiente | | |
| D — Autenticación | Pendiente | | |
| K — Interfaz de puja | Pendiente | | |
| I — Remates y lotes | Pendiente | | |
| V — Configuración | Pendiente | | |
| G — Postores | Pendiente | | |
| H — Garantías | Pendiente | | |
| M — Notificaciones | Pendiente | | |
| N — Sitio público | Pendiente | | |
| L — Streaming | Pendiente | | |
| E/F — Componentes y panel | Pendiente | | |
| O — Reportes | Pendiente | | |
| P — Seguridad | Pendiente | | |
| Q — QA y carga | Pendiente | | |
| R — Despliegue | Pendiente | | |
| S — Documentación | Pendiente | | |
