{{-- Si quien abortó dejó un mensaje propio (por ejemplo, un martillero que abre un remate de otro), se muestra ese. --}}
@php($propio = trim((($exception ?? null))?->getMessage() ?? ''))
<x-pagina-error codigo="403" :titulo="__('errores.403.titulo')"
    :mensaje="$propio !== '' ? $propio : __('errores.403.mensaje')" />
