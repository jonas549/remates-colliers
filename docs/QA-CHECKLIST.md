# QA · Lista de verificación (bloques T, C, D, J, K, I, V, G, H, M, N)

> Ejecutada por Claude en local el **17/09/2026** antes de las pruebas de Jonas. Cada caso dice qué se prueba, cómo
> hacerlo a mano, qué debe pasar y con qué se comprobó. Para probar en el sandbox, seguir además
> `docs/MANUAL-DE-PRUEBAS.md` (datos de acceso y pasos del servidor).

## Resultado

| | Casos |
|---|---|
| Ejecutados en local | **74** |
| Pasaron | **74** |
| Fallaron (al cierre) | **0** |
| Solo en sandbox (sin marcar) | **11** (sección 6) |

**Corregido durante el QA**

1. **Reordenar lotes no existía** (QA-31). Implementado: botones «↑ Subir» y «↓ Bajar» en la ficha del remate
   (`/admin/subastas/{id}`), renumera 1..N, reprograma los horarios y publica el JSON; bloqueado cuando abre el primer
   lote. Prueba nueva `RematesAdminTest::test_reordenar_lotes_reprograma_horarios_y_se_bloquea_al_comenzar` y dos
   comprobaciones nuevas en `panel-remates.mjs`.
2. **Falso positivo del arnés de usabilidad**: Chrome a veces mide 43,999996 px en una barra de 44 px, y el recorrido de
   Configuración fallaba de forma intermitente. Tolerancia de 0,01 px en `usabilidad.mjs`, `comparar.mjs` y
   `sin-original.mjs` (tres corridas seguidas en verde).
3. **Casos sin prueba propia**, agregados: RUT con y sin puntos en el registro (`AutenticacionTest`), configuración del
   panel que rige de verdad en pujas, garantías y cierre (`MotorPujasTest`), espectador que no puede pujar
   (`SitioPublicoTest`) y el recorrido en navegador `tools/comparar/registro-acceso.mjs` (25 comprobaciones: registro,
   RUT, correo, ingreso, bloqueo, caché, recuperación, clave temporal, aprobar/rechazar cuentas).

**Observación sin corregir (no es un fallo):** en la simulación local del sandbox (Apache sin OPcache) la comprobación C3 de
`tools/concurrencia/prueba.php` («consultas de estado antes de T + margen no adjudican») sale **NO CONCLUYENTE** con
1 núcleo: 10 consultas simultáneas tardan más que el propio margen (5 s y 10 s), así que la ventana no se alcanza a
observar. No hubo ninguna violación de invariantes; la regla está cubierta por `MotorPujasTest` y la prueba completa
pasa con OPcache (33/33). Es el riesgo ya anotado en el Bloque R.

## Cómo se ejecutó

| Herramienta | Resultado |
|---|---|
| `php artisan test` (PHPUnit, SQLite en memoria) | **117/117** (888 aserciones) |
| `node tools/comparar/registro-acceso.mjs` (nuevo) | 25/25 |
| `node tools/comparar/panel-remates.mjs` | 37/37 |
| `node tools/comparar/sala-real.mjs` | 32/32 |
| `node tools/comparar/panel-configuracion.mjs` | 10/10 (tres corridas) |
| `node tools/comparar/cuenta-postor.mjs` | 9/9 |
| `node tools/comparar/recorrido-garantia.mjs` | OK a 375 px |
| `node tools/comparar/sitio-publico.mjs` | 33/33 |
| `node tools/comparar/filtros-interaccion.mjs` | 43/43 |
| `php tools/concurrencia/prueba.php 20` (Apache + MySQL 8.4, OPcache) | 33/33 |
| `prueba.php 10` sin OPcache y 1 núcleo, margen 5 s y 10 s | A, B, D y cierre en verde; C3 no concluyente (ver arriba) |

Navegador: Chrome real (Playwright) contra `php artisan serve`. Concurrencia: nunca con `artisan serve`.

### Preparar el entorno local para repetirlo a mano

1. `php tools/comparar/sala-ayudante.php reiniciar` (base desde cero con el seeder; **solo local**).
2. `npm run build` y `php artisan serve` → `http://127.0.0.1:8000`.
3. Cuentas del seeder (clave de todas: `colliers-local-2026`, solo existen en local):
   - Administración: `admin@colliers.test` en `/admin/ingresar`.
   - Martillero: `mossandon@colliers.test` en `/admin/ingresar`.
   - Postores aprobados con garantía en el remate en vivo `apoquindo`: `mpgonzalez@correo.test`, `contacto@andes.test`,
     `camila.ortiz@correo.test`. Postor con cuenta en revisión: `rsalas@correo.test`.
4. Los correos en local quedan en `storage/logs/laravel.log` (`MAIL_MAILER=log`).

Leyenda: `[x]` pasó en local · `[ ]` pendiente (solo en sandbox, sección 6).

---

## 1. Registro y cuenta (Bloque D)

### QA-01 · Registro de persona natural
- [x] **OK en local**
- **Qué se prueba:** que una persona se registre con sus datos y documentos.
- **Pasos:** 1) Abrir `/registro`. 2) Dejar «Persona natural». 3) Completar nombres, apellidos, RUT `21.345.678-4`, fecha de
  nacimiento, nacionalidad, correo nuevo, teléfono, dirección y comuna. 4) Subir los tres documentos (PDF o JPG).
  5) Contraseña y repetición iguales (8+ caracteres). 6) Marcar la aceptación y «Enviar solicitud de registro».
- **Esperado:** redirige a `/verificar-correo`; los documentos quedan en el disco privado; llega el correo de confirmación.
- **Evidencia:** `registro-acceso.mjs` §1 · `AutenticacionTest::test_registro_de_persona_natural_con_documentos_privados_y_verificacion_de_correo`.

### QA-02 · Registro de persona jurídica
- [x] **OK en local**
- **Qué se prueba:** registro en representación de una empresa.
- **Pasos:** igual que QA-01, pero elegir «Persona jurídica», completar razón social, RUT de la empresa (`77.123.456-9`),
  giro, calidad y subir además el poder. Probar también enviar sin los datos de empresa.
- **Esperado:** sin los datos de empresa o sin poder, errores en esos campos; completo, redirige a `/verificar-correo`.
  Una segunda cuenta con el mismo RUT de empresa se rechaza («Esta empresa ya tiene una cuenta registrada…»).
- **Evidencia:** `registro-acceso.mjs` §2 · `AutenticacionTest::test_registro_de_persona_juridica_exige_empresa_y_poder`.

### QA-03 · RUT inválido
- [x] **OK en local**
- **Pasos:** registrar con RUT `21.345.678-5` (dígito verificador incorrecto) y con `17.998.221` (sin dígito).
- **Esperado:** el formulario vuelve con error en el RUT («El RUT no es válido: revisa el dígito verificador.» para el
  dígito incorrecto) y no se crea la cuenta ni se guardan archivos.
