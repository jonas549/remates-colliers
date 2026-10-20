{{-- El envío superó post_max_size: PHP descartó campos, archivos y token. El mensaje dice cuánto acepta el servidor. --}}
<x-pagina-error codigo="413" :titulo="__('errores.413.titulo')"
    :mensaje="$mensaje ?? __('errores.413.mensaje')"
    :accion="__('errores.413.accion')" :href="$volver ?? url()->previous()" />
