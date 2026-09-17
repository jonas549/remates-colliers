# Reporte de QA — Remates Colliers (Sandbox)

- **Fecha de ejecución:** 17 de septiembre de 2026
- **Entorno:** Sandbox `https://rematescolliers.sandboxdelta.com` · deploy `c193405` · APP_ENV=staging · PHP 8.4.24 (litespeed) · OPcache **apagado**
- **Navegador:** Microsoft Edge 153 (Windows) — verificado por user-agent `Edg/153.0.0.0`
- **Ejecutó:** QA automatizado (Claude) sobre el navegador. Sin acceso a terminal ni a SMTP (según instrucción).
- **Evidencia:** carpeta `qa-evidencia/` (capturas por caso). Sin captura, el caso no se considera probado.

> **Nota de alcance:** las capturas están junto a este archivo, en `docs/qa/2026-09-17-sandbox/`.
>
> **Respuesta del equipo (17/09, ver `docs/BACKLOG.md`):** INC-2 corregido (la apertura del lote siguiente ahora se
> materializa y republica el JSON, también por el cron, sin nadie en la sala). OBS-4 corregido (una cuenta rechazada
> cuenta en la pestaña RECHAZADA). INC-1 e INC-3 son comportamiento esperado, confirmado por Jonas: el correo confirmado
> es regla de negocio y la sesión única por navegador es de sesión, no de la aplicación. OBS-5 (PDF) sigue pendiente de
> decisión y OBS-6 (UF) depende de la conexión saliente del servidor.

---

## 1. Resumen ejecutivo

Se probó lo funcional accesible desde el navegador: gate de acceso, login/logout, bloqueo por intentos, registro de postores (natural y jurídica, RUT válido/inválido/duplicado/sin puntos), configuración, creación/edición/publicación/cancelación de remates, lotes y reordenamiento con fotos, rechazo de cuentas, sitio público con filtros, panel del martillero (mensaje a la sala) y feed en tiempo real (JSON).

**Bloqueador principal:** el ciclo completo de **pujas hasta la adjudicación con postores reales NO se pudo ejecutar**. La causa es que **el backend exige que el postor confirme su correo antes de poder aprobar la cuenta** (respuesta `422 "El postor todavía no confirma su correo."`). Sin SMTP (fuera de alcance por instrucción) y sin terminal para generar el enlace firmado de verificación, **ningún postor puede quedar habilitado para pujar**. Esto corta: aprobar cuenta → aprobar garantía → pujar. Ver §5 y la lista de comandos al final.

---

## 2. Tabla de casos