- **Evidencia:** `registro-acceso.mjs` §1 · `AutenticacionTest::test_registro_rechaza_rut_invalido_o_repetido_y_documentos_faltantes`,
  `…test_registro_acepta_rut_con_o_sin_puntos_y_detecta_el_repetido_en_cualquier_formato`.

### QA-04 · RUT duplicado (en cualquier formato)
- [x] **OK en local**
- **Pasos:** registrar con `17998221-8` (ya existe en el seeder como `17.998.221-8`); luego con el RUT de QA-01 escrito como
  `21.345.678-4` y como `21345678-4`.
- **Esperado:** en los tres casos «Ya existe una cuenta con este RUT. Si es tuya, ingresa o recupera tu contraseña.»
- **Evidencia:** `registro-acceso.mjs` §2 · `AutenticacionTest` (dos pruebas citadas en QA-03).

### QA-05 · RUT con y sin puntos
- [x] **OK en local**
- **Pasos:** registrar con `213456784` (sin puntos ni guion) y otra cuenta con `15.482.331-k` (puntos y k minúscula) sobre
  una base sin ese RUT.
- **Esperado:** ambas se aceptan y el RUT se muestra normalizado (`21.345.678-4`, `15.482.331-K`); en la base va cifrado.
- **Evidencia:** `registro-acceso.mjs` §1 · `AutenticacionTest::test_registro_acepta_rut_con_o_sin_puntos_…` · `ModeloDatosTest::test_indice_ciego_no_depende_del_formato_ni_expone_el_rut`.

### QA-06 · Documentos obligatorios y archivos no permitidos
- [x] **OK en local**
- **Pasos:** registrar sin la cédula (frente); luego con un `.exe` como comprobante de domicilio; luego sin aceptar las condiciones.
- **Esperado:** error en el campo correspondiente; no se crea cuenta ni queda ningún archivo guardado.
- **Evidencia:** `AutenticacionTest::test_registro_rechaza_rut_invalido_o_repetido_y_documentos_faltantes`.

### QA-07 · Confirmación de correo
- [x] **OK en local**
- **Pasos:** 1) Tras registrarse, abrir `/mi-cuenta`. 2) Abrir el enlace del correo «confirmar correo» (en local, en
  `storage/logs/laravel.log`). 3) Abrir `/mi-cuenta`.
- **Esperado:** antes de confirmar, `/mi-cuenta` devuelve a `/verificar-correo`; después muestra «Estamos revisando tu
  registro» y la cuenta pasa a «en revisión» (aparece en «Cuentas por aprobar» del panel).
- **Evidencia:** `registro-acceso.mjs` §1 · `AutenticacionTest::test_registro_de_persona_natural_…`.

### QA-08 · Ingreso con correo
- [x] **OK en local**
- **Pasos:** `/ingresar`, correo y contraseña de QA-01, «Ingresar».
- **Esperado:** entra a `/mi-cuenta`; queda un registro «ingreso» en `access_logs`.
- **Evidencia:** `registro-acceso.mjs` §3 · `AutenticacionTest::test_postor_ingresa_con_correo_o_con_rut_en_cualquier_formato`.

### QA-09 · Ingreso con RUT (con y sin puntos)
- [x] **OK en local**
- **Pasos:** cerrar sesión y entrar con `21.345.678-4`; repetir con `213456784`; repetir con correo en mayúsculas.
- **Esperado:** las tres entran a `/mi-cuenta`.
- **Evidencia:** `registro-acceso.mjs` §3 · misma prueba de QA-08.

### QA-10 · Usuario inexistente y accesos separados
- [x] **OK en local**
- **Pasos:** 1) `/ingresar` con `nadie@correo.test`. 2) `/ingresar` con `admin@colliers.test`. 3) `/admin/ingresar` con un postor.
- **Esperado:** 1) «Usuario o contraseña incorrectos.» (sin revelar si existe). 2) «Esta cuenta es de administración:
  ingresa por el acceso de administradores.» 3) «Esta cuenta es de postor: ingresa por el acceso de postores.»
- **Evidencia:** `AutenticacionTest::test_usuario_inexistente_…`, `…test_accesos_separados_para_postores_y_administracion`, `…test_cuenta_inactiva_no_ingresa`.

### QA-11 · Bloqueo tras 5 intentos fallidos
- [x] **OK en local**
- **Pasos:** `/ingresar` con el correo de QA-01 y una clave incorrecta, 5 veces.
- **Esperado:** intentos 1–4: «…Te quedan N intentos antes de que bloqueemos la cuenta por seguridad.»; intento 5:
  «…Bloqueamos la cuenta por seguridad durante 15 minutos.»; `users.intentos_fallidos` y `users.bloqueado_hasta` en la tabla.
- **Evidencia:** `registro-acceso.mjs` §3 · `AutenticacionTest::test_cinco_intentos_fallidos_bloquean_la_cuenta_15_minutos_con_contador_en_tabla`.

### QA-12 · Vaciar la caché no desbloquea
- [x] **OK en local**
- **Pasos:** con la cuenta bloqueada (QA-11): `php artisan cache:clear` y `php artisan optimize:clear`; ingresar con la clave **correcta**.
- **Esperado:** sigue bloqueada: «La cuenta está bloqueada por seguridad hasta las HH:MM…». A los 15 minutos entra y el contador vuelve a 0.
- **Evidencia:** `registro-acceso.mjs` §3 (con los comandos reales) · misma prueba de QA-11 (`Cache::flush()`).

### QA-13 · Recuperación de contraseña
- [x] **OK en local**
- **Pasos:** 1) `/recuperar-clave`, correo de QA-01, «Enviar enlace». 2) Abrir el enlace del correo. 3) Nueva clave y
  confirmación. 4) Ingresar con la clave antigua y luego con la nueva.
- **Esperado:** la antigua falla; la nueva entra aunque la cuenta estuviera bloqueada; las demás sesiones abiertas se cierran.
- **Evidencia:** `registro-acceso.mjs` §4 · `AutenticacionTest::test_recuperar_y_restablecer_la_contrasena_cierra_sesiones_y_levanta_el_bloqueo`.

### QA-14 · Cambio de contraseña obligatorio (administración)
- [x] **OK en local**
- **Pasos:** 1) `php artisan colliers:crear-usuario admin qa@colliers.test Admin QA` y copiar la clave temporal.
  2) `/admin/ingresar` con esa clave. 3) Abrir `/admin/postores`. 4) Cambiar la clave. 5) Abrir `/admin/postores`.
