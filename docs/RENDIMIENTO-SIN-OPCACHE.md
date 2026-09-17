# Rendimiento sin OPcache

> 17/09/2026. OPcache está **apagado** en el sandbox: la extensión no está cargada a nivel de servidor y no se activa
> desde cPanel. El hosting de producción de Colliers puede tener la misma limitación. Este documento mide el efecto,
> explica qué se cambió en el código y qué queda como riesgo del Bloque R.

## 1. Qué hace OPcache y por qué importa aquí

PHP compila cada archivo en cada petición. OPcache guarda el resultado en memoria compartida y lo reutiliza. Sin él,
**cada petición a Laravel compila ~6,4 MB de PHP en 525 archivos**, aunque la respuesta sea un número:

| Paquete | KB compilados por petición |
|---|---|
| `laravel/framework` | 2.206 |
| `nesbot/carbon` (fechas; el kernel HTTP de Laravel lo carga siempre) | 1.998 |
| `vendor/composer` (mapa de clases del autoloader) | 1.186 |
| `symfony/http-foundation` | 198 |
| `phpunit` + `var-dumper` (solo si el servidor tiene las dependencias de desarrollo) | 198 |

Medido con `tools/rendimiento/archivos.php`: ~350–570 ms de CPU por petición en esta máquina.

## 2. Mediciones

Herramienta: `tools/rendimiento/medir.php` contra el Apache de Laragon (mod_php) con MySQL 8.4, 10 postores distintos.
Para simular el hosting compartido, Apache se fija a 1 o 2 núcleos (`CONCURRENCIA_NUCLEOS`) y OPcache se apaga
(`CONCURRENCIA_OPCACHE=0`). Tiempos medidos desde el cliente, en milisegundos (mediana / p95).

| Escenario | Con OPcache · 1 núcleo | Sin OPcache · 1 núcleo · **antes** | Sin OPcache · 1 núcleo · **después** | Sin OPcache · 2 núcleos · después |
|---|---|---|---|---|
| Una puja sola | 174 / 226 | 752 / 1.450 | 728 / 757 | 762 / 1.010 |
| **10 pujas simultáneas** | 670 / 930 | 5.682 / 12.726 | 3.964 / 4.795 | 2.802 / 3.486 |
| Hora de recepción sellada tras el envío (10 simultáneas) | — | **4.543 / 7.262** | **155 / 239** | 28 / 203 |
| Sincronización de reloj, 10 simultáneas | 361 / 459 (`/hora`) | 4.015 / 6.820 (`/hora`) | **15 / 36** (`/hora.php`) | 13 / 37 (`/hora.php`) |
| Estado del remate (PHP), 10 simultáneas | 470 / 642 | 3.899 / 4.356 | 4.392 / 5.316 | 3.135 / 3.599 |

Entre corridas hay bastante variación (Windows, otros procesos): lo concluyente son los órdenes de magnitud.

Pruebas de concurrencia (`tools/concurrencia/prueba.php 10`) **sin OPcache y con 1 núcleo**, después de los cambios:
todas las invariantes en verde (una sola puja gana por ronda, secuencia válida, ganador = última puja, una sola
adjudicación). El escenario nuevo **D** (10 pujas 400 ms antes de T y 10 liquidaciones en T + 2 s, al mismo tiempo)
pasa: 0 pujas selladas después de T y 0 pujas válidas perdidas. La única comprobación que no concluye es la de C
«antes de T + margen no se adjudica»: con respuestas de 4–6 s el script no alcanza a consultar antes del margen, y
lo informa como NO CONCLUYENTE en vez de dar un falso OK.

## 3. Respuesta 1 · ¿Qué tan grave es con 10 postores?

**Antes de los cambios era inaceptable, y no solo por lentitud.** La hora de recepción se tomaba en un middleware,
después de arrancar Laravel. Con 10 pujas simultáneas en 1 núcleo sin OPcache, la hora quedaba sellada hasta **7,3 s
después** de que el postor apretó «Confirmar». Una puja enviada 5 segundos antes del cierre podía rechazarse como
tardía. Eso rompe la regla del acta (validez por hora de recepción).