| # | Caso | Resultado | Evidencia |
|---|------|-----------|-----------|
| C01 | Gate de acceso al sitio con clave | ✅ Pasó | C01-acceso-clave-sitio-antes/despues |
| C02 | Login de administrador | ✅ Pasó | C02-admin-login-antes/despues-dashboard |
| C03 | Configuración: margen=5, duración lote=8, datos bancarios; se guarda | ✅ Pasó | C03-config-antes/despues-guardada |
| C04 | Logout de administrador | ✅ Pasó | C04-logout-admin-antes/despues |
| C05 | Registro postor — RUT inválido (dígito verificador) | ✅ Pasó (rechaza) | C05-registro-rut-invalido-error |
| C06 | Registro postor natural — RUT sin puntos, válido | ✅ Pasó | C06-registro-rut-sin-puntos-valido / C06-...-confirma-correo |
| C07 | Postor cae en "Confirma tu correo"; en panel queda "SIN CONFIRMAR CORREO" y Aprobar deshabilitado | ⚠️ Observación | C07-postor-en-panel... / C07-aprobar-cuenta-deshabilitado |
| C08 | Crear y publicar remate R-2026-001 | ✅ Pasó | C08-crear-subasta-formulario/datos/publicada |
| C09 | Crear segundo lote | ✅ Pasó | C09-lote2-creado |
| C10 | Subir 2 fotos al lote | ✅ Pasó | C10-lote2-fotos-subidas |
| C11 | Reordenar lotes (Subir) y reprogramar horarios | ✅ Pasó | C11-reorden-lotes-antes/despues |
| C12 | Guardar borrador; "Para publicar falta" (fecha, martillero) | ✅ Pasó | C12-borrador-para-publicar-falta |
| C13 | Cancelar remate con motivo (acción irreversible) | ✅ Pasó | C13-cancelar-remate-modal-motivo / C13-remate-cancelado |
| C14 | Sitio público: hero "Próximo remate" y cuenta regresiva | ✅ Pasó | C14-sitio-publico-hero-proximo |
| C15 | Filtro "En vivo" cambia el conteo a 0 | ✅ Pasó | C15-filtro-envivo-cero |
| C16 | Detalle de remate como visitante (ficha, lotes, pasos) | ✅ Pasó | C16-detalle-remate-visitante |
| C17 | JSON de tiempo real responde con no-store | ✅ Pasó | C17-json-tiempo-real |
| C18 | Registro — RUT duplicado rechazado | ✅ Pasó | C18-registro-rut-duplicado |
| C19 | Registro persona jurídica (razón social, RUT empresa, giro) | ✅ Pasó | C19-registro-juridica / ...-confirma-correo |
| C20 | Bloqueo tras 5 intentos fallidos de login (15 min) | ✅ Pasó | C20-bloqueo-5-intentos |
| C21 | /mi-cuenta y /sala redirigen a "Confirma tu correo" sin confirmar | ✅ Pasó | C21-mi-cuenta-redirige-confirma |
| C22 | Panel de postores lista registros con estado de cuenta | ✅ Pasó | C22-postores-sin-confirmar-correo |
| C23 | Rechazar cuenta con motivo | ✅ Pasó | C23-rechazar-cuenta-modal-motivo / C23-cuenta-rechazada |
| C24 | Backend exige correo confirmado para aprobar (422) | ⛔ Bloqueador | C24-backend-aprobar-sin-correo.txt |
| C25 | Panel del martillero carga (estado programado) | ✅ Pasó | C25-panel-martillero-programado |
| C26 | Mensaje a la sala se publica y llega al JSON en vivo | ✅ Pasó | C26-mensaje-sala-publicado / C26-mensaje-sala-json.txt |
| C27 | Dashboard con KPIs y actividad reciente | ✅ Pasó | C27-dashboard-kpis |
| C28 | Reportes post-evento accesibles (sin cierres aún) | ✅ Pasó | C28-reportes-sin-cierres |
| C29 | Sala de pujas: 403 a usuario no habilitado | ✅ Pasó | C29-sala-403-no-habilitado |

<!-- LA SECCIÓN EN VIVO (apertura, precio en vivo, cierre por temporizador, cierre anticipado, lote desierto, paso al siguiente) SE COMPLETA ABAJO -->


### 2.1 Ciclo de remate en vivo (sin postores habilitados)

| # | Caso | Resultado | Evidencia |
|---|------|-----------|-----------|
| C30 | Remate pasa a EN VIVO; lote 1 cierra por temporizador | ✅ Pasó | C30-remate-en-vivo-lote1-desierto |
| C31 | Panel del martillero muestra lote 2 EN VIVO · PUJA ABIERTA con cronómetro | ✅ Pasó | C31-panel-martillero-lote2-envivo |
| C32/C33 | Cierre anticipado con motivo → "CERRADO · ADJUDICANDO" (margen 5 s) | ✅ Pasó | C32-cierre-anticipado-modal / C33-cerrado-adjudicando |
| C34 | Ficha del remate: CERRADA, lote 2 con leyenda "Cierre anticipado: …" | ✅ Pasó | C34-remate-cerrada-ficha / C34-lotes-desiertos-detalle |
| C35 | Estado final del feed: remate finalizado, lote 1 desierto (tiempo), lote 2 desierto (anticipado) | ✅ Pasó | C35-json-final.txt |
| C36 | Dashboard: actividad "cerró anticipadamente" y "cerró sin postores"; 0 de 1 concretados | ✅ Pasó | C36-dashboard-actividad-cierre |
| C37 | Reportes: 2 lotes "No adjudicado", tasa 0%, resumen por categoría | ✅ Pasó | C37-reportes-2-lotes-no-adjudicado |
| — | **Lote desierto** (sin pujas, cierra por tiempo) | ✅ Pasó | C30 / C35 |
| — | **Cierre por temporizador** (lote 1) | ✅ Pasó | C30 / C35 |
| — | **Cierre anticipado** (lote 2) | ✅ Pasó | C32-C35 |

### 2.2 Casos NO ejecutables (bloqueados) — requieren postor habilitado

Todos dependen de aprobar la cuenta, lo que el backend impide sin correo confirmado (C24):

