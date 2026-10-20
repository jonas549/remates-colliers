<?php

// Ficha pública de un remate (resources/views/remates/show.blade.php) y su JavaScript (detalle.js).
// Los datos del remate (dirección, ficha, resultado, estados) vienen del grupo «catalogo» o del administrador.

return [
    'limite_defecto' => 'el inicio del remate',

    'correo' => [
        'asunto_visita' => 'Visita a :direccion (:folio)',
        'asunto_consulta' => 'Consulta por el remate :folio',
    ],

    // Recuadro de bloqueo según la cuenta y la garantía del visitante para ESTE remate.
    'bloqueo' => [
        'vivo' => [
            'visitante' => [
                'titulo' => 'Solo puedes mirar este remate',
                'texto' => 'Para pujar necesitas una cuenta y una garantía aprobada por Colliers. La transmisión y el historial son públicos.',
            ],
            'registrado' => [
                'titulo' => 'Garantía no constituida',
                'texto' => 'Tu cuenta está activa, pero no registras una garantía aprobada para este remate. Sin ella no puedes ingresar posturas.',
            ],
            'en_revision' => [
                'titulo' => 'Garantía en revisión',
                'texto' => 'Recibimos tu comprobante y lo estamos revisando de forma manual. Te avisaremos por correo apenas quede aprobada.',
            ],
            'aprobada' => [
                'titulo' => 'Estás habilitado para pujar',
                'texto' => 'Tu garantía fue aprobada para este remate. Entra a la sala de pujas para ingresar posturas en pesos.',
            ],
        ],
        'proximo' => [
            'visitante' => [
                'titulo' => 'Necesitas una cuenta para pujar',
                'texto' => 'Puedes revisar todos los antecedentes sin registrarte. Para ingresar posturas debes crear una cuenta y constituir la garantía.',
            ],
            'registrado' => [
                'titulo' => 'Falta constituir la garantía',
                'texto' => 'Vale a la vista o transferencia por :monto, fuera de la plataforma. Debe estar aprobada antes del :limite.',
            ],
            'en_revision' => [
                'titulo' => 'Garantía en revisión',
                'texto' => 'Recibimos tu comprobante y lo revisamos de forma manual dentro de :horas horas hábiles. Te avisaremos por correo.',
            ],
            'aprobada' => [
                'titulo' => 'Estás habilitado para pujar',
                'texto' => 'Tu garantía fue aprobada para este remate. Podrás ingresar posturas cuando comience la transmisión.',
            ],
        ],
        'cta' => [
            'crear_cuenta' => 'Crear cuenta',
            'ver_mi_cuenta' => 'Ver mi cuenta',
            'ver_estado_garantia' => 'Ver estado de la garantía',
            'entrar_sala' => 'Entrar a la sala de pujas',
            'constituir_garantia' => 'Constituir la garantía',
            'recordarme' => 'Recordarme al comenzar',
        ],
    ],

    'pasos' => [
        'titulo' => 'Pasos para participar',
        'cuenta' => ['titulo' => 'Crea tu cuenta', 'texto' => 'Con tu RUT y datos de contacto.'],
        'garantia' => ['titulo' => 'Constituye la garantía', 'texto' => 'Vale a la vista o transferencia por :monto, fuera de la plataforma.'],
        'aprobacion' => ['titulo' => 'Espera la aprobación', 'texto' => 'Colliers revisa el comprobante de forma manual y marca tu inscripción como aprobada.'],
        'puja' => ['titulo' => 'Puja durante la transmisión', 'texto' => 'En pesos, con incrementos mínimos de :monto. Cierra automáticamente al vencer el tiempo.'],
    ],

    // Franja bajo la navegación.
    'ticker' => [
        'en_vivo' => 'EN VIVO',
        'no_disponible' => 'NO DISPONIBLE',
        'cerrado' => 'CERRADO',
        'cerrando' => 'CERRANDO',
        'cierra_en' => 'cierra en',
        'retirado' => 'el remate ya no está disponible',
        'adjudicando' => 'el lote cerró: se está adjudicando',
        'lote_cerro' => 'el lote cerró',
        'puja_actual' => 'Puja actual',
        'remate_cerrado' => 'REMATE CERRADO',
        'proximo_remate' => 'PRÓXIMO REMATE',
        'comienza_el' => 'comienza el',
        'garantias_hasta' => 'Garantías hasta el',
    ],

    'migas' => [
        'inicio' => 'Inicio',
        'remates' => 'Remates',
    ],

    'cabeza' => [
        'folio' => 'REMATE N.º',
        'bases' => 'Bases y condiciones',
        'compartir' => 'Compartir',
    ],

    'video' => [
        'titulo' => 'Transmisión en vivo del remate',
        'foto_principal' => 'Foto principal de la propiedad',
        'canal' => 'Transmisión del canal de Colliers Chile',
        'martillero' => 'Martillero:',
        'ver_fotos' => 'Ver las :total fotos',
    ],

    'transmision' => [
        'se_habilita' => 'La transmisión se habilita el',
        'texto' => 'El remate se emite en vivo por el canal de YouTube de Colliers Chile. El reproductor aparecerá en esta misma página al comenzar, y el historial de pujas se abrirá junto con él.',
        'calendario' => 'Agregar a mi calendario',
        'ver_canal' => 'Ver el canal de Colliers',
    ],

    'secciones' => [
        'lotes' => 'Lotes de este remate',
        'lote' => 'Lote :orden · :direccion',
        'descripcion' => 'Descripción',
        'caracteristicas' => 'Características generales',
        'adicional' => 'Información adicional',
        'mandante' => 'Antecedentes del mandante',
        'ubicacion' => 'Ubicación',
        'google_maps' => 'Abrir en Google Maps',
        'condiciones' => 'Condiciones del remate',
        'condicion_pesos' => 'Las posturas se ingresan en pesos chilenos. El valor de la UF se muestra solo como referencia informativa y no determina el monto de las pujas ni de la garantía.',
        'condicion_cierre' => 'El remate cierra automáticamente al vencer el temporizador, sin extensiones por posturas de último minuto. Colliers puede cerrar el remate de forma anticipada.',
    ],

    'visitas' => [
        'titulo' => 'Horarios de visita',
        'sin_horarios' => 'Sin horarios publicados',
        'coordinala' => 'Coordínala con Colliers',
        'coordinar' => 'Coordinar visita',
    ],

    // Recuadro lateral con el precio, el contador y la llamada a la acción.
    'caja' => [
        'en_curso' => 'REMATE EN CURSO',
        'remate_cerrado' => 'REMATE CERRADO',
        'comienza_en' => 'COMIENZA EN',
        'puja_actual' => 'PUJA ACTUAL',
        'pujas_ultima' => ':total pujas · última hace :hace',
        'precio_base' => 'PRECIO BASE',
        'incremento_minimo' => 'INCREMENTO MÍNIMO',
        'garantia' => 'GARANTÍA',
        'referencia_uf' => 'REFERENCIA UF',
        'cerrado' => 'CERRADO',
        'cierra_en' => 'CIERRA EN',
        'retirado' => 'Este remate ya no está disponible',
        'adjudicando' => 'Adjudicando…',
        'cerrado_aviso' => 'Remate cerrado',
        'resultado' => 'RESULTADO',
        'precio_base_monto' => 'Precio base :monto',
        'referencia' => 'Referencia:',
        'sin_pujas_aun' => 'aún no hay pujas',
        'inicio' => 'INICIO',
        'cierre_garantias' => 'CIERRE DE GARANTÍAS',
        'hasta_inicio' => 'Hasta el inicio',
        'lote_adjudicando' => 'El lote cerró: se está adjudicando.',
        'lote_cerro' => 'El lote cerró.',
        'te_avisaremos' => 'Te avisaremos',
        'estado_garantia' => 'Ver el estado de mi garantía',
        'como_garantia' => 'Cómo constituir la garantía',
        'contactar' => 'Contactar al ejecutivo',
        'avisame' => 'Avísame antes de que comience',
        'correo_ejemplo' => 'tu@correo.cl',
        'correo_aviso' => 'Correo para el aviso',
        'avisarme' => 'Avisarme',
    ],

    'contador' => [
        'dias' => 'DÍAS',
        'horas' => 'HORAS',
        'min' => 'MIN',
        'seg' => 'SEG',
    ],

    'historial' => [
        'titulo' => 'Historial de pujas',
        'actualiza' => 'Actualiza solo',
        'nota' => 'Los postores se identifican con un número asignado por el sistema.',
    ],

    'documentos' => [
        'titulo' => 'Documentos disponibles',
        'con_garantia' => 'Con garantía aprobada',
        'en_preparacion' => 'Documentos en preparación',
        'pidelos' => 'Pídelos a Colliers',
        'nota' => 'Los antecedentes legales completos se entregan a los postores con garantía aprobada.',
    ],

    'ayuda' => [
        'titulo' => '¿Necesitas ayuda?',
        'texto' => 'El equipo de remates resuelve dudas sobre la propiedad, la garantía y el procedimiento.',
    ],

    'recomendados' => [
        'titulo' => 'Otros remates que podrían interesarte',
        'ver_todos' => 'Ver todos los remates',
        'precio_base' => 'PRECIO BASE',
        'remate' => 'REMATE',
        'garantia' => 'GARANTÍA',
        'ver' => 'Ver remate',
    ],

    // detalle.js
    'js' => [
        'seg' => ':n seg',
        'min' => ':n min',
        'h' => ':n h',
        'no_disponible' => 'REMATE NO DISPONIBLE',
        'adjudicado' => 'REMATE ADJUDICADO',
        'cerrado' => 'REMATE CERRADO',
        'cerrado_adjudicando' => 'CERRADO · ADJUDICANDO',
        'sin_pujas' => 'Sin pujas',
        'hace' => 'hace :tiempo',
        'enlace_copiado' => 'Enlace copiado',
        'listo' => 'Listo.',
        'error_correo' => 'No pudimos registrar tu correo.',
        'sin_conexion' => 'Sin conexión: inténtalo de nuevo.',
    ],
];
