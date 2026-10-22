<?php

/*
 * Bidder area: «My account» (account and bid deposit status), active sessions, auction registration, proof of payment
 * and «Notify me» unsubscribe. Data entered by the administrator (bank, titles, reasons) arrives through placeholders
 * and is shown as is.
 */
return [
    'nav' => [
        'titulo' => 'My account',
        'etiqueta' => 'MY ACCOUNT',
        'remates' => 'Auctions',
        'cerrar_sesion' => 'Sign out',
    ],

    'aviso_prueba' => 'This is a test account created from the Colliers panel. Its test auction is not listed on the public auctions page.',
    'aviso_prueba_aprobada' => 'This is a test account created from the Colliers panel. Its test auction is not listed on the public auctions page: enter it from this page with the «Enter the bidding room» button.',

    // Top banner and steps according to the account and bid deposit status.
    'bandas' => [
        'cuenta_revision' => [
            'etiqueta' => 'ACCOUNT UNDER REVIEW',
            'titulo' => 'We’re reviewing your registration',
            'texto' => 'We received your documents. A Colliers representative checks them manually and will email you, usually within :horas business hours. Until then you can’t register for an auction.',
        ],
        'cuenta_rechazada' => [
            'etiqueta' => 'ACCOUNT NOT APPROVED',
            'titulo' => 'We couldn’t approve your account',
            'texto' => 'Colliers reviewed your documents and did not approve the account. Write to us if you want to correct any details.',
            'texto_motivo' => 'Colliers reviewed your documents and did not approve the account: :motivo Write to us if you want to correct any details.',
        ],
        'cuenta_bloqueada' => [
            'etiqueta' => 'ACCOUNT BLOCKED',
            'titulo' => 'Your account is blocked',
            'texto' => 'You can’t register or bid while the account is blocked. Write to us so we can look into it.',
            'texto_motivo' => 'You can’t register or bid while the account is blocked (:motivo). Write to us so we can look into it.',
        ],
        'sin_inscripcion' => [
            'etiqueta' => 'ACCOUNT APPROVED · NO REGISTRATIONS',
            'titulo' => 'Your account is approved',
            'texto' => 'To bid, register for an auction and provide its bid deposit by bank draft (vale a la vista) or transfer. This happens outside the platform: Colliers reviews it and marks your registration as approved or rejected.',
            'texto_unica' => 'To bid, you need a single bidding approval, provided by bank draft (vale a la vista) or transfer: it approves you to bid in every auction. This happens outside the platform; Colliers reviews and approves it.',
            'texto_unica_monto' => 'To bid, you need a single bidding approval of :monto, provided by bank draft (vale a la vista) or transfer: it approves you to bid in every auction. This happens outside the platform; Colliers reviews and approves it.',
        ],
        'garantia_pendiente' => [
            'etiqueta' => 'ACCOUNT APPROVED · DEPOSIT PENDING',
            'titulo' => 'Your account is approved; the bid deposit is missing',
            'texto' => 'To bid, provide the bid deposit by bank draft (vale a la vista) or transfer and send us the proof of payment. This happens outside the platform: Colliers reviews it and marks your registration as approved or rejected.',
        ],
        'garantia_revision' => [
            'etiqueta' => 'DEPOSIT UNDER REVIEW',
            'titulo' => 'We received your proof of payment',
            'texto' => 'We’re checking your proof of payment and will email you as soon as it’s approved, usually within :horas business hours. Only then is the bidding room enabled for this auction.',
            'texto_unica' => 'We’re checking your proof of payment and will email you as soon as it’s approved, usually within :horas business hours. Only then is the bidding room enabled.',
        ],
        'aprobada' => [
            'etiqueta' => 'DEPOSIT APPROVED · APPROVED TO BID',
            'titulo' => 'You’re approved to bid',
            'texto' => 'Your bid deposit of :monto was approved for :remate. You can place bids in pesos once the auction broadcast starts.',
            'texto_unica' => 'Your bidding approval of :monto was approved: it covers every published auction. You can place bids in pesos once each broadcast starts.',
        ],
        // Auction ended with an approved deposit (QA 21/10).
        'finalizado' => [
            'etiqueta' => 'AUCTION ENDED',
            'titulo' => 'The auction has ended',
            'titulo_ganado' => 'You were awarded the property',
            'texto' => 'The auction :remate has ended and no more bids are accepted. Your bid deposit is governed by the auction terms and conditions.',
            'texto_ganado' => 'Colliers will contact you to sign and pay the balance, as set out in the auction terms and conditions.',
        ],
        'remate_cancelado' => [
            'etiqueta' => 'AUCTION CANCELED',
            'titulo' => 'Colliers canceled this auction',
            'texto' => 'The auction :remate was canceled and will not take place. No bids are accepted. If Colliers auctions the property again, it will be a new auction with its own registration. Your bid deposit is governed by the auction terms and conditions; write to us with any questions.',
            'texto_motivo' => 'The auction :remate was canceled (:motivo) and will not take place. No bids are accepted. If Colliers auctions the property again, it will be a new auction with its own registration. Your bid deposit is governed by the auction terms and conditions; write to us with any questions.',
        ],
        'rechazada' => [
            'etiqueta' => 'DEPOSIT REJECTED',
            'titulo' => 'We couldn’t validate your bid deposit',
            'texto' => 'Colliers did not approve the proof of payment. You can correct it and send it again before the deposit deadline, or write to us if you think it’s a mistake.',
            'texto_motivo' => 'Colliers did not approve the proof of payment: :motivo You can correct it and send it again before the deposit deadline, or write to us if you think it’s a mistake.',
        ],
    ],

    // Bid deposit and step status (labels; stored values don't change).
    'estados' => [
        'no_iniciada' => 'Not started',
        'pendiente' => 'Pending',
        'en_revision' => 'Under review',
        'aprobada' => 'Approved',
        'rechazada' => 'Rejected',
        'bloqueada' => 'Blocked',
        'completado' => 'Completed',
        'remate_cancelado' => 'Auction canceled',
        'habilitado' => 'Approved to bid',
        'bloqueado' => 'Blocked',
    ],

    'pasos' => [
        'registro' => ['titulo' => 'Registration sent', 'texto' => 'Personal details and documents received.'],
        'cuenta' => ['titulo' => 'Account approved', 'texto' => 'Manual document review by Colliers.'],
        'garantia' => ['titulo' => 'Bid deposit provided', 'texto' => 'Bank draft (vale a la vista) or transfer, outside the platform.'],
        'habilitado' => ['titulo' => 'Approved to bid', 'texto' => 'The bidding room opens for the auction you registered for.'],
    ],

    'lateral' => [
        'titulo_habilitado' => 'YOUR NEXT AUCTION',
        'titulo' => 'MEANWHILE',
        'texto_habilitado' => 'Shortcuts to the bidding room, the catalog and the documents of the property you registered for.',
        'texto' => 'You can browse the auction catalog and each property’s documents. The bidding room opens only once your bid deposit is approved.',
        'ver_remates' => 'See published auctions',
        'entrar_sala' => 'Enter the bidding room',
        'bases_generales' => 'General auction terms and conditions (in Spanish)',
        'sesiones' => 'Active sessions and password',
        'tus_inscripciones' => 'YOUR REGISTRATIONS',
        'contacto' => 'CONTACT',
        'contacto_texto' => 'If your request has gone unanswered for more than :horas business hours, write to us with your RUT and the auction.',
    ],

    'garantia' => [
        'kicker_remate' => 'AUCTION BID DEPOSIT',
        'kicker_inscripcion' => 'AUCTION REGISTRATION',
        'monto' => 'AMOUNT',
        'plazo' => 'DEADLINE',
        'estado' => 'STATUS',
        'plazo_unica' => 'No deadline: valid for every auction',
        'plazo_inicio' => 'Until the start',
        'medios' => [
            'vale_vista' => 'bank draft (vale a la vista)',
            'transferencia' => 'transfer',
        ],
        'monto_unica' => ':monto (single bidding approval)',
    ],

    'cancelado' => [
        'subtitulo' => 'AUCTION CANCELED',
        'texto' => 'Colliers canceled this auction. It will not take place and no bids are accepted.',
        'texto_fecha' => 'Colliers canceled this auction on :fecha. It will not take place and no bids are accepted.',
        'ver_remates' => 'See published auctions',
        'ver_comprobante' => 'View the proof of payment you sent',
        'nota' => 'What happens to the bid deposit is governed by the auction terms and conditions. With any questions, write to :correo.',
    ],

    'constituir' => [
        'subtitulo' => 'HOW TO PROVIDE IT',
        'vale' => 'Bank draft (vale a la vista) payable to :titular.',
        'vale_entregado' => 'Bank draft (vale a la vista) payable to :titular, delivered at :direccion.',
        'transferencia' => 'Or a transfer to :banco :tipo No. :numero, stating the auction number.',
        'transferencia_rut' => 'Or a transfer to :banco :tipo No. :numero, RUT :rut, stating the auction number.',
        'sin_banco' => 'Bank draft (vale a la vista) or transfer for :monto. Write to :correo to get the account and bank draft details.',
        'subir_o_enviar' => 'Upload the proof of payment here, or send it to :correo with your name and the auction you’re applying for.',
        'subir' => 'Upload proof of payment',
        'ver_antecedentes' => 'See the auction documents',
        'medio' => 'METHOD',
        'transferencia_opcion' => 'Transfer',
        'vale_opcion' => 'Bank draft (vale a la vista)',
        'archivo' => 'PROOF OF PAYMENT (PDF, JPG OR PNG · UP TO 10 MB)',
        'enviar' => 'Send proof of payment',
        'cancelar' => 'Cancel',
        'ver_comprobante_enviado' => 'View the proof of payment you sent',
        'nota' => 'Review is manual and there are no online payments. Once approved, you can bid in that auction; what happens to the bid deposit after the auction is governed by its terms and conditions.',
        'nota_recibido' => 'Review is manual and there are no online payments. Once approved, you can bid in that auction; what happens to the bid deposit after the auction is governed by its terms and conditions. Proof of payment received on :fecha.',
    ],

    'adjudicaciones' => [
        'subtitulo' => 'YOUR AWARDED LOTS',
    ],

    'aprobada' => [
        'subtitulo' => 'BID DEPOSIT RECEIVED AND APPROVED',
        'garantia' => 'Bid deposit',
        // :medio is «Bank draft», «Transfer» or «Bid deposit»; :monto is in bold.
        'recibida' => ':medio for :monto, received by Colliers.',
        'recibida_fecha' => ':medio for :monto, received on :fecha.',
        'recibida_titular' => ':medio for :monto payable to :titular, received by Colliers.',
        'recibida_titular_fecha' => ':medio for :monto payable to :titular, received on :fecha.',
        'aprobada_el' => 'Approved by Colliers on :fecha. You’re approved to bid in this auction.',
        'en_vivo' => 'The auction is <strong>live</strong>: enter the bidding room.',
        'abre' => 'The bidding room opens on :fecha, together with the auction broadcast.',
        'finalizado' => 'This auction has ended: no more bids are accepted.',
        'entrar_sala' => 'Enter the bidding room',
        'ver_comprobante' => 'View proof of payment',
        'nota' => 'If you are awarded the property, Colliers will contact you to sign and pay the balance. What happens to the bid deposit is governed by the auction terms and conditions.',
    ],

    'inscripcion' => [
        'habilitado_en_todos' => 'With your bidding approval you can bid in any published auction: no need to register for each one. Open the auction you’re interested in and use «Enter the bidding room».',
        'garantia' => 'deposit',
        'inscribirme' => 'Register',
        'nota' => 'When you register, the bid deposit for that auction is calculated and you’ll see here how to provide it.',
        'cuando_apruebe' => 'Once Colliers approves your account, you can register for published auctions.',
        'sin_abiertos' => 'No auctions are open for registration right now. We’ll email you when a new one is published.',
        // Messages after registering or uploading the proof of payment.
        'inscrito' => 'You’re registered for :folio. The bid deposit is :monto: provide it and upload the proof of payment.',
        'comprobante_recibido' => 'We received your proof of payment. Colliers will review it and email you.',
        'comprobante_max' => 'The proof of payment can be up to 10 MB.',
        'atributo_medio' => 'method',
        'atributo_comprobante' => 'proof of payment',
        // Reasons why registration or the proof of payment upload is not possible.
        'cuenta_no_aprobada' => 'Colliers must approve your account before you can register for an auction.',
        'remate_de_prueba' => 'This is a Colliers test auction and doesn’t accept registrations. See the auctions published on the site.',
        'cuenta_de_prueba' => 'This is a test account: it can only bid in its test auction, which is already in «My account».',
        'no_acepta' => 'This auction doesn’t accept registrations.',
        'sin_monto_unico' => 'Colliers hasn’t set the bidding approval amount yet. Write to us and we’ll let you know as soon as it’s ready.',
        'ya_aprobada' => 'Your bid deposit is already approved.',
        'ya_en_revision' => 'We already received your proof of payment and are reviewing it.',
        'plazo_cerrado' => 'The deposit deadline for this auction has passed.',
        'ya_comenzo' => 'The auction has started: no more bid deposits are accepted.',
    ],

    'sesiones' => [
        'titulo' => 'Active sessions',
        'kicker' => 'ACCOUNT SECURITY',
        'intro' => 'Devices where your account is signed in. If you don’t recognize one, sign it out and change your password.',
        'requiere_base' => 'The session list requires SESSION_DRIVER=database.',
        'este_dispositivo' => 'THIS DEVICE',
        'ultima_actividad' => 'LAST ACTIVE :fecha',
        'otro_dispositivo' => 'Other device',
        'navegador' => 'browser',
        'programa_en_sistema' => ':programa on :sistema',
        'cerrar' => 'SIGN OUT',
        'volver_panel' => 'Back to the panel',
        'volver_cuenta' => 'Back to my account',
        'cerrar_demas' => 'Sign out of all other sessions',
        'cambiar_clave' => 'Change password',
        'cerrar_sesion' => 'Sign out',
        'usar_cerrar_sesion' => 'To end the current session, use «Sign out».',
        // Remembered devices for the sign-in code (22/10).
        'dispositivos_titulo' => 'DEVICES THAT DON\'T ASK FOR A CODE',
        'dispositivo_hasta' => 'REMEMBERED UNTIL :fecha',
        'olvidar' => 'FORGET',
        'olvidar_todos' => 'Forget all devices',
        'dispositivo_olvidado' => 'That device will ask for the code again at sign-in.',
        'dispositivos_olvidados' => '{1} 1 device will ask for the code again.|[0,*] :count devices will ask for the code again.',
        'cerrada' => 'That session was signed out.',
        'cerradas' => '{1} 1 session signed out.|[0,*] :count sessions signed out.',
    ],

    // Public site «Notify me» and unsubscribe from the email.
    'avisame' => [
        'atributo_email' => 'email',
        'listo_remate' => 'Done: we’ll email you before this auction starts.',
        'listo_nuevos' => 'Done: we’ll email you when a new auction is published.',
        'baja_titulo_pagina' => 'Notifications turned off',
        'baja_etiqueta' => 'AUCTIONS',
        'baja_kicker' => 'EMAIL NOTIFICATIONS',
        'baja_titulo' => 'Done, we won’t send you more notifications',
        'baja_recordatorio' => ':email will no longer receive the reminder for auction :folio.',
        'baja_nuevos' => ':email will no longer receive new auction notifications.',
        'baja_volver' => 'You can subscribe again anytime from the site.',
        'baja_ver_remates' => 'See auctions',
    ],
];