| Caso | Motivo |
|------|--------|
| Aprobar cuenta de postor | Backend responde 422 "El postor todavía no confirma su correo" (sin SMTP ni terminal no se confirma) |
| Inscribir y aprobar/rechazar **garantía** | Requiere cuenta aprobada |
| **Puja válida** hasta adjudicación con ganador real | Requiere garantía aprobada |
| **Puja bajo el mínimo** (rechazo con puja real) | Requiere postor habilitado |
| **Puja sin garantía / sin cuenta aprobada** (rechazo del endpoint) | Requiere una sesión de postor logueada (ver limitación de sesión única) |
| **Puja del que va ganando** ("Tienes la puja más alta") | Requiere ≥1 postor habilitado |
| **Puja después del cierre** (rechazo "El lote ya cerró") | Requiere postor habilitado |
| **Dos postores simultáneos** + carrera de puja | Requiere ≥2 postores habilitados y ≥2 sesiones/navegadores |
| **Tiempos reales de confirmación con varios postores** | Igual que arriba — **dato solicitado que quedó pendiente** |
| **Precio en vivo actualizándose sin recargar** (con pujas) | Requiere pujas reales para ver cambiar el precio |
| **Adjudicación con ganador real** y correos de adjudicación | Requiere pujas; además los correos dependen de SMTP |

---

## 3. Incidencias y observaciones

### INC-1 (Bloqueador) · No se puede habilitar postores sin confirmar el correo
- **Qué se esperaba:** poder aprobar una cuenta desde el panel para luego probar garantías y pujas.
- **Qué pasó:** el botón "Aprobar cuenta" está deshabilitado mientras la cuenta esté "SIN CONFIRMAR CORREO", y el **backend** también lo rechaza: `POST aprobarCuenta → 422 {"ok":false,"mensaje":"El postor todavía no confirma su correo."}`.
- **URL:** `/admin/postores` · **Hora:** ~14:34 (Chile) · **Evidencia:** C07, C22, C24-backend-aprobar-sin-correo.txt
- **Impacto:** corta todo el flujo de pujas/garantías/adjudicación con postores reales.
- **No es un bug en sí** (es la regla de negocio "confirmar correo primero"), pero **bloquea el QA** sin SMTP ni el enlace de verificación por terminal. Ver comandos al final.

### INC-2 · El feed de tiempo real y el badge del lote no reflejan la apertura automática del lote 2
- **Qué se esperaba:** al vencer el lote 1 (14:54), el lote 2 pasa solo a "en vivo" y el feed lo muestra.
- **Qué pasó:** a las 14:57 el JSON seguía con lote 2 `programado` y `generado_en_ms` congelado en ~14:40. El **panel del martillero** sí mostraba el lote 2 "EN VIVO · PUJA ABIERTA" con cronómetro, pero **el badge de la pestaña del lote seguía "PROGRAMADO"** y el JSON no se había reescrito hasta que un request lo forzó.
- **URL:** `/tiempo-real/av-prueba-123-depto-45.json` y `/admin/subastas/1/en-vivo` · **Hora:** 14:54–14:58 · **Evidencia:** C31 (panel dice EN VIVO, pestaña dice PROGRAMADO), C17/C35.
- **Impacto:** un espectador que solo mira el sitio público podría no ver que el lote abrió hasta que alguien "toca" el sistema. Coincide con lo que el propio manual advierte en §10 ("El precio no se actualiza en vivo" / "queda CERRADO·ADJUDICANDO"). **Conviene confirmar que el cron reescribe el JSON en cada transición aun sin nadie en la sala.**

### INC-3 · Sesión única del navegador: admin y postor no coexisten
- **Qué pasó:** admin y postor comparten la cookie de sesión del mismo perfil. Al iniciar sesión como postor se pierde la de admin, y viceversa; al cerrar sesión del postor se cerró también la del admin.
- **Impacto para QA:** las pujas simultáneas y la operación admin+postor en paralelo exigen **varias sesiones/navegadores distintos** (ventanas privadas o dispositivos), como indica el manual §0. Con un solo Edge conectado no se pueden reproducir los tiempos de confirmación simultánea.

### OBS-4 · Contador de la pestaña "RECHAZADA" no sube
- Tras rechazar la cuenta de un postor, la fila queda "RECHAZADO" pero el contador de la pestaña **RECHAZADA** siguió en 0 (y "CUENTAS POR APROBAR" bajó). Menor, de conteo. **Evidencia:** C23-cuenta-rechazada.

