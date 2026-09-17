# Manual de pruebas · Remates Colliers (Bloques K a N)

> Escrito el 17/09/2026 para probar en el **sandbox** `https://rematescolliers.sandboxdelta.com`.
> Todo lo que dice «terminal» es la terminal de cPanel, dentro de `/home/oywadfan/remates-colliers`.
> Si `php -v` no muestra 8.4, reemplaza `php` por `/opt/cpanel/ea-php84/root/usr/bin/php` en todos los comandos.

---

## 0. Qué necesitas

| Qué | Dónde |
|---|---|
| Clave de acceso al sandbox | `COLLIERS_ACCESO_CLAVE` del `.env` del servidor. La pide la primera vez en cada navegador. |
| Administrador | El correo de `COLLIERS_ADMIN_EMAIL`. Ingreso: `https://rematescolliers.sandboxdelta.com/admin/ingresar` |
| Martillero | Se crea en el paso 1.5 (publicar un remate exige martillero). |
| Postores | Los creas tú en el paso 4 (registro real) o con el comando del paso 7 (postores de demostración). |
| Navegadores | Uno normal (administrador), una ventana privada por postor y un celular. Cada ventana privada es una sesión distinta. |

Direcciones que se usan en todo el manual (reemplaza `{slug}` e `{id}` por los del remate):

| Pantalla | URL |
|---|---|
| Sitio público (listado) | `/` |
| Detalle de un remate | `/remates/{slug}` |
| Sala de pujas | `/remates/{slug}/sala` |
| Estado en tiempo real (JSON) | `/tiempo-real/{slug}.json` |
| Hora del servidor | `/hora.php` |
| Registro de postor | `/registro` |
| Ingreso de postor | `/ingresar` |
| Mi cuenta (postor) | `/mi-cuenta` |
| Panel | `/admin` |
| Subastas | `/admin/subastas` |
| Ficha de un remate | `/admin/subastas/{id}` |
| Panel del martillero | `/admin/subastas/{id}/en-vivo` |
| Postores y garantías | `/admin/postores` |
| Configuración | `/admin/configuracion` |

---

## 1. Preparar el servidor (una vez, después del push)

1. Esperar el deploy (cron cada 5 min) y revisar el registro:
   ```
   tail -n 30 ~/scripts/deploy-colliers.log
   ```
   Debe terminar sin errores y mostrar las migraciones `2026_09_17_100001_agregar_gestion_de_remates` y `2026_09_17_200001_crear_suscripciones` en `DONE`.
2. Diagnóstico:
   ```
   cd ~/remates-colliers && php artisan colliers:diagnostico
   ```
   Esperado: «Sin errores críticos». Anota si avisa «dependencias de desarrollo» (ver paso 4).
3. Confirmar el entorno:
   ```
   grep ^APP_ENV .env
   ```
   Debe decir `APP_ENV=staging`. Si lo cambias: `php artisan optimize`.
4. Revisar cómo instala Composer el deploy:
   ```
   grep -n composer ~/scripts/deploy-colliers.sh
   ```
   Lo ideal es `composer install --no-dev --optimize-autoloader`. Sin `--no-dev`, cada petición compila ~200 KB de más (no rompe nada).
5. Crear el martillero:
   ```
   php artisan colliers:crear-usuario martillero martillero@colliers.test M. Ossandón
   ```
   Copia la **clave temporal** que muestra (solo aparece una vez).
6. Medir el servidor desde tu PC (no toca datos; necesitas PHP en el PC y el repositorio):
   ```
   php tools/sandbox/medir-servidor.php https://rematescolliers.sandboxdelta.com
   ```
   Anota «Laravel por petición» y «Núcleos efectivos». Guíate por `docs/RENDIMIENTO-SIN-OPCACHE.md`.

## 2. Configuración inicial (panel)

