<?php

/*
 * Sala de pujas (vista, sala.js y motivos de rechazo del motor). Sitio en inglés, 20/10.
 * Los marcadores (:monto, :hora…) los reemplaza Laravel en PHP y t() en el JavaScript (resources/js/modulos/textos.js).
 */
return [
    'titulo' => 'Sala de pujas',

    'cabecera' => [
        'garantia_aprobada' => 'GARANTÍA APROBADA',
        'ir_a_mi_cuenta' => 'Ir a Mi cuenta',
    ],

    'video' => [
        'titulo' => 'Transmisión en vivo del remate',
        'canal' => 'Transmisión del canal de Colliers Chile',
        'martillero' => 'Martillero',
        'oficial' => 'Precio y cronómetro oficiales: el video tiene 10–30 s de retraso',
    ],

    'ficha' => [
        'ver_antecedentes' => 'Ver antecedentes',
        'precio_base' => 'PRECIO BASE',
        'incremento_minimo' => 'INCREMENTO MÍNIMO',
        'tu_garantia' => 'TU GARANTÍA',
        'referencia_uf' => 'REFERENCIA UF',
        // Línea de características del lote (comuna, tipo, superficie, dormitorios y baños, ocupación).
        'superficie' => ':superficie m² útiles',
        'dormitorios_banos' => ':dormitoriosD / :banosB',
    ],

    'historial' => [
        'titulo' => 'Historial de pujas',
        // :total y :conectados llegan como HTML de la vista (contadores en vivo).
        'conteo' => ':total POSTURAS · TIEMPO REAL:conectados',
        'nota' => 'El remate cierra automáticamente al vencer el temporizador, sin extensiones. Colliers puede cerrarlo de forma anticipada.',
    ],

    'panel' => [
        'precio_actual' => 'PRECIO ACTUAL',
        // :base y :sobre llegan como HTML de la vista.
        'base' => 'Base :base · :sobre sobre el mínimo',
        'cierra_en' => 'CIERRA EN',
        'horas' => 'HORAS',
        'min' => 'MIN',
        'seg' => 'SEG',
        'sin_extension' => 'Sin extensiones por posturas de último minuto.',
        'ver_remates' => 'Ver remates publicados',
    ],

    'soporte' => [
        'titulo' => 'SOPORTE DURANTE EL REMATE',
        'texto' => 'Si se corta la transmisión o tienes un problema para pujar, llámanos: el remate no se detiene.',
    ],

    'barra' => [
        'aria' => 'Estado de la puja',
        'finalizado' => 'Finalizado',
        'mi_cuenta' => 'Mi cuenta',
        'remates' => 'Remates',
        'pujar' => 'Pujar',
    ],

    'hoja' => [
        'titulo' => 'Ingresar puja',
        'cerrar' => 'Cerrar',
        // :precio y :tiempo llegan como HTML de la vista.
        'resumen' => 'Precio actual :precio · cierra en :tiempo',
    ],

    'modal' => [
        'titulo' => 'Confirma tu puja',
        'propiedad' => 'Vas a ofertar por :direccion',
        // :diferencia llega como HTML de la vista.
        'supera' => 'supera en :diferencia la puja actual',
        'aviso' => 'La postura es irrevocable y compromete tu garantía de :garantia. Si resultas adjudicatario, deberás suscribir la escritura y pagar el saldo en el plazo de las bases. El remate cierra automáticamente al vencer el tiempo.',
        'confirmar' => 'Confirmar puja',
        'cancelar' => 'Cancelar',
    ],

    'avisos' => [
        'mensaje_martillero' => 'MENSAJE DEL MARTILLERO',
    ],

    'formulario' => [
        'puja_rapida' => 'PUJA RÁPIDA',
        'monto_libre' => 'MONTO LIBRE (CLP)',
        'nota' => 'Las posturas se cursan en pesos y son irrevocables. Se te pedirá confirmar antes de registrarlas.',
    ],

    // Motivos de rechazo del motor (PujaRechazada). El código del motivo no cambia; solo el texto que lee el postor.
    'rechazo' => [
        'monto_invalido' => 'El monto debe ser un número entero de pesos, sin puntos ni decimales.',
        'lote_inexistente' => 'Ese lote no existe en este remate.',
        'remate_no_disponible' => 'Este remate no está disponible para pujar.',
        'remate_cancelado' => 'El remate fue cancelado: ya no recibe pujas.',
        'remate_no_publicado' => 'Este remate todavía no está publicado.',
        'lote_no_abierto' => 'El lote todavía no abre.',
        'lote_cerrado' => 'El lote ya cerró: tu puja llegó después de la hora de cierre.',
        'cuenta_no_habilitada' => 'Tu cuenta no está habilitada para pujar.',
        'sin_garantia' => 'No tienes una garantía aprobada para este remate.',
        'ya_vas_ganando' => 'Ya tienes la puja más alta: espera a que alguien te supere.',
        'monto_insuficiente' => 'El monto es menor que la puja mínima.',
        'generico' => 'Puja rechazada.',

        'lote_cerrado_sin_hora' => 'El lote ya cerró.',
        'lote_cerrado_a_las' => 'El lote cerró a las :hora (hora de Santiago): tu puja llegó después.',
        'ya_adjudicado' => ' Ya está adjudicado.',
        'lote_abre_a_las' => 'El lote abre a las :hora (hora de Santiago): todavía no se puede pujar.',
        'monto_menor_que' => 'El monto es menor que la puja mínima: :minima.',

        'cuenta' => [
            'registrado' => 'Todavía no confirmas tu correo: revisa tu bandeja para activar la cuenta.',
            'en_revision' => 'Colliers todavía está revisando tu cuenta: te avisamos por correo cuando quede aprobada.',
            'rechazado' => 'Tu cuenta fue rechazada: revisa el motivo en «Mi cuenta».',
            'bloqueado' => 'Tu cuenta está bloqueada: escríbenos para revisarla.',
            'sin_postor' => 'Esta cuenta no es de postor: las pujas se hacen con una cuenta de postor.',
            'inactiva' => 'Tu cuenta está deshabilitada.',
        ],

        'garantia' => [
            'pendiente' => 'Tu garantía está pendiente: sube el comprobante en «Mi cuenta» para que Colliers la revise.',
            'en_revision' => 'Tu garantía está en revisión: te avisamos por correo apenas quede aprobada.',
            'rechazada' => 'Tu garantía fue rechazada: revisa el motivo en «Mi cuenta» y sube otro comprobante.',
            'sin_inscripcion' => 'No estás inscrito en este remate: inscríbete y constituye la garantía para poder pujar.',
        ],
    ],

    // Textos de resources/js/modulos/sala.js: t('sala.<clave>').
    // Alias del postor en el feed («Postor #N» viaja como dato en el JSON público; se muestra traducido).
    'alias' => 'Postor #:n',
    // Banda de sin conexión (QA 21/10).
    'conexion' => [
        'titulo' => 'Sin conexión.',
        'texto' => 'El precio y el cronómetro pueden no estar actualizados. Pujar queda bloqueado hasta reconectar.',
        'barra' => 'SIN CONEXIÓN',
    ],

    'js' => [
        'alias' => 'Postor #:n',
        'conectado' => ':n CONECTADO',
        'conectados' => ':n CONECTADOS',
        'lote_de' => 'Lote :orden de :total',

        // Cambio de lote. :direccion llega como «: Av. …» o vacío.
        'lote_sin_posturas' => 'El lote :orden cerró sin posturas.',
        'lote_ganado' => 'Te adjudicaste el lote :orden en :precio. Colliers te contactará para la firma y el pago del saldo.',
        'lote_de_otro' => 'El lote :orden se adjudicó a otro postor en :precio.',
        'ahora_abre' => 'Ahora se remata el lote :orden:direccion, abre a las :hora.',
        'ahora_en_vivo' => 'Ahora se remata el lote :orden:direccion, en vivo.',
        'cambio_de_lote' => 'CAMBIO DE LOTE · AHORA :lote',

        'hace_seg' => 'hace :n seg',
        'hace_min' => 'hace :n min',
        'hace_h' => 'hace :n h',

        'abre_en_etiqueta' => 'ABRE EN',
        'cierra_en_etiqueta' => 'CIERRA EN',

        'estado' => [
            'cancelado' => 'REMATE CANCELADO',
            'no_disponible' => 'REMATE NO DISPONIBLE',
            'adjudicado' => 'ADJUDICADO',
            'desierto' => 'CERRADO SIN POSTORES',
            'proximo' => 'PRÓXIMO · PUJA AÚN NO ABRE',
            'adjudicando' => 'CERRADO · ADJUDICANDO',
            'en_vivo' => 'EN VIVO · PUJA ABIERTA',
            'finalizado' => 'REMATE FINALIZADO',
        ],

        'tiempo' => [
            'cancelado' => 'Remate cancelado',
            'no_disponible' => 'Remate no disponible',
            'finalizado' => 'Remate finalizado',
            'abre_en' => 'Abre en :tiempo',
            'cerrado' => 'Cerrado',
            'cierra_en' => 'Cierra en :tiempo',
        ],

        'sin_posturas' => 'Sin posturas',
        'vas_ganando' => 'Vas ganando',
        'te_superaron' => 'Te superaron',
        'detalle_sin_posturas' => 'Nadie ha pujado todavía. La primera postura parte en :monto.',
        'detalle_ganando' => 'Tu postura es la más alta registrada en este remate.',
        'detalle_superado' => 'La puja más alta es de otro postor. Puedes ofertar desde :monto.',

        'ayuda_abre' => 'La puja abre en :tiempo.',
        'ayuda_sin_conexion' => 'Sin conexión: espera a que la sala se reconecte para pujar.',
        'ayuda_ganando' => 'Tienes la puja más alta: espera a que otro postor la supere.',
        'ayuda_minimo' => 'La postura debe ser al menos :monto.',
        'ayuda_incremento' => 'Incremento mínimo :monto.',
        'ayuda_incremento_uf' => 'Incremento mínimo :monto. Referencia: :uf.',
        'placeholder' => 'Mínimo :monto',
        'enviando' => 'Enviando…',
        'pujar' => 'Pujar :monto',

        'error' => [
            'rechazada' => 'La puja fue rechazada.',
            'sesion' => 'Tu sesión expiró. Recarga la página para seguir pujando.',
            'acceso' => 'No tienes acceso para pujar. Recarga la página.',
            'seguido' => 'Estás enviando pujas muy seguido. Espera unos segundos.',
            'no_confirmada' => 'No pudimos confirmar tu puja. Revisa el historial: si no aparece, vuelve a intentarlo.',
            'sin_conexion' => 'Sin conexión: no sabemos si la puja llegó. Revisa el historial antes de reintentar.',
        ],

        'postor_tu' => ':postor (tú)',

        'resultado' => [
            'cancelado_titulo' => 'Colliers canceló este remate',
            'no_disponible_titulo' => 'Este remate ya no está disponible',
            'cerrado_titulo' => 'Remate cerrado',
            'finalizado_titulo' => 'Remate finalizado',
            'desierto_titulo' => 'El remate se cerró sin posturas',
            'ganado_titulo' => 'Te adjudicaste la propiedad',
            'otro_titulo' => 'Adjudicado a otro postor',
            'cancelado_texto' => 'No se reciben pujas. Tus remates y el estado de tu garantía están en «Mi cuenta».',
            'no_disponible_texto' => 'Colliers retiró este remate: no se reciben pujas.',
            'cerrado_texto' => 'Estamos confirmando la adjudicación con las posturas recibidas antes del cierre.',
            'desierto_texto' => 'No se registraron posturas sobre el precio base. La propiedad se publicará en un remate nuevo, con fecha y condiciones propias.',
            'ganado_texto' => 'Precio final :precio. Colliers te contactará para la firma y el pago del saldo, según las bases del remate.',
            'otro_texto' => 'Precio final :precio. Colliers te informará sobre tu garantía según las bases del remate.',
        ],
    ],
];