### OBS-5 · "Descargar PDF" en Reportes
- No se probó a fondo (descarga de archivos requiere confirmación del usuario). Según el manual §9 esta acción "todavía no hace nada (pendiente de decisión)". Los exportables **CSV/XLSX** existen pero, al no haber pujas ni adjudicaciones, no hubo datos de montos/tildes/"Postor #N" que validar; queda pendiente para cuando haya un remate con pujas reales.

### OBS-6 · UF sin valor
- El sitio muestra "UF de hoy —, sin valor vigente" (fuente automática). No se probó "Actualizar la UF ahora" (config) para no exceder el alcance; el sitio funciona igual sin UF. Queda a criterio de Jonas.

---

## 4. Datos de configuración observados (Sistema)

- OPcache (web): **APAGADO** · PHP 8.4.24 · litespeed · Entorno **staging** · Límite memoria 2048M · Máx. por petición 30 s
- Cachés optimize: configuración/rutas/eventos **sí**
- Cron (programador): último latido < 1 min al momento de revisar
- Cola de correos: 0 pendientes · 0 fallidos · Correo saliente: **log** (modo registro; SMTP no configurado)
- Seguridad: intentos antes de bloquear **5**, bloqueo **15 min** (verificado en C20)

---

## 5. Comandos que necesito que corras en el servidor (terminal cPanel)

Para **desbloquear y completar** el ciclo de pujas (INC-1). En `/home/oywadfan/remates-colliers` (usa `/opt/cpanel/ea-php84/root/usr/bin/php` si `php` no es 8.4):

**A) Generar el enlace de verificación de correo de los postores de prueba** (sin SMTP), para poder aprobarlos y seguir con garantías y pujas. Uno por postor (cambia el correo):

```bash
php artisan tinker --execute="\$u=App\Models\User::where('email','postor1@correo.test')->first(); echo URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id'=>\$u->id,'hash'=>sha1(\$u->getEmailForVerification())]).PHP_EOL;"
```

Pásame cada URL (o ábrela tú en Edge estando logueado con ese postor). Con el correo confirmado, la cuenta se puede aprobar y sigo con: aprobar/rechazar garantía, puja válida/bajo mínimo/del ganador/después del cierre, dos simultáneos y adjudicación con ganador real.

> Alternativa: si prefieres, activa SMTP tú (fuera de mi alcance) y confirma los correos abriendo el enlace del mail; el efecto es el mismo.

**B) (Opcional) Postores de demostración** — desbloquea de una vez varias sesiones habilitadas y da la clave común, ideal para las pujas simultáneas y los tiempos de confirmación:

```bash
php artisan colliers:remate-demo --postores=5 --inicio=3 --duracion=10
```

Devuelve el slug del remate demo, la clave común y la tabla de correos. Con eso puedo hacer las pujas en paralelo y medir los tiempos reales de confirmación (el dato que pediste). **Necesitaría además 2–3 navegadores/sesiones distintas** para la concurrencia real; con un solo Edge solo puedo aproximar.

**C) (Diagnóstico, cuando quieras)** para adjuntar al reporte:

```bash
php artisan colliers:diagnostico
tail -n 80 storage/logs/laravel.log
```

Y para confirmar por qué el feed no se reescribió solo (INC-2): revisar que el cron reprograme/escriba el JSON en cada transición de lote aunque nadie tenga la sala abierta.

---

## 6. Estado de los datos de prueba dejados en el sandbox

- **Remate R-2026-001** "Av. Prueba 123, Depto. 45" (2 lotes): **CERRADO**, ambos lotes desiertos. Sirve como remate cerrado de referencia.
- **Remate R-2026-002** "Pasaje Cancelado 999": **CANCELADO** (prueba de cancelación).
- **Postor1** (Ana Lucía Rojas Muñoz, `postor1@correo.test`, RUT 12.345.678-5, natural): **RECHAZADO** (prueba de rechazo) y **login bloqueado** ~15 min por la prueba de 5 intentos.
- **Postor2** (Inversiones QA SpA, `postor2@correo.test`, RUT empresa 76.543.210-3, jurídica): registrado, **sin confirmar correo**.
- **Configuración** modificada: margen de liquidación = 5 s, duración por defecto de lote = 8 min, datos bancarios de prueba (Banco de Chile / Cuenta Corriente / 00-123-45678-90 / "Colliers Remates SpA (PRUEBA QA)"). Ajústalos si no corresponden.