1. Entra a `/admin/ingresar` con el administrador (si es el primer ingreso, pedirá cambiar la clave).
2. Menú **05 Configuración** (`/admin/configuracion`):
   - **Margen de liquidación:** `5` (OPcache está apagado; ver `docs/RENDIMIENTO-SIN-OPCACHE.md` §3).
   - **Envío de correos:** `No enviar: dejar en el registro (pruebas)` si todavía no tienes el SMTP; o `SMTP con los datos de abajo` con los datos reales.
   - **Datos para constituir la garantía:** llena banco, número de cuenta y titular con datos de prueba (si quedan vacíos, el postor ve «escríbenos para recibir los datos»).
   - **Fuente del valor (UF):** deja «Automática».
3. **Guardar configuración** → debe aparecer «Configuración guardada».
4. En la misma página:
   - **Unidad de fomento → Actualizar la UF ahora.** Si aparece un valor, el servidor puede salir a Internet. Si da error, anótalo (el sitio funciona igual, sin UF).
   - **Probar el correo saliente → Enviar correo de prueba.** Con modo registro dice «registrado en storage/logs»; con SMTP debe llegarte el correo.
   - **Sistema:** anota «OPcache (web)», «Cron» (último latido < 3 min) y «Cola de correos».

## 3. Cargar un remate de prueba

1. `/admin/subastas` → **Crear subasta**.
2. Completa:
   - Dirección `Av. Prueba 123, Depto. 45`, Comuna `Providencia`, Región `Región Metropolitana`, Tipo `Departamento`, Superficie `80`.
   - Precio base `100000000`. Incremento y garantía vacíos (usan los globales: 100.000 y 10 %).
   - **Inicio del remate:** hoy, **dentro de 60 minutos** (hora de Chile): da tiempo para registrar, aprobar e inscribir postores (pasos 4 y 5).
   - **Cierre de garantías:** hoy, **dentro de 50 minutos** (si lo dejas vacío queda 48 h antes del inicio, ya vencido, y nadie podría inscribirse).
   - Martillero `M. Ossandón`. Video: el ID de un video de YouTube cualquiera (por ejemplo `jfKfPfyJRdk`).
3. **Publicar subasta** → te lleva a la ficha con «Remate R-2026-00N publicado» y el estado **PRÓXIMA**.
4. En la ficha, **Datos y condiciones → Duración de cada lote (min):** `8` → **Guardar datos** (así no esperas 30 minutos). La tabla de lotes muestra abre y cierra con 8 minutos de diferencia.
5. (Opcional) **Reordenar:** en la ficha, **Agregar lote →** con otra dirección y precio base `50000000` → **Crear lote**. Vuelve a la ficha y en la fila del lote 2 pulsa **↑ Subir**: debe decir «Orden de los lotes actualizado; horarios reprogramados.», el lote movido queda 1.º y abre a la hora de inicio. (Si no quieres dos lotes en la prueba de pujas, carga este segundo lote en otro remate.)
6. (Opcional) En **Lotes → Editar**: sube 2 fotos, agrega latitud `-33.4262` y longitud `-70.6116`, un horario de visita y, en la ficha, un documento PDF público.
7. Abre `/` en una ventana privada: el remate aparece como PRÓXIMO con cuenta regresiva. Abre su detalle: ficha, fotos, mapa (si pusiste coordenadas), documentos y «Agregar a mi calendario» (descarga un `.ics`).

## 4. Registrar un postor y aprobarlo

1. Ventana privada → `/registro`. Persona natural, nombres y apellidos, RUT `12.345.678-5` (válido), correo (real o de prueba),
   teléfono `+56 9 1234 5678`, dirección, comuna y región, clave de al menos 8 caracteres, los tres documentos obligatorios
   (cédula frente, cédula dorso y comprobante de domicilio: cualquier JPG, PNG o PDF de menos de 5 MB) y acepta los términos. **Enviar**.
2. Confirmar el correo:
   - Con SMTP: abre el enlace del correo «Confirma tu correo».
   - Sin SMTP (modo registro), genera el enlace en la terminal (cambia el correo):
     ```
     php artisan tinker --execute="\$u=App\Models\User::where('email','postor1@correo.test')->first(); echo URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id'=>\$u->id,'hash'=>sha1(\$u->getEmailForVerification())]).PHP_EOL;"
     ```
     Pega esa dirección en la **misma** ventana privada donde te registraste.
