<?php

// Páginas de error del sitio (también las ve el panel) y mensajes de error que llegan al navegador.
return [
    'etiqueta' => 'REMATES',
    'kicker' => 'ERROR :codigo',
    'volver_remates' => 'Volver a los remates',
    'ayuda' => '¿Necesitas ayuda? Escríbenos a :correo o llama al :telefono.',

    '403' => [
        'titulo' => 'No tienes acceso a esta página',
        'mensaje' => 'Tu cuenta no tiene permiso para ver este contenido. Si crees que es un error, contacta al equipo de remates.',
    ],
    '404' => [
        'titulo' => 'No encontramos esta página',
        'mensaje' => 'El remate o la página que buscas no existe o ya no está publicada. Si llegaste desde un enlace, puede que el remate haya cerrado.',
    ],
    '413' => [
        'titulo' => 'El envío es demasiado pesado',
        'mensaje' => 'Los archivos que adjuntaste superan lo que este servidor acepta en un solo formulario. Adjunta archivos más livianos e inténtalo de nuevo.',
        'detalle' => 'El envío pesa :enviado y este servidor acepta hasta :limite por formulario. Adjunta archivos más livianos (puedes reducir las fotos o guardar el PDF con menos calidad) e inténtalo de nuevo.',
        'accion' => 'Volver al formulario',
    ],
    '419' => [
        'titulo' => 'Tu sesión expiró',
        'mensaje' => 'Por seguridad, el formulario dejó de ser válido después de un tiempo sin actividad. Vuelve atrás, recarga la página e inténtalo de nuevo.',
        'accion' => 'Volver a intentarlo',
        // Sesión vencida en una acción por fetch (puja, panel).
        'json' => 'Tu sesión expiró. Recarga la página para continuar.',
    ],
    '429' => [
        'titulo' => 'Demasiados intentos',
        'mensaje' => 'Recibimos muchas solicitudes seguidas desde tu conexión. Espera un momento antes de volver a intentarlo.',
    ],
    '500' => [
        'titulo' => 'Tuvimos un problema',
        'mensaje' => 'Ocurrió un error inesperado de nuestro lado. Ya quedó registrado. Si estás participando en un remate en vivo, llámanos: el remate no se detiene.',
    ],
    '503' => [
        'titulo' => 'Estamos haciendo mantenimiento',
        'mensaje' => 'La plataforma vuelve en unos minutos. Si tenías un remate en curso, el estado oficial de las pujas se conserva en el servidor.',
        'accion' => 'Reintentar',
    ],
];