- **Esperado:** 3) redirige a `/mi-cuenta/cambiar-clave` («Cambia tu contraseña para continuar»); 5) el panel abre.
- **Evidencia:** `registro-acceso.mjs` §5 · `AutenticacionTest::test_cambio_de_contrasena_obligatorio_antes_de_usar_el_panel` · `InfraestructuraTest::test_crear_martillero_con_clave_temporal_de_un_solo_uso`.

### QA-15 · Cambiar la propia contraseña y sesiones activas
- [x] **OK en local**
- **Pasos:** en `/mi-cuenta/cambiar-clave` probar con la clave actual equivocada y luego correcta; ver sesiones activas y cerrar otra.
- **Esperado:** sin la clave actual no cambia; al cambiar se cierran las demás sesiones; solo se ven y cierran sesiones propias.
- **Evidencia:** `AutenticacionTest::test_cambiar_la_propia_contrasena_exige_la_actual_…`, `…test_sesiones_activas_solo_propias_y_cierre_remoto`, `…test_admin_restablece_la_clave_de_otra_cuenta_…`, `…test_admin_desbloquea_una_cuenta`.

### QA-16 · Documentos privados del postor
- [x] **OK en local**
- **Pasos:** con la URL de un documento de un postor (`/mi-cuenta/documentos/{id}`), abrirla como su dueño y como otro postor; en el panel, abrirlo como administración, como martillero y con el id de otro postor.
- **Esperado:** el dueño y la administración lo descargan; otro postor y el martillero: 403; documento pedido bajo otro postor: 404.
- **Evidencia:** `AutenticacionTest::test_documentos_solo_para_su_dueno_y_la_administracion`.

---

## 2. Administración (Bloques I, V, G, H)

### QA-17 · Aprobar una cuenta
- [x] **OK en local**
- **Pasos:** `/admin/postores` → pestaña «Cuentas por aprobar» → fila del postor de QA-01 → «Aprobar cuenta».
- **Esperado:** la cuenta pasa a «aprobado», sale de la pestaña, queda en bitácora y se envía el correo de aprobación.
  Una cuenta que no confirmó el correo no se puede aprobar («El postor todavía no confirma su correo.»).
- **Evidencia:** `registro-acceso.mjs` §6 · `PostoresGarantiasTest::test_aprobar_y_rechazar_cuentas_con_motivo_bitacora_y_evento` · `NotificacionesTest::test_revision_de_cuenta_y_garantia_…`.

### QA-18 · Rechazar una cuenta
- [x] **OK en local**
- **Pasos:** en «Cuentas por aprobar» → «Rechazar» sobre la cuenta jurídica → enviar sin motivo → escribir
  «Falta el poder notarial vigente» → enviar. Luego ingresar como ese postor a `/mi-cuenta`.
- **Esperado:** sin motivo no se envía; con motivo la cuenta queda «rechazado», el postor recibe el correo y ve el motivo en «Mi cuenta».
- **Evidencia:** `registro-acceso.mjs` §6 · `PostoresGarantiasTest::test_aprobar_y_rechazar_cuentas_…`.

### QA-19 · Bloquear y desbloquear una cuenta aprobada
- [x] **OK en local**
- **Pasos:** en `/admin/postores` → «Ficha» → «Bloquear cuenta» con motivo; intentar inscribirse como ese postor; «Desbloquear cuenta».
- **Esperado:** bloqueada no puede inscribirse ni pujar; al desbloquear vuelve a «aprobado».
- **Evidencia:** `PostoresGarantiasTest::test_cuenta_bloqueada_no_se_inscribe_y_se_desbloquea` · `MotorPujasTest::test_sin_garantia_aprobada_o_sin_cuenta_aprobada_se_rechaza`.

### QA-20 · Inscripción y comprobante de garantía (postor)
- [x] **OK en local**
- **Pasos:** como postor aprobado, en el detalle de un remate próximo → «Inscribirme» → en `/mi-cuenta` «Subir comprobante» (PDF), elegir medio.
- **Esperado:** nace la garantía «pendiente» con el 10 % de la suma de precios base; al subir el comprobante pasa a
  «en revisión» y la administración recibe aviso. Sin pasarela de pago.
- **Evidencia:** `cuenta-postor.mjs` · `PostoresGarantiasTest::test_inscripcion_comprobante_revision_y_reenvio`.

### QA-21 · Aprobar una garantía (también desde el celular)
- [x] **OK en local**
- **Pasos:** `/admin/postores` a 375 px → pestaña «En revisión» → «Aprobar garantía».
- **Esperado:** la garantía queda «aprobada», el postor recibe el correo y el detalle del remate le muestra «Estás habilitado para pujar».
- **Evidencia:** `recorrido-garantia.mjs` (375 px) · `PostoresGarantiasTest::test_inscripcion_comprobante_…` · `SitioPublicoTest::test_variantes_del_visitante_…`.

### QA-22 · Rechazar una garantía y reenvío
- [x] **OK en local**
- **Pasos:** «Rechazar» sobre una garantía en revisión con motivo «El monto no coincide»; como postor, ver `/mi-cuenta` y subir otro comprobante.
- **Esperado:** el postor ve el motivo y puede reenviar; vuelve a «en revisión» y el motivo anterior se limpia; el comprobante anterior se conserva.
- **Evidencia:** `cuenta-postor.mjs` («rechazada», «reenvío») · `PostoresGarantiasTest::test_inscripcion_comprobante_…`.

### QA-23 · Plazos de garantía
- [x] **OK en local**
- **Pasos:** inscribirse o subir comprobante después del cierre de garantías, y con el remate ya comenzado.
- **Esperado:** «El plazo para constituir la garantía de este remate ya cerró.» / «El remate ya comenzó: no se reciben más garantías.»
- **Evidencia:** `PostoresGarantiasTest::test_reglas_de_plazo_y_de_cuenta_para_garantias`.

### QA-24 · Crear un remate
- [x] **OK en local**
- **Pasos:** `/admin/subastas` → «Crear subasta» → dirección, comuna, región, tipo, precio base `120.000.000`, inicio,
  duración, martillero, video → guardar como borrador.
- **Esperado:** remate en borrador con folio `R-AAAA-NNN`, slug y su primer lote; no aparece en el sitio público.
- **Evidencia:** `panel-remates.mjs` · `RematesAdminTest::test_crear_subasta_como_borrador_con_su_primer_lote`.

### QA-25 · Validación del formulario
- [x] **OK en local**
- **Pasos:** enviar «Crear subasta» vacío y con precio no numérico.
- **Esperado:** errores en español junto a cada campo; nada se guarda.
- **Evidencia:** `RematesAdminTest::test_validacion_del_formulario_en_espanol`.