3. `/mi-cuenta` debe mostrar **CUENTA EN REVISIÓN**.
4. Como administrador: `/admin/postores` → pestaña **CUENTAS POR APROBAR** → en la fila del postor, **Aprobar cuenta**. Aviso: «Cuenta aprobada. El postor recibirá un correo.»
5. Recarga `/mi-cuenta` del postor: **Tu cuenta está aprobada** y la lista de remates abiertos con **Inscribirme**.
6. Para probar el rechazo: registra otro postor (RUT `11.111.111-1`), confírmale el correo igual que en el paso 2, y en Postores usa **Rechazar** con un motivo. Ese postor ve el motivo en `/mi-cuenta` y recibe el correo.

## 5. Aprobar una garantía

1. Postor: abre el detalle del remate del paso 3 → **Constituir la garantía** (o **Inscribirme** en `/mi-cuenta`). Llega a «Quedaste inscrito… La garantía es de $10.000.000».
2. **Subir comprobante** → medio `Transferencia` → cualquier PDF → **Enviar comprobante**. Estado: **GARANTÍA EN REVISIÓN**.
3. Administrador: `/admin/postores` → pestaña **EN REVISIÓN** → **Ficha** (debe aparecer el enlace «Comprobante de garantía», descárgalo) → **Aprobar garantía**.
4. Desde el **celular**: repite con otro postor y aprueba desde la tarjeta («Aprobar garantía» ocupa el ancho del botón). Criterio del proyecto: se tiene que poder hacer cómodamente.
5. Postor: `/mi-cuenta` → **Estás habilitado para pujar** y el botón **Entrar a la sala de pujas**.
6. Rechazo: con otro postor, **Rechazar** con motivo «El monto no coincide» → el postor ve el motivo y puede volver a subir el comprobante (hasta el cierre de garantías).

## 6. Hacer una puja, del inicio a la adjudicación

1. Postor habilitado → `/remates/{slug}/sala` antes del inicio: dice **PRÓXIMO · PUJA AÚN NO ABRE** y cuenta hacia la apertura.
2. Al llegar la hora: **EN VIVO · PUJA ABIERTA**, contador hacia el cierre.
3. **Puja rápida + $100k** (o monto libre) → **Pujar $…** → se abre el modal **Confirma tu puja** (obligatorio) → **Confirmar puja**. Debe quedar **Vas ganando** y la puja arriba del historial como «Postor #1 (tú)».
4. Monto bajo el mínimo en «Monto libre»: el botón se deshabilita y la ayuda se pone en rojo.
5. Administrador → `/admin/subastas/{id}/en-vivo` (o **Subastas → Panel en vivo**): mismo precio, el ganador con su **nombre real** y el historial.
6. Mensaje a la sala: escribe «Quedan pocos minutos» → **Publicar mensaje**. En la sala aparece en la franja amarilla sin recargar.
7. Deja que el tiempo termine. En la sala: al llegar a cero desaparece el formulario, dice **CERRADO · ADJUDICANDO** y, pasado el margen (5 s), **Te adjudicaste la propiedad**.
8. Cierre anticipado (en otro remate): en el panel en vivo **Cerrar este lote ahora** con motivo → pasado el margen queda **ADJUDICADO** con la mejor puja.

## 7. Probar con varios postores a la vez

**Opción rápida (postores de demostración):**

1. En la terminal:
   ```
   php artisan colliers:remate-demo --postores=5 --inicio=3 --duracion=10
   ```
   Muestra el enlace del JSON, la **clave común** de los postores y la tabla con sus correos. El remate de demostración **no aparece en el listado** ni envía correos; su URL es `/remates/demo-…` (el slug está en la salida).
