<?php

/*
 * Bidding room (view, sala.js and the engine's rejection reasons). English site, 20/10.
 * Placeholders (:monto, :hora…) are replaced by Laravel in PHP and by t() in JavaScript (resources/js/modulos/textos.js).
 */
return [
    'titulo' => 'Bidding room',

    'cabecera' => [
        'garantia_aprobada' => 'BID DEPOSIT APPROVED',
        'ir_a_mi_cuenta' => 'Go to My account',
    ],

    'video' => [
        'titulo' => 'Live auction broadcast',
        'canal' => 'Broadcast from the Colliers Chile channel',
        'martillero' => 'Auctioneer',
        'oficial' => 'Official price and timer: the video runs 10–30 s behind',
    ],

    'ficha' => [
        'ver_antecedentes' => 'View details',
        'precio_base' => 'STARTING PRICE',
        'incremento_minimo' => 'MIN. BID INCREMENT',
        'tu_garantia' => 'YOUR BID DEPOSIT',
        'referencia_uf' => 'UF REFERENCE',
        // Lot features line (district, type, area, bedrooms and bathrooms, occupancy).
        'superficie' => ':superficie m² usable',
        'dormitorios_banos' => ':dormitorios bd / :banos ba',
    ],

    'historial' => [
        'titulo' => 'Bid history',
        // :total and :conectados arrive as HTML from the view (live counters).
        'conteo' => ':total BIDS · REAL TIME:conectados',
        'nota' => 'The auction closes automatically when the timer runs out, with no extensions. Colliers may close it early.',
    ],

    'panel' => [
        'precio_actual' => 'CURRENT PRICE',
        // :base and :sobre arrive as HTML from the view.
        'base' => 'Starting :base · :sobre above the minimum',
        'cierra_en' => 'CLOSES IN',
        'horas' => 'HOURS',
        'min' => 'MIN',
        'seg' => 'SEC',
        'sin_extension' => 'No extensions for last-minute bids.',
        'ver_remates' => 'View published auctions',
    ],

    'soporte' => [
        'titulo' => 'SUPPORT DURING THE AUCTION',
        'texto' => 'If the broadcast drops or you cannot bid, call us: the auction does not stop.',
    ],

    'barra' => [
        'aria' => 'Bid status',
        'finalizado' => 'Ended',
        'mi_cuenta' => 'My account',
        'remates' => 'Auctions',
        'pujar' => 'Bid',
    ],

    'hoja' => [
        'titulo' => 'Place a bid',
        'cerrar' => 'Close',
        // :precio and :tiempo arrive as HTML from the view.
        'resumen' => 'Current price :precio · closes in :tiempo',
    ],

    'modal' => [
        'titulo' => 'Confirm your bid',
        'propiedad' => 'You are bidding on :direccion',
        // :diferencia arrives as HTML from the view.
        'supera' => ':diferencia above the current bid',
        'aviso' => 'Bids are irrevocable and commit your bid deposit of :garantia. If you are awarded the lot, you must sign the deed and pay the balance within the period set in the terms and conditions. The auction closes automatically when time runs out.',
        'confirmar' => 'Confirm bid',
        'cancelar' => 'Cancel',
    ],

    'avisos' => [
        'mensaje_martillero' => 'MESSAGE FROM THE AUCTIONEER',
    ],

    'formulario' => [
        'puja_rapida' => 'QUICK BID',
        'monto_libre' => 'OTHER AMOUNT (CLP)',
        'nota' => 'Bids are in Chilean pesos and are irrevocable. You will be asked to confirm before they are placed.',
    ],

    // Engine rejection reasons (PujaRechazada). The reason code does not change; only the text the bidder reads.
    'rechazo' => [
        'monto_invalido' => 'Enter a whole number of pesos, without dots or decimals.',
        'lote_inexistente' => 'That lot is not part of this auction.',
        'remate_no_disponible' => 'This auction is not open for bidding.',
        'remate_cancelado' => 'The auction was cancelled. Bids are no longer accepted.',
        'remate_no_publicado' => 'This auction is not published yet.',
        'lote_no_abierto' => 'The lot is not open yet.',
        'lote_cerrado' => 'Too late: the lot closed before your bid arrived.',
        'cuenta_no_habilitada' => 'Your account is not approved to bid.',
        'sin_garantia' => 'You have no approved bid deposit for this auction.',
        'ya_vas_ganando' => 'You already have the highest bid. Wait until someone outbids you.',
        'monto_insuficiente' => 'Your bid is below the minimum.',
        'generico' => 'Bid rejected.',

        'lote_cerrado_sin_hora' => 'The lot is closed.',
        'lote_cerrado_a_las' => 'Too late: the lot closed at :hora (Santiago time).',
        'ya_adjudicado' => ' It has been awarded.',
        'lote_abre_a_las' => 'The lot opens at :hora (Santiago time). You cannot bid yet.',
        'monto_menor_que' => 'Your bid is below the minimum: :minima.',

        'cuenta' => [
            'registrado' => 'Confirm your email first: check your inbox to activate your account.',
            'en_revision' => 'Colliers is still reviewing your account. We will email you once it is approved.',
            'rechazado' => 'Your account was rejected. See the reason in “My account”.',
            'bloqueado' => 'Your account is blocked. Contact us to review it.',
            'sin_postor' => 'This is not a bidder account. Bids require a bidder account.',
            'inactiva' => 'Your account is disabled.',
        ],

        'garantia' => [
            'pendiente' => 'Your bid deposit is pending. Upload the proof of payment in “My account” so Colliers can review it.',
            'en_revision' => 'Your bid deposit is under review. We will email you as soon as it is approved.',
            'rechazada' => 'Your bid deposit was rejected. See the reason in “My account” and upload a new proof of payment.',
            'sin_inscripcion' => 'You are not registered for this auction. Register and provide the bid deposit to bid.',
        ],
    ],

    // Texts from resources/js/modulos/sala.js: t('sala.<key>').
    // Alias del postor en el feed («Postor #N» viaja como dato en el JSON público; se muestra traducido).
    'alias' => 'Bidder #:n',
    // Connection-lost banner (QA 21/10).
    'conexion' => [
        'titulo' => 'Connection lost.',
        'texto' => 'The price and the countdown may be out of date. Bidding is blocked until you reconnect.',
        'barra' => 'CONNECTION LOST',
    ],

    'js' => [
        'alias' => 'Bidder #:n',
        'conectado' => ':n ONLINE',
        'conectados' => ':n ONLINE',
        'lote_de' => 'Lot :orden of :total',

        // Lot change. :direccion arrives as «: Av. …» or empty.
        'lote_sin_posturas' => 'Lot :orden closed with no bids.',
        'lote_ganado' => 'You were awarded lot :orden for :precio. Colliers will contact you to sign and pay the balance.',
        'lote_de_otro' => 'Lot :orden was awarded to another bidder for :precio.',
        'ahora_abre' => 'Now up: lot :orden:direccion, opens at :hora.',
        'ahora_en_vivo' => 'Now up: lot :orden:direccion, live.',
        'cambio_de_lote' => 'LOT CHANGE · NOW :lote',

        'hace_seg' => ':n sec ago',
        'hace_min' => ':n min ago',
        'hace_h' => ':n h ago',

        'abre_en_etiqueta' => 'OPENS IN',
        'cierra_en_etiqueta' => 'CLOSES IN',

        'estado' => [
            'cancelado' => 'AUCTION CANCELLED',
            'no_disponible' => 'AUCTION NOT AVAILABLE',
            'adjudicado' => 'AWARDED',
            'desierto' => 'CLOSED · NO BIDS',
            'proximo' => 'UPCOMING · BIDDING NOT OPEN YET',
            'adjudicando' => 'CLOSED · AWARDING',
            'en_vivo' => 'LIVE · BIDDING OPEN',
            'finalizado' => 'AUCTION ENDED',
        ],

        'tiempo' => [
            'cancelado' => 'Auction cancelled',
            'no_disponible' => 'Auction not available',
            'finalizado' => 'Auction ended',
            'abre_en' => 'Opens in :tiempo',
            'cerrado' => 'Closed',
            'cierra_en' => 'Closes in :tiempo',
        ],

        'sin_posturas' => 'No bids',
        'vas_ganando' => 'You\'re winning',
        'te_superaron' => 'Outbid',
        'detalle_sin_posturas' => 'No bids yet. The first bid starts at :monto.',
        'detalle_ganando' => 'Yours is the highest bid in this auction.',
        'detalle_superado' => 'Another bidder has the highest bid. You can bid from :monto.',

        'ayuda_abre' => 'Bidding opens in :tiempo.',
        'ayuda_sin_conexion' => 'Connection lost: wait for the room to reconnect to bid.',
        'ayuda_ganando' => 'You have the highest bid. Wait until another bidder outbids you.',
        'ayuda_minimo' => 'Your bid must be at least :monto.',
        'ayuda_incremento' => 'Minimum bid increment :monto.',
        'ayuda_incremento_uf' => 'Minimum bid increment :monto. Reference: :uf.',
        'placeholder' => 'Minimum :monto',
        'enviando' => 'Sending…',
        'pujar' => 'Bid :monto',

        'error' => [
            'rechazada' => 'Bid rejected.',
            'sesion' => 'Your session expired. Reload the page to keep bidding.',
            'acceso' => 'You do not have access to bid. Reload the page.',
            'seguido' => 'Too many bids in a row. Wait a few seconds.',
            'no_confirmada' => 'We could not confirm your bid. Check the history: if it is not there, try again.',
            'sin_conexion' => 'No connection: we do not know if your bid arrived. Check the history before trying again.',
        ],

        'postor_tu' => ':postor (you)',

        'resultado' => [
            'cancelado_titulo' => 'Colliers cancelled this auction',
            'no_disponible_titulo' => 'This auction is no longer available',
            'cerrado_titulo' => 'Auction closed',
            'finalizado_titulo' => 'Auction ended',
            'desierto_titulo' => 'The auction closed with no bids',
            'ganado_titulo' => 'You were awarded the property',
            'otro_titulo' => 'Awarded to another bidder',
            'cancelado_texto' => 'Bids are no longer accepted. Your auctions and your bid deposit status are in “My account”.',
            'no_disponible_texto' => 'Colliers withdrew this auction. Bids are no longer accepted.',
            'cerrado_texto' => 'We are confirming the award with the bids received before the close.',
            'desierto_texto' => 'No bids were placed above the starting price. The property will be offered in a new auction, with its own date and conditions.',
            'ganado_texto' => 'Final price :precio. Colliers will contact you to sign and pay the balance, as set out in the terms and conditions.',
            'otro_texto' => 'Final price :precio. Colliers will inform you about your bid deposit as set out in the terms and conditions.',
        ],
    ],
];