**Después de los cambios:**

- **Validez: resuelta.** La hora se sella en ~0,2 s aunque la respuesta tarde 4–5 s. Nadie pierde una puja por la
  lentitud del servidor.
- **Experiencia: mala en el peor caso, tolerable en el caso normal.**
  - Una puja sola tarda ~0,75 s en confirmarse (con OPcache, 0,17 s). Tolerable.
  - Si los 10 postores pujan **en el mismo segundo** con 1 núcleo, cada uno espera ~4 s (p95 ~5 s) viendo «Enviando…».
    Con 2 núcleos, ~3 s. Es malo justo en los segundos finales, que es cuando más importa.
  - Los espectadores no se ven afectados: leen un JSON estático (sin PHP) y sincronizan el reloj con `/hora.php`.
- **Riesgo que queda: el margen de liquidación.** Una puja recibida antes de T tiene que llegar al bloqueo del lote
  antes de que alguien liquide en T + margen (2 s por defecto). En la prueba D alcanzó con 2 s, pero con respuestas de
  4–6 s no hay holgura garantizada. **Recomendación:** en un servidor sin OPcache, subir el margen a **5 s** desde el
  panel (Bloque V) y confirmarlo con la medición del sandbox. No es anti-sniping: nadie puede pujar después de T; solo
  se espera más para adjudicar.

**Veredicto:** con los cambios es **aceptable para operar** (las reglas se cumplen), pero **no es lo que se le debe
comprometer al cliente** como experiencia en los segundos finales si el servidor tiene 1 núcleo. Depende de cuántos
núcleos entregue realmente el hosting, y eso se mide en el sandbox (§6).

## 4. Respuesta 2 · Optimizar la puja sin OPcache

### Hecho

1. **Hora de recepción desde `REQUEST_TIME_FLOAT`** (`app/Http/Middleware/HoraRecepcion.php`). PHP fija ese valor al
   recibir la petición, antes de compilar nada; el cliente no puede alterarlo. Si falta o es absurdo (futuro o más de
   120 s atrás) se usa la hora actual. Es el cambio de mayor impacto: de 4,5 s a 0,15 s de sello.
2. **`public/hora.php` sin framework.** La sincronización del reloj ya no arranca Laravel: 15 ms con 10 simultáneas
   contra 4 s. La sala la usa; la ruta `/hora` de Laravel queda de respaldo. No pasa por la clave del sandbox (no
   expone nada, igual que el JSON de estado).
3. **Menos consultas por puja:** `Configuracion::valor()` recuerda los valores 2 s (una puja leía la misma clave 3–4
   veces; guardar desde el panel vacía la memoria) y `PujaController` arma el resumen con una sola lectura del alias.
4. **Escenario D** en las pruebas de concurrencia y comprobación «ninguna puja recibida antes de T se perdió» en C y D.

### Evaluado y descartado: una ruta de puja que no arranque el framework

Medido con un prototipo (`Capsule` de Eloquent + Carbon + una consulta con bloqueo, sin kernel HTTP, sesión ni router):
**4 MB y ~250–300 ms**, contra 6,4 MB y ~400 ms de Laravel completo. Es un **30 %**, no un cambio de orden: Composer,
Carbon y Eloquent siguen siendo la mayor parte.

Un cambio de orden (~5 ms) exige PHP puro con PDO, sin Composer. Eso significa **reimplementar fuera de Laravel** el
descifrado de la cookie de sesión, la verificación CSRF, el límite por minuto, el bloqueo del lote, todas las
validaciones de la puja, el registro de intentos fuera de la transacción y la publicación del JSON. Quedarían **dos
implementaciones del punto crítico del proyecto** que hay que mantener idénticas. Con 10 postores comprometidos y la
validez ya resuelta por el sello de hora, **no conviene**. Se reconsidera solo si la medición del sandbox muestra 1
núcleo y el cliente exige confirmaciones rápidas con 10 pujas en el mismo segundo.

## 5. Respuesta 3 · Cachés de Laravel y archivos por petición