### QA-26 · Editar un remate
- [x] **OK en local**
- **Pasos:** en `/admin/subastas/{id}` cambiar título, horario, duración y pausa antes de comenzar; repetir con el remate ya comenzado.
- **Esperado:** antes de comenzar se guarda y los lotes se reprograman; comenzado, horario/precio/duración no cambian
  (mensaje de error y nada se guarda) pero título y video sí.
- **Evidencia:** `RematesAdminTest::test_condiciones_fijas_una_vez_que_abre_el_primer_lote`, `…test_varios_lotes_con_horario_fijo_y_pausa`.

### QA-27 · Publicar un remate
- [x] **OK en local**
- **Pasos:** en la ficha, «Publicar» con datos faltantes (sin martillero, sin inicio); completar y publicar.
- **Esperado:** con faltantes sigue en borrador y lista lo que falta; completo queda publicado, fija el cierre de
  garantías (48 h antes por defecto), escribe el JSON público y avisa a los suscriptores.
- **Evidencia:** `panel-remates.mjs` · `RematesAdminTest::test_publicar_desde_el_formulario_…`, `…test_publicar_con_datos_faltantes_…` · `NotificacionesTest::test_avisame_publicacion_y_baja`.

### QA-28 · Cancelar un remate
- [x] **OK en local**
- **Pasos:** `/admin/subastas` → fila de un próximo → «Cerrar ahora» → el modal ofrece «Cancelar el remate» → motivo → «Cancelar remate». Intentarlo con uno en curso.
- **Esperado:** el próximo queda cancelado con motivo y sale del sitio público; uno en curso no se cancela.
- **Evidencia:** `panel-remates.mjs` · `RematesAdminTest::test_cancelar_solo_antes_de_comenzar`.

### QA-29 · Crear remate nuevo desde uno desierto o cancelado
- [x] **OK en local**
- **Pasos:** en la ficha de un remate cerrado sin adjudicar → «Crear remate nuevo».
- **Esperado:** borrador nuevo con los lotes no adjudicados, fotos y documentos; el original no cambia.
- **Evidencia:** `RematesAdminTest::test_crear_remate_nuevo_a_partir_de_uno_desierto`.

### QA-30 · Crear lotes
- [x] **OK en local**
- **Pasos:** ficha del remate → «Agregar lote →» → dirección, comuna, tipo, precio base `8.500.000`, duración propia → «Crear lote».
- **Esperado:** «Lote 2 guardado»; en la ficha el lote 2 abre al cerrar el lote 1 más la pausa.
- **Evidencia:** `panel-remates.mjs` · `RematesAdminTest::test_varios_lotes_con_horario_fijo_y_pausa`.

### QA-31 · Reordenar lotes *(implementado en este QA)*
- [x] **OK en local**
- **Pasos:** 1) Ficha de un remate no comenzado con 2 o más lotes. 2) En la fila del lote 2, «↑ Subir». 3) Revisar la
  tabla. 4) Con el remate ya comenzado, intentar mover un lote.
- **Esperado:** «Orden de los lotes actualizado; horarios reprogramados.»; el lote movido queda 1.º y abre a la hora de
  inicio; el desplazado abre después (duración + pausa). El primero no muestra «Subir» ni el último «Bajar».
  Comenzado el remate: «El remate ya comenzó: el orden de los lotes no se puede cambiar.» y no cambia nada.
  Martillero: 403. Lote de otro remate: 404.
- **Evidencia:** `panel-remates.mjs` («reordenar lotes», «el lote movido queda primero…») · `RematesAdminTest::test_reordenar_lotes_reprograma_horarios_y_se_bloquea_al_comenzar`.

### QA-32 · Subir imágenes
- [x] **OK en local**
- **Pasos:** ficha del lote → elegir una foto de 2560×1920 y otra PNG → «Subir fotos» → «Hacer principal» en la segunda → «Eliminar» la primera.
- **Esperado:** «2 fotos agregadas.»; la grande se reduce a 1920 px; la principal pasa a ser la primera del listado; al eliminar se borra el archivo.
- **Evidencia:** `panel-remates.mjs` · `RematesAdminTest::test_fotos_visitas_y_documentos_del_lote`.

### QA-33 · Documentos y horarios de visita
- [x] **OK en local**
- **Pasos:** ficha → «Subir documento» (PDF, público o solo con garantía); ficha del lote → «Agregar horario».
- **Esperado:** «Documento agregado», descargable desde el panel y, si es público, desde el detalle; visita guardada en UTC y mostrada en hora de Chile.
- **Evidencia:** `panel-remates.mjs` · `RematesAdminTest::test_fotos_visitas_y_documentos_del_lote` · `SitioPublicoTest::test_detalle_proximo_…`.

### QA-34 · Permisos del panel
- [x] **OK en local**
- **Pasos:** entrar como martillero a `/admin/subastas/{id}`, `/admin/postores` y `/admin/configuracion`; como postor a `/admin`.
- **Esperado:** martillero: solo listado y «Panel en vivo» de sus remates (403 en lo demás); postor: 403.
- **Evidencia:** `RematesAdminTest::test_solo_administracion_gestiona_y_el_martillero_ve_solo_su_panel_en_vivo` · `AutenticacionTest::test_panel_exige_rol_de_administracion` · `PostoresGarantiasTest::test_listado_real_solo_para_administradores`.

### QA-35 · Panel del martillero
- [x] **OK en local**
- **Pasos:** `/admin/subastas` → «Panel en vivo» de Av. Apoquindo 4501 → revisar precio y ganador → escribir «Quedan
  pocos minutos» → «Publicar mensaje» → abrir la sala como postor.