2. Abre `/ingresar` en 3 navegadores distintos (normal, privada de otro navegador y celular) con 3 correos de la tabla y la misma clave. En cada uno, `/remates/{slug}/sala`.
3. Pujan por turnos y también **al mismo tiempo** (cuenta regresiva en voz alta y confirmen juntos). Esperado:
   - Todos ven el precio nuevo en ~1 segundo sin recargar.
   - Si dos confirman el mismo monto, **solo una** puja entra; la otra recibe «El monto es menor que la puja mínima.»
   - Quien va ganando no puede volver a pujar («Tienes la puja más alta»).
4. Espectador sin sesión: abre `/remates/{slug}` en otra ventana privada. Ve el precio y el historial actualizarse cada ~2 s.
5. Corte de red: en un celular, activa el modo avión 10 s y vuelve: la sala recupera el estado actual sola.
6. Tiempo de confirmación: anota cuánto tarda «Enviando…» cuando confirman juntos. Sin OPcache es esperable 1–5 s; lo importante es que ninguna puja confirmada antes del cierre se pierda.

## 8. Verificar que el cierre y la adjudicación funcionaron

1. JSON público: `https://rematescolliers.sandboxdelta.com/tiempo-real/{slug}.json` → el lote en `"estado":"adjudicado"`, `"ganador":"Postor #N"`, `"precio_actual"` igual a la última puja.
2. Ficha del remate (`/admin/subastas/{id}`): lote **ADJUDICADO** con el precio final; si fue anticipado, «Cierre anticipado: motivo».
3. Dashboard (`/admin`): en «Actividad reciente» aparece «R-… adjudicado en $…» o «… cerró anticipadamente …».
4. Correos (solo remates que no son de demostración): el ganador recibe «Te adjudicaste la propiedad» y la administración «Lote adjudicado en R-…». Se envían con el cron del minuto siguiente. Revisar en la terminal:
   ```
   php artisan tinker --execute="App\Models\NotificacionLog::latest('id')->take(5)->get(['tipo','estado','destinatario','asunto','error'])->each(fn(\$n)=>print(json_encode(\$n).PHP_EOL));"
   ```
5. Base de datos (ganador = última puja):
   ```
   php artisan tinker --execute="\$a=App\Models\Adjudicacion::latest('id')->first(); \$p=App\Models\Puja::where('lote_id',\$a->lote_id)->orderByDesc('id')->first(); echo \$a->monto.' '.\$a->user_id.' | última puja '.\$p->monto.' '.\$p->user_id.PHP_EOL;"
   ```
   Los dos pares de números deben ser iguales.
6. Sin anti-sniping: una puja enviada 2–3 segundos antes del cierre entra, y la hora de cierre **no cambia**.
7. Después del cierre: una puja enviada desde otra pestaña que quedó abierta es rechazada con «El lote ya cerró».

## 9. Qué revisar en cada pantalla