| Medida | Efecto sin OPcache | Estado |
|---|---|---|
| `config:cache` | No lee `.env` ni 15 archivos de configuración | Ya activo: el deploy corre `php artisan optimize` |
| `route:cache` | No registra rutas (Fortify incluido) en cada petición | Ya activo (`optimize`) |
| `event:cache` | No descubre listeners | Ya activo (`optimize`) |
| `view:cache` | Blade ya compilado; **igual se compila el PHP resultante** en cada petición | Ya activo (`optimize`); no ayuda a la puja (JSON) |
| `composer install --no-dev` | ~200 KB menos por petición (phpunit, var-dumper) y mapa de clases más chico | **Verificar el script de deploy.** `colliers:diagnostico` ahora avisa si hay dependencias de desarrollo |
| Mapa de clases (`optimize-autoloader`) | El mapa pesa 1,2 MB y se compila en cada petición (~38 ms aquí). Sin él, Composer busca cada clase en disco: en Linux es barato, en Windows no | Sin cambiar: se decide midiendo en el sandbox, no en Windows |
| Precarga (`opcache.preload`) | — | Imposible: requiere OPcache |

`colliers:diagnostico` revisa ahora que configuración, rutas y eventos estén en caché y que no haya dependencias de
desarrollo en el servidor.

Conclusión: las cachés ya estaban aplicadas y son necesarias, pero **no atacan el costo principal**, que es compilar el
framework en cada petición. Por eso lo que más rinde es **no ejecutar PHP** cuando no hace falta (JSON estático,
`hora.php`) y **sellar la hora antes** de ese costo.

## 6. Respuesta 4 · Alternativas que dependen solo de nosotros

1. **Hecho:** sello con `REQUEST_TIME_FLOAT`, `hora.php`, menos consultas, escenario D.
2. **Espectadores sin PHP.** El detalle en vivo del sitio público (Bloque N) consulta solo el JSON estático; nunca llama
   al endpoint de estado. Así la cantidad de espectadores no compite por CPU con las pujas.
3. **Margen de liquidación desde el panel** (Bloque V): subirlo a 5 s si el servidor no tiene OPcache.
4. **Medir el servidor real sin tocar datos:** `php tools/sandbox/medir-servidor.php https://rematescolliers.sandboxdelta.com`
   desde cualquier PC con PHP. Compara estático, `hora.php` y Laravel, y estima los núcleos efectivos.
5. **Ver el estado de OPcache de la web desde el panel** (Administración → Configuración → Sistema, Bloque V). La consola
   y la web pueden usar PHP distintos: `php -m` en la terminal no dice nada de la web.
6. **Revisar en cPanel si existe «Select PHP Version» (Selector de PHP de CloudLinux).** Si existe, OPcache es una casilla
   que se marca en la propia cuenta, sin soporte. Hoy el sitio usa el handler `ea-php84` (EasyApache), donde las
   extensiones son del servidor; cambiar a `alt-php84` requiere cambiar el handler en `public/.htaccess` (versionado) y
   probarlo en el sandbox antes.
7. **`composer install --no-dev`** en el script de deploy (si no lo tiene).

Lo que **no** depende de nosotros y no se puede resolver por código: activar OPcache a nivel de servidor, o más núcleos.

## 7. Riesgo para el Bloque R

**Verificar OPcache en el hosting de producción antes de comprometer rendimiento.** Si está apagado: medir con
`tools/sandbox/medir-servidor.php`, fijar el margen de liquidación según la latencia de 10 pujas simultáneas y avisar al
cliente de que la confirmación en los segundos finales puede tardar varios segundos (sin afectar la validez).
Anotado en `docs/BACKLOG.md`, Bloque R.

## 8. Cómo repetir las mediciones

```
# MySQL/MariaDB de Laragon encendido
CONCURRENCIA_OPCACHE=0 CONCURRENCIA_NUCLEOS=1 php tools/concurrencia/servidor.php   # en otra terminal
php tools/rendimiento/medir.php 10 5
php tools/concurrencia/prueba.php 10
CONCURRENCIA_MARGEN=5 php tools/concurrencia/prueba.php 10                          # con otro margen
```
