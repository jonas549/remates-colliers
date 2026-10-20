<?php

/*
 * Área del postor: «Mi cuenta» (estado de la cuenta y de la garantía), sesiones activas, inscripción en un remate,
 * comprobante de la garantía y baja del «Avísame». Los datos que carga el administrador (banco, títulos, motivos)
 * llegan como marcadores y se muestran tal cual.
 */
return [
    'nav' => [
        'titulo' => 'Mi cuenta',
        'etiqueta' => 'MI CUENTA',
        'remates' => 'Remates',
        'cerrar_sesion' => 'Cerrar sesión',
    ],

    'aviso_prueba' => 'Esta es una cuenta de prueba creada desde el panel de Colliers. Su remate de prueba no aparece en el listado público de remates.',
    'aviso_prueba_aprobada' => 'Esta es una cuenta de prueba creada desde el panel de Colliers. Su remate de prueba no aparece en el listado público de remates: se entra desde esta página, con el botón «Entrar a la sala de pujas».',

    // Banda superior y pasos según el estado de la cuenta y de la garantía.
    'bandas' => [
        'cuenta_revision' => [
            'etiqueta' => 'CUENTA EN REVISIÓN',
            'titulo' => 'Estamos revisando tu registro',
            'texto' => 'Recibimos tus antecedentes. Un ejecutivo de Colliers los valida de forma manual y te informará por correo, normalmente dentro de :horas horas hábiles. Hasta entonces no puedes inscribirte en un remate.',
        ],
        'cuenta_rechazada' => [
            'etiqueta' => 'CUENTA NO APROBADA',
            'titulo' => 'No pudimos aprobar tu cuenta',
            'texto' => 'Colliers revisó tus antecedentes y no aprobó la cuenta. Escríbenos si quieres corregir algún dato.',
            'texto_motivo' => 'Colliers revisó tus antecedentes y no aprobó la cuenta: :motivo Escríbenos si quieres corregir algún dato.',
        ],
        'cuenta_bloqueada' => [
            'etiqueta' => 'CUENTA BLOQUEADA',
            'titulo' => 'Tu cuenta está bloqueada',
            'texto' => 'No puedes inscribirte ni pujar mientras la cuenta esté bloqueada. Escríbenos para revisarlo.',
            'texto_motivo' => 'No puedes inscribirte ni pujar mientras la cuenta esté bloqueada (:motivo). Escríbenos para revisarlo.',
        ],
        'sin_inscripcion' => [
            'etiqueta' => 'CUENTA APROBADA · SIN INSCRIPCIONES',
            'titulo' => 'Tu cuenta está aprobada',
            'texto' => 'Para pujar, inscríbete en un remate y constituye su garantía por vale a la vista o transferencia. Es un trámite externo a la plataforma: Colliers lo revisa y marca tu inscripción como aprobada o rechazada.',
            'texto_unica' => 'Para pujar necesitas constituir la habilitación por vale a la vista o transferencia: con ella quedas habilitado en todos los remates. Es un trámite externo a la plataforma, que Colliers revisa y aprueba.',
            'texto_unica_monto' => 'Para pujar necesitas constituir la habilitación de :monto por vale a la vista o transferencia: con ella quedas habilitado en todos los remates. Es un trámite externo a la plataforma, que Colliers revisa y aprueba.',
        ],
        'garantia_pendiente' => [
            'etiqueta' => 'CUENTA APROBADA · GARANTÍA PENDIENTE',
            'titulo' => 'Tu cuenta está aprobada, falta la garantía',
            'texto' => 'Para pujar debes constituir la garantía por vale a la vista o transferencia y enviarnos el comprobante. Es un trámite externo a la plataforma: Colliers lo revisa y marca tu inscripción como aprobada o rechazada.',
        ],
        'garantia_revision' => [
            'etiqueta' => 'GARANTÍA EN REVISIÓN',
            'titulo' => 'Recibimos tu comprobante de garantía',
            'texto' => 'Estamos verificando el comprobante. Te avisaremos por correo apenas quede aprobada, normalmente dentro de :horas horas hábiles; recién ahí se habilita la sala de pujas para este remate.',
            'texto_unica' => 'Estamos verificando el comprobante. Te avisaremos por correo apenas quede aprobada, normalmente dentro de :horas horas hábiles; recién ahí se habilita la sala de pujas.',
        ],
        'aprobada' => [
            'etiqueta' => 'GARANTÍA APROBADA · HABILITADO PARA PUJAR',
            'titulo' => 'Estás habilitado para pujar',
            'texto' => 'Tu garantía por :monto fue aprobada para :remate. Podrás ingresar posturas en pesos cuando comience la transmisión del remate.',
            'texto_unica' => 'Tu habilitación por :monto fue aprobada: te sirve para todos los remates publicados. Podrás ingresar posturas en pesos cuando comience cada transmisión.',
        ],
        'remate_cancelado' => [
            'etiqueta' => 'REMATE CANCELADO',
            'titulo' => 'Colliers canceló este remate',
            'texto' => 'El remate :remate fue cancelado y no se realizará. No se reciben pujas. Si Colliers vuelve a rematar la propiedad, lo hará en un remate nuevo, con su propia inscripción. Tu garantía se rige por las bases del remate; escríbenos si tienes dudas.',
            'texto_motivo' => 'El remate :remate fue cancelado (:motivo) y no se realizará. No se reciben pujas. Si Colliers vuelve a rematar la propiedad, lo hará en un remate nuevo, con su propia inscripción. Tu garantía se rige por las bases del remate; escríbenos si tienes dudas.',
        ],
        'rechazada' => [
            'etiqueta' => 'GARANTÍA RECHAZADA',
            'titulo' => 'No pudimos validar tu garantía',
            'texto' => 'Colliers no aprobó el comprobante. Puedes corregirlo y volver a enviarlo antes del cierre de garantías, o escribirnos si crees que se trata de un error.',
            'texto_motivo' => 'Colliers no aprobó el comprobante: :motivo Puedes corregirlo y volver a enviarlo antes del cierre de garantías, o escribirnos si crees que se trata de un error.',
        ],
    ],

    // Estado de la garantía y de cada paso (etiquetas; los valores guardados no cambian).
    'estados' => [
        'no_iniciada' => 'No iniciada',
        'pendiente' => 'Pendiente',
        'en_revision' => 'En revisión',
        'aprobada' => 'Aprobada',
        'rechazada' => 'Rechazada',
        'bloqueada' => 'Bloqueada',
        'completado' => 'Completado',
        'remate_cancelado' => 'Remate cancelado',
        'habilitado' => 'Habilitado',
        'bloqueado' => 'Bloqueado',
    ],

    'pasos' => [
        'registro' => ['titulo' => 'Registro enviado', 'texto' => 'Datos personales y documentos recibidos.'],
        'cuenta' => ['titulo' => 'Cuenta aprobada', 'texto' => 'Revisión manual de antecedentes por Colliers.'],
        'garantia' => ['titulo' => 'Garantía constituida', 'texto' => 'Vale a la vista o transferencia, fuera de la plataforma.'],
        'habilitado' => ['titulo' => 'Habilitado para pujar', 'texto' => 'Se activa la sala de pujas del remate inscrito.'],
    ],

    'lateral' => [
        'titulo_habilitado' => 'TU PRÓXIMO REMATE',
        'titulo' => 'MIENTRAS TANTO',
        'texto_habilitado' => 'Accesos directos a la sala de pujas, al catálogo y a los antecedentes de la propiedad en la que estás inscrito.',
        'texto' => 'Puedes revisar el catálogo de remates y los antecedentes de cada propiedad. La sala de pujas se habilita solo con la garantía aprobada.',
        'ver_remates' => 'Ver remates publicados',
        'entrar_sala' => 'Entrar a la sala de pujas',
        'bases_generales' => 'Bases generales de los remates',
        'sesiones' => 'Sesiones activas y contraseña',
        'tus_inscripciones' => 'TUS INSCRIPCIONES',
        'contacto' => 'CONTACTO',
        'contacto_texto' => 'Si tu solicitud lleva más de :horas horas hábiles sin respuesta, escríbenos indicando tu RUT y el remate.',
    ],

    'garantia' => [
        'kicker_remate' => 'GARANTÍA DEL REMATE',
        'kicker_inscripcion' => 'INSCRIPCIÓN EN UN REMATE',
        'monto' => 'MONTO',
        'plazo' => 'PLAZO',
        'estado' => 'ESTADO',
        'plazo_unica' => 'Sin plazo: vale para todos los remates',
        'plazo_inicio' => 'Hasta el inicio',
        'medios' => [
            'vale_vista' => 'vale a la vista',
            'transferencia' => 'transferencia',
        ],
        'monto_unica' => ':monto (habilitación única)',
    ],

    'cancelado' => [
        'subtitulo' => 'REMATE CANCELADO',
        'texto' => 'Colliers canceló este remate. No se realizará y no se reciben pujas.',
        'texto_fecha' => 'Colliers canceló este remate el :fecha. No se realizará y no se reciben pujas.',
        'ver_remates' => 'Ver remates publicados',
        'ver_comprobante' => 'Ver comprobante enviado',
        'nota' => 'El destino de la garantía se rige por las bases del remate. Si tienes dudas, escríbenos a :correo.',
    ],

    'constituir' => [
        'subtitulo' => 'CÓMO CONSTITUIRLA',
        'vale' => 'Vale a la vista a nombre de :titular.',
        'vale_entregado' => 'Vale a la vista a nombre de :titular, entregado en :direccion.',
        'transferencia' => 'O transferencia a la :tipo :banco N.º :numero, indicando el número de remate.',
        'transferencia_rut' => 'O transferencia a la :tipo :banco N.º :numero, RUT :rut, indicando el número de remate.',
        'sin_banco' => 'Vale a la vista o transferencia por :monto. Escríbenos a :correo para recibir los datos de la cuenta y del vale.',
        'subir_o_enviar' => 'Sube aquí el comprobante, o envíalo a :correo con tu nombre y el remate al que postulas.',
        'subir' => 'Subir comprobante',
        'ver_antecedentes' => 'Ver antecedentes del remate',
        'medio' => 'MEDIO',
        'transferencia_opcion' => 'Transferencia',
        'vale_opcion' => 'Vale a la vista',
        'archivo' => 'COMPROBANTE (PDF, JPG O PNG · HASTA 10 MB)',
        'enviar' => 'Enviar comprobante',
        'cancelar' => 'Cancelar',
        'ver_comprobante_enviado' => 'Ver comprobante enviado',
        'nota' => 'La revisión es manual y no hay pagos en línea. Una vez aprobada quedas habilitado para pujar en ese remate; el destino de la garantía al terminar el remate se rige por sus bases.',
        'nota_recibido' => 'La revisión es manual y no hay pagos en línea. Una vez aprobada quedas habilitado para pujar en ese remate; el destino de la garantía al terminar el remate se rige por sus bases. Comprobante recibido el :fecha.',
    ],

    'aprobada' => [
        'subtitulo' => 'GARANTÍA RECIBIDA Y APROBADA',
        'garantia' => 'Garantía',
        // :medio es «Vale a la vista», «Transferencia» o «Garantía»; :monto va en negrita.
        'recibida' => ':medio por :monto, recibida por Colliers.',
        'recibida_fecha' => ':medio por :monto, recibido el :fecha.',
        'recibida_titular' => ':medio por :monto a nombre de :titular, recibida por Colliers.',
        'recibida_titular_fecha' => ':medio por :monto a nombre de :titular, recibido el :fecha.',
        'aprobada_el' => 'Aprobada por Colliers el :fecha. Quedas habilitado para pujar en este remate.',
        'en_vivo' => 'El remate está <strong>en vivo</strong>: entra a la sala de pujas.',
        'abre' => 'La sala de pujas se abre el :fecha, junto con la transmisión del remate.',
        'entrar_sala' => 'Entrar a la sala de pujas',
        'ver_comprobante' => 'Ver comprobante',
        'nota' => 'Si te adjudicas la propiedad, Colliers te contactará para la firma y el pago del saldo. El destino de la garantía se rige por las bases del remate.',
    ],

    'inscripcion' => [
        'habilitado_en_todos' => 'Con tu habilitación aprobada puedes pujar en cualquier remate publicado: no tienes que inscribirte uno por uno. Entra al remate que te interese y usa «Entrar a la sala de pujas».',
        'garantia' => 'garantía',
        'inscribirme' => 'Inscribirme',
        'nota' => 'Al inscribirte se calcula la garantía de ese remate y aquí verás cómo constituirla.',
        'cuando_apruebe' => 'Cuando Colliers apruebe tu cuenta podrás inscribirte en los remates publicados.',
        'sin_abiertos' => 'No hay remates abiertos a inscripción en este momento. Te avisaremos por correo cuando se publique uno nuevo.',
        // Mensajes tras inscribirse o subir el comprobante.
        'inscrito' => 'Quedaste inscrito en :folio. La garantía es de :monto: constitúyela y sube el comprobante.',
        'comprobante_recibido' => 'Recibimos tu comprobante. Colliers lo revisará y te avisará por correo.',
        'comprobante_max' => 'El comprobante puede pesar hasta 10 MB.',
        'atributo_medio' => 'medio',
        'atributo_comprobante' => 'comprobante',
        // Motivos por los que no se puede inscribir o subir el comprobante.
        'cuenta_no_aprobada' => 'Tu cuenta debe estar aprobada por Colliers para inscribirte en un remate.',
        'remate_de_prueba' => 'Este es un remate de prueba de Colliers: no recibe inscripciones. Revisa los remates publicados en el sitio.',
        'cuenta_de_prueba' => 'Esta es una cuenta de prueba: solo puede pujar en su remate de prueba, que ya aparece en «Mi cuenta».',
        'no_acepta' => 'Este remate no acepta inscripciones.',
        'sin_monto_unico' => 'Colliers todavía no fijó el monto de la habilitación. Escríbenos y te avisamos apenas esté.',
        'ya_aprobada' => 'Tu garantía ya está aprobada.',
        'ya_en_revision' => 'Ya recibimos tu comprobante y lo estamos revisando.',
        'plazo_cerrado' => 'El plazo para constituir la garantía de este remate ya cerró.',
        'ya_comenzo' => 'El remate ya comenzó: no se reciben más garantías.',
    ],

    'sesiones' => [
        'titulo' => 'Sesiones activas',
        'kicker' => 'SEGURIDAD DE LA CUENTA',
        'intro' => 'Dispositivos donde tu cuenta tiene la sesión abierta. Si no reconoces alguno, ciérralo y cambia tu contraseña.',
        'requiere_base' => 'La lista de sesiones requiere SESSION_DRIVER=database.',
        'este_dispositivo' => 'ESTE DISPOSITIVO',
        'ultima_actividad' => 'ÚLTIMA ACTIVIDAD :fecha',
        'otro_dispositivo' => 'Otro dispositivo',
        'navegador' => 'navegador',
        'programa_en_sistema' => ':programa en :sistema',
        'cerrar' => 'CERRAR',
        'volver_panel' => 'Volver al panel',
        'volver_cuenta' => 'Volver a mi cuenta',
        'cerrar_demas' => 'Cerrar las demás sesiones',
        'cambiar_clave' => 'Cambiar contraseña',
        'cerrar_sesion' => 'Cerrar sesión',
        'usar_cerrar_sesion' => 'Para cerrar la sesión actual usa «Cerrar sesión».',
        'cerrada' => 'Cerramos esa sesión.',
        'cerradas' => '{1} Cerramos 1 sesión.|[0,*] Cerramos :count sesiones.',
    ],

    // «Avísame» del sitio público y su baja desde el correo.
    'avisame' => [
        'atributo_email' => 'correo electrónico',
        'listo_remate' => 'Listo: te avisaremos por correo antes de que comience este remate.',
        'listo_nuevos' => 'Listo: te avisaremos por correo cuando se publique un remate nuevo.',
        'baja_titulo_pagina' => 'Avisos desactivados',
        'baja_etiqueta' => 'REMATES',
        'baja_kicker' => 'AVISOS POR CORREO',
        'baja_titulo' => 'Listo, no te enviaremos más avisos',
        'baja_recordatorio' => ':email ya no recibirá el recordatorio del remate :folio.',
        'baja_nuevos' => ':email ya no recibirá avisos de remates nuevos.',
        'baja_volver' => 'Puedes volver a suscribirte cuando quieras desde el sitio.',
        'baja_ver_remates' => 'Ver los remates',
    ],
];