- **Esperado:** precio `$198.500.000` y ganador con nombre real y alias («María Paz González Soto (Postor #1)»); el
  mensaje aparece en la sala sin recargar (escritorio y sobre el video en móvil); el JSON público no trae identidades.
- **Evidencia:** `panel-remates.mjs` · `sala-real.mjs` («mensaje del martillero…») · `RematesAdminTest::test_panel_en_vivo_muestra_identidades_solo_al_panel` · `MotorPujasTest::test_mensaje_del_martillero_se_difunde`.

### QA-36 · Configuración: incremento mínimo se aplica
- [x] **OK en local**
- **Pasos:** `/admin/configuracion` → incremento mínimo `300000` → «Guardar configuración» → en la sala, pujar
  `$100.200.000` sobre `$100.000.000` y luego `$100.300.000`.
- **Esperado:** la puja mínima mostrada sube a +$300.000; la primera se rechaza («monto insuficiente»), la segunda se acepta. Sin deploy.
- **Evidencia:** `MotorPujasTest::test_configuracion_cambiada_en_el_panel_rige_en_pujas_garantias_y_cierre` · `ConfiguracionTest::test_guardar_valores_rige_de_inmediato_sin_deploy` · incremento propio del remate en `MotorPujasTest::test_primera_puja_debe_alcanzar_…`.

### QA-37 · Configuración: porcentaje de garantía se aplica
- [x] **OK en local**
- **Pasos:** porcentaje de garantía `5` → guardar → como postor aprobado, inscribirse en un remate próximo con base `$80.000.000`.
- **Esperado:** la garantía nace por `$4.000.000` (5 %). Un remate con porcentaje propio usa el suyo.
- **Evidencia:** misma prueba de QA-36 · `ModeloDatosTest::test_garantia_es_por_remate_sobre_la_suma_de_bases_y_redondea_hacia_arriba`.

### QA-38 · Configuración: margen de liquidación se aplica
- [x] **OK en local**
- **Pasos:** margen `6` → guardar → dejar vencer un lote con pujas y consultar su estado a los 5 s y a los 6 s del cierre.
- **Esperado:** a los 5 s está cerrado para pujar pero sin adjudicar; a los 6 s queda adjudicado a la última puja.
- **Evidencia:** misma prueba de QA-36.

### QA-39 · Configuración: validación, SMTP cifrado, UF y acceso
- [x] **OK en local**
- **Pasos:** guardar valores inválidos; guardar la clave SMTP y volver a guardar dejándola vacía; UF manual; entrar como martillero.
- **Esperado:** errores en español y nada se guarda; la clave SMTP queda cifrada y se conserva si se deja vacía; UF manual se muestra en el sitio; martillero 403.
- **Evidencia:** `panel-configuracion.mjs` · `ConfiguracionTest` (6 pruebas).

### QA-40 · Dashboard con datos reales
- [x] **OK en local**
- **Pasos:** abrir `/admin`.
- **Esperado:** actividad, remates y pendientes reales (sin remates de demostración); usable a 375 px.
- **Evidencia:** `panel-remates.mjs` («dashboard…») · `RematesAdminTest::test_listado_y_dashboard_con_datos_reales`.

### QA-41 · Exportar postores
- [x] **OK en local**
- **Pasos:** `/admin/postores` → exportar CSV.
- **Esperado:** descarga un CSV con las filas del listado (nombre, folio del remate y monto de la garantía).
- **Evidencia:** `PostoresGarantiasTest::test_exportar_listado_csv`.

---

## 3. Pujas (Bloques J y K)

Remate en vivo del seeder: `/remates/apoquindo/sala`. Postores: `mpgonzalez@correo.test` (va ganando),
`contacto@andes.test`, `camila.ortiz@correo.test`. Una ventana privada por postor.

### QA-42 · Puja válida con confirmación
- [x] **OK en local**
- **Pasos:** como `contacto@andes.test` en la sala → puja rápida o monto «Otro» ≥ mínimo → se abre el modal → confirmar.
- **Esperado:** sin el modal no se registra nada; el modal muestra el monto; al confirmar pasa a «Vas ganando»; los
  demás ven el precio nuevo y «Postor #N» arriba del historial en ~1 s; la base guarda la puja con hora de servidor, IP y user agent.
- **Evidencia:** `sala-real.mjs` · `MotorPujasTest::test_puja_valida_actualiza_el_lote_y_publica_el_estado_sin_identidades`.

### QA-43 · Puja bajo el incremento mínimo
- [x] **OK en local**
- **Pasos:** escribir en «Otro» un monto menor a la puja mínima; forzar el envío (petición directa) con ese monto.
- **Esperado:** en la sala el botón queda deshabilitado con ayuda en rojo; el motor responde 422 `monto_insuficiente` y
  registra el intento en `puja_intentos`. La primera puja debe alcanzar el precio base.
- **Evidencia:** `sala-real.mjs` · `MotorPujasTest::test_primera_puja_debe_alcanzar_el_precio_base_y_las_siguientes_el_incremento`, `…test_montos_mal_formados_se_rechazan`.

### QA-44 · Puja de postor sin cuenta aprobada
- [x] **OK en local**
- **Pasos:** como `rsalas@correo.test` (en revisión) abrir la sala y enviar una puja directa al endpoint.
- **Esperado:** la sala redirige a `/mi-cuenta`; el motor responde 422 `cuenta_no_habilitada` (también cuentas bloqueadas y administración).
- **Evidencia:** `SalaTest::test_sin_cuenta_o_garantia_aprobadas_…` · `MotorPujasTest::test_sin_garantia_aprobada_o_sin_cuenta_aprobada_se_rechaza`.

### QA-45 · Puja de postor sin garantía aprobada
- [x] **OK en local**
- **Pasos:** postor aprobado sin garantía, con garantía en revisión, y con garantía aprobada de **otro** remate: abrir la sala y pujar directo.
- **Esperado:** la sala redirige a su estado de cuenta; el motor responde 422 `sin_garantia` en los tres casos.
- **Evidencia:** `sitio-publico.mjs` («postor sin garantía…») · `MotorPujasTest::test_sin_garantia_aprobada_…`, `…test_garantia_de_otro_remate_no_habilita` · `SitioPublicoTest::test_detalle_en_vivo_…`.

### QA-46 · Puja del que ya va ganando
- [x] **OK en local**
- **Pasos:** como `mpgonzalez@correo.test` (va ganando) intentar pujar.
- **Esperado:** botón deshabilitado en la sala; directo al motor: 422 `ya_vas_ganando`.
- **Evidencia:** `sala-real.mjs` · `MotorPujasTest::test_quien_va_ganando_no_puede_superarse_a_si_mismo`.

### QA-47 · Puja después del cierre (y sin anti-sniping)
- [x] **OK en local**
- **Pasos:** `php tools/comparar/sala-ayudante.php cerrar-en 20`; pujar a los 19 s y otra vez a los 21 s.
- **Esperado:** la recibida antes de T se acepta aunque termine después y **no extiende** el cierre; la de después de T
  se rechaza `lote_cerrado`; al llegar a T el formulario desaparece.
- **Evidencia:** `sala-real.mjs` («al llegar a cierra_en…») · `MotorPujasTest::test_validez_por_hora_de_recepcion_y_sin_anti_sniping` · concurrencia C y D.

### QA-48 · Puja antes de abrir, en borrador o cancelado
- [x] **OK en local**
- **Esperado:** rechazadas; un borrador no tiene sala (404).
- **Evidencia:** `MotorPujasTest::test_antes_de_abrir_se_rechaza`, `…test_remate_en_borrador_o_cancelado_no_acepta_pujas` · `SalaTest::test_remate_en_borrador_no_existe_…`.

### QA-49 · Sin sesión, límite por minuto y lote de otro remate
- [x] **OK en local**
- **Esperado:** sin sesión 401; ráfaga sobre el límite 429; lote que no es del remate de la URL: 422 `lote_inexistente`.
- **Evidencia:** `MotorPujasTest::test_sin_sesion_responde_401`, `…test_limite_de_pujas_por_minuto`, `…test_lote_de_otro_remate_se_trata_como_inexistente`.

### QA-50 · Rechazos registrados aunque se deshaga la transacción
- [x] **OK en local**
- **Esperado:** cada 422 deja una fila en `puja_intentos` con motivo; la tabla `pujas` nunca se edita ni se borra.
- **Evidencia:** `MotorPujasTest::test_los_rechazos_quedan_registrados_fuera_de_la_transaccion` · `ModeloDatosTest::test_puja_guarda_microsegundos_y_no_se_edita_ni_se_borra` · concurrencia A («cada rechazo quedó registrado en puja_intentos»).

### QA-51 · Dos postores simultáneos: solo uno gana
- [x] **OK en local**
- **Pasos:** con MySQL de Laragon encendido: `php tools/concurrencia/servidor.php` en una terminal y `php tools/concurrencia/prueba.php 20` en otra.
- **Esperado:** escenario A: en cada ronda 20 pujas del mismo monto en el mismo instante → **exactamente una** aceptada
  (201×1, 422×19); precio y ganador = última puja; sin errores 5xx.
- **Evidencia:** concurrencia con OPcache 33/33 (20 postores) · sin OPcache y 1 núcleo, 10 postores: A en verde.

### QA-52 · Ráfagas desordenadas y caché vaciada a mitad del remate
- [x] **OK en local**
- **Esperado:** escenario B: secuencia de pujas aceptadas válida, precio = máximo, respuestas 201 = filas, JSON = base,
  aunque la caché se vacía en la ronda 8.
- **Evidencia:** concurrencia B (con y sin OPcache) · `MotorPujasTest::test_vaciar_la_cache_a_mitad_del_remate_no_altera_el_estado`.

### QA-53 · Cierre por temporizador y adjudicación automática
- [x] **OK en local**
- **Pasos:** tres postores pujando; `php tools/comparar/sala-ayudante.php cerrar-en 30`; esperar sin tocar nada.
- **Esperado:** a T el formulario desaparece en todas las ventanas; en T + margen el navegador dispara la liquidación:
  el ganador ve «Te adjudicaste la propiedad», los demás «Adjudicado a otro postor»; en la base: lote adjudicado a la
  última puja, fila en `adjudicaciones`, remate finalizado, correos al adjudicatario y a la administración (en la cola).
  Es idempotente: 10 liquidaciones simultáneas → una sola adjudicación.
- **Evidencia:** `sala-real.mjs` · `MotorPujasTest::test_cierre_perezoso_adjudica_pasado_el_margen_de_forma_idempotente`, `…test_endpoint_de_estado_liquida_lo_vencido_y_no_se_cachea` · `SalaTest::test_cargar_la_sala_liquida_el_lote_vencido` · `NotificacionesTest::test_al_cerrar_avisa_al_adjudicatario_y_a_la_administracion` · concurrencia C y D.

### QA-54 · Cierre anticipado desde el panel
- [x] **OK en local**
- **Pasos:** `/admin/subastas/{id}/en-vivo` → «Cerrar este lote ahora» → motivo → «Cerrar lote».
- **Esperado:** adjudica la mejor puja hasta ese instante (supuesto vigente), corta las posteriores, guarda el motivo
  («Cierre anticipado: …» en la ficha) y la sala lo refleja.
- **Evidencia:** `panel-remates.mjs` («cierre anticipado…») · `RematesAdminTest::test_cerrar_ahora_en_vivo_adjudica_la_mejor_puja_con_el_motivo` · `MotorPujasTest::test_cierre_anticipado_adjudica_la_mejor_puja_y_corta_las_posteriores`.

### QA-55 · Lote sin pujas queda desierto
- [x] **OK en local**
- **Pasos:** lote abierto sin pujas; dejarlo vencer; `php artisan colliers:liquidar`.
- **Esperado:** «Lotes liquidados: 1», estado desierto, sin adjudicación.
- **Evidencia:** `MotorPujasTest::test_lote_sin_pujas_queda_desierto`.

### QA-56 · Paso al siguiente lote
- [x] **OK en local**
- **Pasos:** `php tools/comparar/sala-ayudante.php dos-lotes 40`; abrir `/remates/dos-lotes/sala` y esperar el cierre del lote 1.
- **Esperado:** cabecera «LOTE 1 DE 2»; al liquidar el lote 1 la sala pasa al lote 2 con aviso y ficha nueva, sin recargar;
  el lote 2 acepta pujas desde su precio base; el remate se finaliza al liquidar el último.
- **Evidencia:** `sala-real.mjs` · `MotorPujasTest::test_varios_lotes_con_horario_fijo_y_remate_finalizado_al_liquidar_el_ultimo`.

### QA-57 · El cronómetro usa la hora del servidor
- [x] **OK en local**
- **Pasos:** adelantar el reloj del equipo 7 minutos y abrir la sala; comparar con `/hora.php`.
- **Esperado:** el cronómetro muestra el tiempo real restante según el servidor, no según el equipo; `/hora.php`
  responde `{"servidor_ms":…}` sin caché y sin arrancar Laravel.
- **Evidencia:** `sala-real.mjs` («cronómetro con la hora del servidor aunque el equipo esté 7 min adelantado») · `MotorPujasTest::test_hora_del_servidor` · `RendimientoTest::test_hora_php_responde_sin_arrancar_laravel`, `…test_la_hora_de_recepcion_es_la_de_llegada_a_php_…`.

### QA-58 · Reconexión
- [x] **OK en local**
- **Pasos:** cortar la red de una ventana, pujar desde otra, reconectar.
- **Esperado:** sin conexión conserva el último estado; al volver recupera precio e historial actuales.
- **Evidencia:** `sala-real.mjs`.

### QA-59 · Sala en el celular
- [x] **OK en local**
- **Pasos:** sala a 375 px: barra fija inferior → hoja de puja → confirmar.
- **Esperado:** barra con el precio, hoja se cierra al confirmar, «VAS GANANDO», sin scroll horizontal.
- **Evidencia:** `sala-real.mjs` («móvil…»).

### QA-60 · Bloqueo de deploy con remate en curso
- [x] **OK en local** (el comando; el script del servidor va en la sección 6)
- **Pasos:** `php artisan colliers:puede-desplegar` con un remate en curso o por comenzar.
- **Esperado:** código de salida 75.
- **Evidencia:** `MotorPujasTest::test_deploy_se_bloquea_con_remate_en_curso_o_por_comenzar` · `InfraestructuraTest::test_puede_desplegar_responde_75_con_bloqueo_manual`.

---

## 4. Sitio público (Bloque N)

### QA-61 · Listado de remates
- [x] **OK en local**
- **Pasos:** abrir `/` sin sesión.
- **Esperado:** tarjetas de remates publicados, en vivo y cerrados reales; sin borradores, cancelados ni demostraciones;
  UF del día como referencia; destacado; usable a 375/760/1120/1440 px.
- **Evidencia:** `sitio-publico.mjs` · `SitioPublicoTest::test_listado_con_remates_reales_sin_borradores_cancelados_ni_demostraciones`.

### QA-62 · Filtros del listado
- [x] **OK en local**
- **Pasos:** combinar fecha, tipo, dormitorios, estacionamiento/bodega, rango de precio, región y comuna; limpiar filtros; buscador.
- **Esperado:** los resultados coinciden con cada filtro; región y comuna salen de los datos; limpiar restaura todo.
- **Evidencia:** `filtros-interaccion.mjs` (43).

### QA-63 · Ficha de remate próximo
- [x] **OK en local**
- **Pasos:** abrir `/remates/{slug}` de un próximo.
- **Esperado:** folio, ficha del activo y mandante reales, mapa cargado con enlace a Google Maps, documentos públicos
  descargables (los reservados a garantía aprobada dan 403 sin ella), calendario `.ics` descargable y la tarjeta de
  acceso según la cuenta del visitante («Necesitas una cuenta…», «Falta constituir la garantía», «Garantía en revisión»,
  «Estás habilitado para pujar»).
- **Evidencia:** `sitio-publico.mjs` · `SitioPublicoTest::test_detalle_proximo_con_ficha_mapa_visitas_documentos_y_calendario`, `…test_variantes_del_visitante_…`.

### QA-64 · Sala en modo espectador, sin poder pujar
- [x] **OK en local**
- **Pasos:** 1) Sin sesión, abrir `/remates/apoquindo` (en vivo). 2) Intentar `/remates/apoquindo/sala`. 3) Enviar una
  puja directa al endpoint. 4) Repetir 1 y 3 como postor aprobado sin garantía.
