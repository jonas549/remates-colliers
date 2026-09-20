{{-- El envío superó post_max_size: PHP descartó campos, archivos y token. El mensaje dice cuánto acepta el servidor. --}}
<x-pagina-error codigo="413" titulo="El envío es demasiado pesado"
    :mensaje="$mensaje ?? 'Los archivos que adjuntaste superan lo que este servidor acepta en un solo formulario. Adjunta archivos más livianos e inténtalo de nuevo.'"
    accion="Volver al formulario" :href="$volver ?? url()->previous()" />
