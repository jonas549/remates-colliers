<?php

// Public auction page (resources/views/remates/show.blade.php) and its JavaScript (detalle.js).
// Auction data (address, details, result, statuses) comes from the «catalogo» group or from the administrator.

return [
    'limite_defecto' => 'the start of the auction',

    // Subjects of emails sent to Colliers: they stay in Spanish (Jonas, 20/10).
    'correo' => [
        'asunto_visita' => 'Visita a :direccion (:folio)',
        'asunto_consulta' => 'Consulta por el remate :folio',
    ],

    // Notice box based on the visitor's account and bid deposit for THIS auction.
    'bloqueo' => [
        'vivo' => [
            'visitante' => [
                'titulo' => 'You can only watch this auction',
                'texto' => 'To bid you need an account and a bid deposit approved by Colliers. The broadcast and the bid history are public.',
            ],
            'registrado' => [
                'titulo' => 'No bid deposit',
                'texto' => 'Your account is active, but you have no approved bid deposit for this auction. Without it you cannot place bids.',
            ],
            'en_revision' => [
                'titulo' => 'Bid deposit under review',
                'texto' => 'We received your proof of payment and are reviewing it manually. We will email you as soon as it is approved.',
            ],
            'aprobada' => [
                'titulo' => 'You are approved to bid',
                'texto' => 'Your bid deposit for this auction was approved. Go to the bidding room to place bids in Chilean pesos.',
            ],
        ],
        'proximo' => [
            'visitante' => [
                'titulo' => 'You need an account to bid',
                'texto' => 'You can review all the details without signing up. To place bids, create an account and provide the bid deposit.',
            ],
            'registrado' => [
                'titulo' => 'Bid deposit pending',
                'texto' => 'Bank draft (vale a la vista) or transfer for :monto, outside the platform. It must be approved before :limite.',
            ],
            'en_revision' => [
                'titulo' => 'Bid deposit under review',
                'texto' => 'We received your proof of payment and will review it manually within :horas business hours. We will email you.',
            ],
            'aprobada' => [
                'titulo' => 'You are approved to bid',
                'texto' => 'Your bid deposit for this auction was approved. You can place bids once the broadcast starts.',
            ],
        ],
        'cta' => [
            'crear_cuenta' => 'Create account',
            'ver_mi_cuenta' => 'View my account',
            'ver_estado_garantia' => 'View deposit status',
            'entrar_sala' => 'Enter the bidding room',
            'constituir_garantia' => 'Provide the bid deposit',
            'recordarme' => 'Remind me when it starts',
        ],
    ],

    'pasos' => [
        'titulo' => 'How to take part',
        'cuenta' => ['titulo' => 'Create your account', 'texto' => 'With your RUT and contact details.'],
        'garantia' => ['titulo' => 'Provide the bid deposit', 'texto' => 'Bank draft (vale a la vista) or transfer for :monto, outside the platform.'],
        'aprobacion' => ['titulo' => 'Wait for approval', 'texto' => 'Colliers reviews your proof of payment manually and approves your registration.'],
        'puja' => ['titulo' => 'Bid during the broadcast', 'texto' => 'In Chilean pesos, with a minimum bid increment of :monto. Closes automatically when time runs out.'],
    ],

    // Strip under the navigation.
    'ticker' => [
        'en_vivo' => 'LIVE',
        'no_disponible' => 'NOT AVAILABLE',
        'cerrado' => 'CLOSED',
        'cerrando' => 'CLOSING',
        'cierra_en' => 'closes in',
        'retirado' => 'this auction is no longer available',
        'adjudicando' => 'the lot closed: awarding in progress',
        'lote_cerro' => 'the lot closed',
        'puja_actual' => 'Current bid',
        'remate_cerrado' => 'AUCTION CLOSED',
        'proximo_remate' => 'UPCOMING AUCTION',
        'comienza_el' => 'starts',
        'garantias_hasta' => 'Deposit deadline:',
    ],

    'migas' => [
        'inicio' => 'Home',
        'remates' => 'Auctions',
    ],

    'cabeza' => [
        'folio' => 'AUCTION NO.',
        'bases' => 'Terms and conditions (in Spanish)',
        'compartir' => 'Share',
    ],

    'video' => [
        'titulo' => 'Live auction broadcast',
        'foto_principal' => 'Main photo of the property',
        'canal' => 'Broadcast from the Colliers Chile channel',
        'martillero' => 'Auctioneer:',
        'ver_fotos' => 'View all :total photos',
    ],

    'transmision' => [
        'se_habilita' => 'The broadcast opens',
        'texto' => 'The auction is streamed live on the Colliers Chile YouTube channel. The player will appear on this page when it starts, together with the bid history.',
        'calendario' => 'Add to my calendar',
        'ver_canal' => 'Visit the Colliers channel',
    ],

    'secciones' => [
        'lotes' => 'Lots in this auction',
        'lote' => 'Lot :orden · :direccion',
        'descripcion' => 'Description',
        'caracteristicas' => 'Key features',
        'adicional' => 'Additional information',
        'mandante' => 'Seller (principal) details',
        'ubicacion' => 'Location',
        'google_maps' => 'Open in Google Maps',
        'condiciones' => 'Auction conditions',
        'condicion_pesos' => 'Bids are placed in Chilean pesos. The UF value is shown for reference only and does not determine the bid or bid deposit amounts.',
        'condicion_cierre' => 'The auction closes automatically when the timer runs out, with no extensions for last-minute bids. Colliers may close the auction early.',
    ],

    'visitas' => [
        'titulo' => 'Viewing times',
        'sin_horarios' => 'No viewing times published',
        'coordinala' => 'Arrange it with Colliers',
        'coordinar' => 'Arrange a viewing',
    ],

    // Side box with the price, the countdown and the call to action.
    'caja' => [
        'en_curso' => 'AUCTION IN PROGRESS',
        'remate_cerrado' => 'AUCTION CLOSED',
        'comienza_en' => 'STARTS IN',
        'puja_actual' => 'CURRENT BID',
        'pujas_ultima' => ':total bids · last one :hace ago',
        'precio_base' => 'STARTING PRICE',
        'incremento_minimo' => 'MIN. BID INCREMENT',
        'garantia' => 'BID DEPOSIT',
        'referencia_uf' => 'UF REFERENCE',
        'cerrado' => 'CLOSED',
        'cierra_en' => 'CLOSES IN',
        'retirado' => 'This auction is no longer available',
        'adjudicando' => 'Awarding…',
        'cerrado_aviso' => 'Auction closed',
        'resultado' => 'RESULT',
        'precio_base_monto' => 'Starting price :monto',
        'referencia' => 'Reference:',
        'sin_pujas_aun' => 'no bids yet',
        'inicio' => 'START',
        'cierre_garantias' => 'DEPOSIT DEADLINE',
        'hasta_inicio' => 'Until the start',
        'lote_adjudicando' => 'The lot closed: awarding in progress.',
        'lote_cerro' => 'The lot closed.',
        'te_avisaremos' => 'We will notify you',
        'estado_garantia' => 'View my deposit status',
        'como_garantia' => 'How to provide the bid deposit',
        'contactar' => 'Contact the account executive',
        'avisame' => 'Notify me before it starts',
        'correo_ejemplo' => 'name@email.com',
        'correo_aviso' => 'Email for the reminder',
        'avisarme' => 'Notify me',
    ],

    'contador' => [
        'dias' => 'DAYS',
        'horas' => 'HOURS',
        'min' => 'MIN',
        'seg' => 'SEC',
    ],

    'historial' => [
        'titulo' => 'Bid history',
        'actualiza' => 'Updates automatically',
        'nota' => 'Bidders are identified by a number assigned by the system.',
    ],

    'documentos' => [
        'titulo' => 'Available documents',
        'con_garantia' => 'With an approved bid deposit',
        'en_preparacion' => 'Documents in preparation',
        'pidelos' => 'Request them from Colliers',
        'nota' => 'The full legal documents are given to bidders with an approved bid deposit.',
        // Documents are uploaded in Spanish. The governing-version line is pending confirmation by Colliers.
        'idioma' => ' (in Spanish)',
        'version_que_rige' => 'All documents are in Spanish. The Spanish version is the only one that governs.',
    ],

    'ayuda' => [
        'titulo' => 'Need help?',
        'texto' => 'The auctions team answers questions about the property, the bid deposit and the procedure.',
    ],

    'recomendados' => [
        'titulo' => 'Other auctions you may like',
        'ver_todos' => 'View all auctions',
        'precio_base' => 'STARTING PRICE',
        'remate' => 'AUCTION',
        'garantia' => 'BID DEPOSIT',
        'ver' => 'View auction',
    ],

    // detalle.js
    'js' => [
        'alias' => 'Bidder #:n',
        'seg' => ':n sec',
        'min' => ':n min',
        'h' => ':n h',
        'no_disponible' => 'AUCTION NOT AVAILABLE',
        'adjudicado' => 'AUCTION AWARDED',
        'cerrado' => 'AUCTION CLOSED',
        'cerrado_adjudicando' => 'CLOSED · AWARDING',
        'sin_pujas' => 'No bids',
        'hace' => ':tiempo ago',
        'enlace_copiado' => 'Link copied',
        'listo' => 'Done.',
        'error_correo' => 'We could not save your email.',
        'sin_conexion' => 'No connection: please try again.',
    ],
];