- **Esperado:** 1) precio, historial y transmisión visibles con «Solo puedes mirar este remate», sin botón de sala ni
  formulario de puja. 2) redirige a `/ingresar`. 3) 401. 4) sin botón de sala; el motor responde 422 `sin_garantia`.
  No se registra ninguna puja.
- **Evidencia:** `sitio-publico.mjs` («el espectador…», «postor sin garantía…») · `SitioPublicoTest::test_detalle_en_vivo_lee_solo_el_json_estatico_con_las_pujas_publicas`.

### QA-65 · El JSON de estado se actualiza en vivo
- [x] **OK en local**
- **Pasos:** abrir `/remates/apoquindo` sin sesión y `/tiempo-real/apoquindo.json` en otra pestaña;
  `php tools/comparar/sala-ayudante.php pujar apoquindo contacto@andes.test 199000000`.
- **Esperado:** en ~2 s el detalle muestra el precio nuevo y «Postor #N» arriba del historial sin recargar; el JSON
  tiene el estado nuevo, sin correos, nombres ni `user_id`; el espectador nunca llama a un endpoint PHP.
- **Evidencia:** `sitio-publico.mjs` («detalle en vivo…») · `sala-real.mjs` («…(JSON estático)») · `SitioPublicoTest::test_detalle_en_vivo_…` · `MotorPujasTest::test_puja_valida_…` (sin temporales, sin identidades).

