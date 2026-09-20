{{-- Si quien abortó dejó un mensaje propio (por ejemplo, un martillero que abre un remate de otro), se muestra ese. --}}
@php($propio = trim((($exception ?? null))?->getMessage() ?? ''))
<x-pagina-error codigo="403" titulo="No tienes acceso a esta página"
    :mensaje="$propio !== '' ? $propio : 'Tu cuenta no tiene permiso para ver este contenido. Si crees que es un error, contacta al equipo de remates.'" />
