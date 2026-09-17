{{-- Errores de validación de un formulario del panel. --}}
@if ($errors->any())
    <div class="admin-errores" role="alert">
        <div class="admin-errores__titulo">Revisa estos datos:</div>
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
