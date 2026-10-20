<?php

// Site error pages (the panel sees them too) and error messages sent to the browser.
return [
    'etiqueta' => 'AUCTIONS',
    'kicker' => 'ERROR :codigo',
    'volver_remates' => 'Back to auctions',
    'ayuda' => 'Need help? Email us at :correo or call :telefono.',

    '403' => [
        'titulo' => 'You don’t have access to this page',
        'mensaje' => 'Your account isn’t allowed to see this content. If you think this is a mistake, contact the auctions team.',
    ],
    '404' => [
        'titulo' => 'Page not found',
        'mensaje' => 'The auction or page you’re looking for doesn’t exist or is no longer published. If you followed a link, the auction may have closed.',
    ],
    '413' => [
        'titulo' => 'Upload too large',
        'mensaje' => 'Your files exceed what this server accepts in a single form. Attach smaller files and try again.',
        'detalle' => 'Your upload is :enviado and this server accepts up to :limite per form. Attach smaller files (shrink the photos or save the PDF at lower quality) and try again.',
        'accion' => 'Back to the form',
    ],
    '419' => [
        'titulo' => 'Your session expired',
        'mensaje' => 'For security, the form expired after a period of inactivity. Go back, reload the page and try again.',
        'accion' => 'Try again',
        // Session expired during a fetch action (bid, panel).
        'json' => 'Your session expired. Reload the page to continue.',
    ],
    '429' => [
        'titulo' => 'Too many attempts',
        'mensaje' => 'We received too many requests in a row from your connection. Wait a moment and try again.',
    ],
    '500' => [
        'titulo' => 'Something went wrong',
        'mensaje' => 'An unexpected error occurred on our side and has been logged. If you’re bidding in a live auction, call us: the auction doesn’t stop.',
    ],
    '503' => [
        'titulo' => 'Under maintenance',
        'mensaje' => 'The platform will be back in a few minutes. If an auction was in progress, the official bid record is kept on the server.',
        'accion' => 'Retry',
    ],
];