| Pantalla | Qué debería ver |
|---|---|
| `/` | Remates publicados reales (sin borradores, cancelados ni demostraciones), UF del día, hero con el próximo remate, filtros que cambian el conteo, «Suscribirme» confirma con un mensaje. |
| `/remates/{slug}` próximo | Cuenta regresiva al inicio, precio base, garantía (10 %), incremento, cierre de garantías, ficha, mapa, visitas, documentos (los reservados dicen «Con garantía aprobada»), recomendados. El recuadro cambia según quién mira: visitante / falta garantía / en revisión / habilitado. |
| `/remates/{slug}` en vivo | Video, «REMATE EN CURSO», precio y historial que se actualizan solos, «Postor #N» sin nombres. |
| `/remates/{slug}` cerrado | «REMATE CERRADO» y el resultado. |
| `/remates/{slug}/sala` | Aviso «Precio y cronómetro oficiales: el video tiene 10–30 s de retraso», puja rápida 100k / 500k / 1M, modal obligatorio, «Vas ganando / Te superaron». En celular: barra fija abajo con «Pujar». |
| `/mi-cuenta` | Banda de color con el estado, 4 pasos, monto y plazo de la garantía, datos bancarios, subir comprobante, acceso a la sala cuando está aprobada. |
| `/admin` | KPIs reales, subastas en curso y próximas, pendientes y actividad. |
| `/admin/subastas` | Pestañas con conteo, crear subasta, Editar, Panel en vivo, Cerrar ahora (en un próximo = cancelar), Crear remate nuevo (en cerradas). En celular: botón «Acciones». |
| `/admin/subastas/{id}` | Datos (fijos una vez que abre el primer lote), lotes, documentos, «Para publicar falta:» en borradores, Cancelar remate. |
| `/admin/subastas/{id}/en-vivo` | Precio, cronómetro, ganador con nombre, historial, mensaje a la sala, cerrar lote con motivo. |
| `/admin/postores` | Filas reales, pestañas por estado y «Cuentas por aprobar», búsqueda, Aprobar/Rechazar con motivo, Ficha con documentos, Exportar listado (CSV). |
| `/admin/configuracion` | Todos los valores del negocio, prueba de correo, UF y Sistema. |
| `/admin/reportes` | Datos reales del mes del último cierre: KPIs, tabla por remate (base, final, sobreprecio, pujas, minutos de la primera a la última puja, resultado), participación, dinámica y categorías. El selector cambia el período (mes, trimestre, año, todo). **CSV** baja el desempeño comercial, **XLSX** el libro completo (6 hojas) y cada «Descargar» su hoja: ábrelos en Excel y revisa tildes, montos y que los postores salgan como «Postor #N». «Descargar PDF» todavía no hace nada (pendiente de decisión). |

## 10. Si algo falla

1. **Anota:** URL, hora exacta, usuario y lo que viste (captura).
2. **Registro de la aplicación:**
   ```
   tail -n 80 storage/logs/laravel.log
   ```
3. **Deploy:** `tail -n 50 ~/scripts/deploy-colliers.log`
4. **Diagnóstico:** `php artisan colliers:diagnostico` y la sección **Sistema** de `/admin/configuracion`.
5. **Correos que no llegan:**
   ```
   php artisan queue:failed
   php artisan tinker --execute="echo App\Models\NotificacionLog::where('estado','fallida')->latest('id')->value('error');"
   ```
   Si el cron no corre, la cola no avanza: revisa «Cron (programador)» en Sistema.
6. **Pujas rechazadas que no esperabas** (motivo exacto de cada intento):
   ```
   php artisan tinker --execute="App\Models\PujaIntento::latest('id')->take(10)->get(['motivo','monto','recibida_en','detalle'])->each(fn(\$i)=>print(json_encode(\$i).PHP_EOL));"
   ```

| Síntoma | Causa probable | Qué hacer |
|---|---|---|
| El sitio pide la clave de acceso en cada página | La cookie no se guardó (navegador en modo estricto) | Probar en otra ventana; revisar `COLLIERS_ACCESO_CLAVE`. |
| «No se puede publicar: … Asigna un martillero» | No hay martilleros | Paso 1.5. |
| Nadie puede inscribirse | El cierre de garantías ya pasó (vacío = 48 h antes del inicio) | En la ficha, fija el cierre de garantías antes del inicio y en el futuro. |
| La sala redirige a «Mi cuenta» | Cuenta o garantía de ESE remate sin aprobar | Pasos 4 y 5. |
| El precio no se actualiza en vivo | El JSON no se escribe o queda en caché | Abrir `/tiempo-real/{slug}.json`: debe cambiar al pujar y responder `Cache-Control: no-store`. |
| Queda «CERRADO · ADJUDICANDO» mucho rato | Nadie tiene la sala abierta y el cron no corre | Esperar 1 min (cron) o abrir `/remates/{slug}/estado`. |
| «Enviando…» tarda varios segundos | Servidor sin OPcache con varias pujas juntas | Anotar el tiempo; ver `docs/RENDIMIENTO-SIN-OPCACHE.md`. No se pierden pujas. |
| Página 419 «La sesión expiró» | Pestaña abierta mucho tiempo | Recargar e intentar de nuevo. |
| Página 500 | Error de la aplicación | `tail -n 80 storage/logs/laravel.log` y enviarme el bloque del error. |