### QA-66 · Remate cerrado en el sitio
- [x] **OK en local**
- **Pasos:** abrir el detalle de un remate cerrado del seeder a 375, 760, 1120 y 1440 px.
- **Esperado:** responde 200, sin scroll horizontal y con áreas táctiles ≥ 44 px.
- **Evidencia:** `sitio-publico.mjs` («detalle-cerrado…»).

### QA-67 · «Avísame» y baja
- [x] **OK en local**
- **Pasos:** en el listado «Suscribirme» con un correo; en un próximo «Avísame»; publicar un remate; abrir el enlace de baja del correo.
- **Esperado:** suscripción guardada; correo al publicar; la baja muestra confirmación y no vuelve a enviar.
- **Evidencia:** `sitio-publico.mjs` · `NotificacionesTest::test_avisame_publicacion_y_baja`.

---

## 5. Notificaciones e infraestructura comprobables en local (Bloques M y B)

### QA-68 · Correos de revisión en español y bitácora
- [x] **OK en local**
- **Esperado:** aprobación/rechazo de cuenta y garantía, comprobante recibido: en español, por la cola, con fila en `notificaciones_log`.
- **Evidencia:** `NotificacionesTest::test_revision_de_cuenta_y_garantia_envia_correos_en_espanol_y_los_registra`.

### QA-69 · Recordatorios una sola vez
- [x] **OK en local**
- **Esperado:** `colliers:recordatorios` avisa N horas antes del inicio y no repite.
- **Evidencia:** `NotificacionesTest::test_recordatorios_antes_del_remate_una_sola_vez`.

### QA-70 · Remates de demostración no envían correos
- [x] **OK en local**
- **Evidencia:** `NotificacionesTest::test_los_remates_de_demostracion_no_envian_correos` · `RemateDemoTest` (se niega en producción).

### QA-71 · Programador de tareas
- [x] **OK en local**
- **Esperado:** `php artisan schedule:list` muestra latido, `colliers:liquidar` y la cola `--stop-when-empty` cada minuto, `colliers:actualizar-uf` cada hora y `colliers:recordatorios` cada 10 min.
- **Evidencia:** `InfraestructuraTest::test_programador_incluye_latido_y_cola` · `schedule:list` ejecutado el 17/09.

### QA-72 · Clave de acceso al sandbox
- [x] **OK en local**
- **Esperado:** con `COLLIERS_ACCESO_CLAVE` todo el sitio (incluido `/admin`) pide la clave; incorrecta no entra; correcta deja cookie.
- **Evidencia:** `InfraestructuraTest` (4 pruebas de clave de acceso).

### QA-73 · Instalación y diagnóstico
- [x] **OK en local**
- **Esperado:** `colliers:instalar` idempotente y sin administrador con clave corta; `colliers:diagnostico` revisa la base.
- **Evidencia:** `InfraestructuraTest::test_instalar_…`, `…test_diagnostico_se_ejecuta_y_revisa_la_base`.

### QA-74 · Fechas en UTC y montos enteros
- [x] **OK en local**
- **Esperado:** hora de Chile en pantalla, UTC en la base; montos en pesos enteros.
- **Evidencia:** `ModeloDatosTest::test_fechas_se_guardan_en_utc_aunque_lleguen_en_hora_de_santiago` · `RematesAdminTest::test_fotos_visitas_…` (visita 11:00 Chile → 14:00 UTC).

---

## 5b. Reportes (Bloque O, agregado el 17/09 después del QA)

### QA-75 · Reportes con datos reales
- [x] **OK en local**
- **Pasos:** `/admin/reportes` como administración; cambiar el período a otro mes, a «Todo el historial» y a un mes sin cierres; entrar como martillero y como postor.
- **Esperado:** sin aviso de datos de ejemplo; KPIs, tabla, participación, dinámica y categorías calculados de los remates cerrados (sin demostraciones ni cancelados); mes sin cierres: «Sin remates cerrados en …»; período inválido: 404; el martillero ve la pantalla; postor: 403; ningún correo ni nombre de postor.
- **Evidencia:** `reportes.mjs` · `ReportesTest::test_desempeno_participacion_dinamica_y_categorias_del_mes`, `…test_periodos_en_hora_de_chile_…`, `…test_pantalla_con_datos_reales_…`.

### QA-76 · Exportables XLSX y CSV
- [x] **OK en local**
- **Pasos:** «XLSX», «CSV» y cada «Descargar»; abrir los archivos.
- **Esperado:** XLSX con 6 hojas (Resumen, Desempeño comercial, Por categoría, Participación, Pujas, Garantías), montos numéricos con separador de miles, postores como «Postor #N»; CSV con punto y coma y BOM (tildes correctas en Excel). El paquete de Excel no se carga en las demás pantallas.
- **Evidencia:** `reportes.mjs` · `ReportesTest::test_exportables_xlsx_y_csv_sin_identidades`, `…test_el_paquete_de_excel_no_se_carga_en_las_demas_peticiones` · exportación comprobada también con `config:cache`, como en el servidor.

### S-12 · Descargas de reportes en el sandbox
- [ ] **Solo en sandbox** — `colliers:diagnostico` sin avisos en las extensiones nuevas (`iconv`, `simplexml`, `xmlreader`, `xmlwriter`, `zlib`) y las descargas XLSX/CSV funcionan.

## 5c. Ingreso con la clave del sandbox (bug del 17/09)

### QA-77 · El ingreso nunca falla en silencio
- [x] **OK en local** (servidor con `APP_ENV=staging`, `SESSION_SECURE_COOKIE=true` y clave de acceso)
- **Pasos:** 1) Primera visita a `/admin/ingresar`: clave del sandbox. 2) Ingresar con un administrador con clave temporal. 3) Borrar la cookie `colliers_acceso` y recargar. 4) Simular que el navegador no guarda la sesión al ingresar. 5) Clave incorrecta. 6) Borrar la cookie de la clave sin sesión y enviar el formulario.
- **Esperado:** 1) `/acceso` sin aviso y vuelve al formulario. 2) Llega a «Cambia tu contraseña». 3) No lo expulsa y recibe la cookie de nuevo. 4) Vuelve a `/admin/ingresar?sesion=perdida` con «Tu usuario y contraseña son correctos, pero el navegador no conservó la sesión…» y el log tiene «Ingreso sin sesión». 5) «Usuario o contraseña incorrectos…». 6) `/acceso` con «Tu acceso a este sitio de prueba venció…» y, con la clave, vuelve al formulario.
- **Evidencia:** `tools/comparar/ingreso-sandbox.mjs` (11) · `AutenticacionTest::test_ingreso_que_pierde_la_sesion_nunca_termina_en_silencio` · `InfraestructuraTest::test_la_clave_del_sandbox_no_expulsa_…`, `…test_la_clave_del_sandbox_nunca_bloquea_en_silencio`, `…test_sesion_vencida_en_una_accion_por_fetch_responde_en_espanol`.

### S-13 · Ingreso de administración en el sandbox
- [ ] **Solo en sandbox** — entrar por `/admin/ingresar` y llegar a «Cambia tu contraseña». Si la pantalla vuelve al acceso, ahora dice por qué; pasar la línea «Ingreso sin sesión» de `storage/logs/laravel-AAAA-MM-DD.log`.

## 6. Solo en sandbox (sin marcar)

Estos dependen del hosting real (LiteSpeed, cron, correo saliente, límites de PHP) y no se pueden dar por probados en
local. Pasos detallados en `docs/MANUAL-DE-PRUEBAS.md`.

### S-01 · Entrega real de correos por SMTP
- [ ] **Solo en sandbox**
- **Qué:** que los correos de confirmación, recuperación, aprobación/rechazo, comprobante, adjudicación, «Avísame» y recordatorios lleguen a una casilla real (no spam), con enlaces al dominio del sandbox.
- **Esperado:** llegan en ≤ 1–2 min (cola por cron) y `notificaciones_log` los marca «enviado».

### S-02 · Botón «Probar envío» del SMTP
- [ ] **Solo en sandbox** — `/admin/configuracion` → datos SMTP reales → probar → llega el correo de prueba.

### S-03 · Cron: cola y tareas programadas
- [ ] **Solo en sandbox** — `schedule:run` cada minuto: la cola se vacía, el latido se actualiza en `colliers:diagnostico`, los lotes vencidos se liquidan sin nadie mirando, los recordatorios salen a su hora y la UF se actualiza cada hora.

### S-04 · JSON estático servido por LiteSpeed
- [ ] **Solo en sandbox** — cabeceras sin caché ya verificadas por Jonas el 17/09 (`no-store`, sin ETag, 403 al `.htaccess`); falta verlo actualizarse durante un remate real con varias ventanas.

### S-05 · Muchos espectadores sobre el JSON
- [ ] **Solo en sandbox** — varias decenas de pestañas/equipos mirando `/remates/{slug}` en vivo sin errores ni lentitud del sitio.

### S-06 · 10 postores simultáneos en el hosting sin OPcache
- [ ] **Solo en sandbox** — con el margen en 5 s: tiempos de confirmación de puja (en local sin OPcache y 1 núcleo: mediana ~3,4–4,8 s, máx ~7,4 s), sin errores 5xx y sin pujas perdidas al cierre. Riesgo del Bloque R.

### S-07 · Hora del servidor
- [ ] **Solo en sandbox** — `/hora.php` responde rápido desde LiteSpeed y coincide con la hora oficial (NTP del hosting).

### S-08 · UF automática
- [ ] **Solo en sandbox** — el servidor puede salir por HTTP a mindicador.cl y la UF del día aparece en el listado.

### S-09 · Límites de subida del hosting
- [ ] **Solo en sandbox** — fotos de hasta 15 MB, documentos y comprobantes se suben sin error de `upload_max_filesize`/`post_max_size`, y la reducción de fotos no agota la memoria.

### S-10 · Bloqueo del script de deploy con remate en curso
- [ ] **Solo en sandbox** — con un remate en curso, un push no se despliega (`~/scripts/deploy-colliers.log` muestra el aviso y reintenta).

### S-11 · Entorno `staging`
- [ ] **Solo en sandbox** — `APP_ENV=staging`: `/revision` y `?demo=1` no existen, el seeder de desarrollo se niega y `colliers:remate-demo` funciona.
