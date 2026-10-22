{{--
    Segundo factor del panel (22/10): el código de 6 dígitos que llegó al correo. Pantalla sin diseño propio:
    reutiliza el diseño del Login (decisión del 16/09).
--}}
@php
    $errorEnvio = session('error_envio');
@endphp
<x-acceso.marco
    :pagina="__('acceso.dos_pasos.pagina')"
    :kicker="__('acceso.login.kicker_admin')"
    :titulo="__('acceso.dos_pasos.titulo')"
    :intro="$errorEnvio ? null : __('acceso.dos_pasos.intro', ['correo' => $correo, 'minutos' => $minutos])"
    :proximo="$proximo"
>
    @if ($errorEnvio)
        <div class="acceso__error" role="alert" data-error-envio>{{ $errorEnvio }}</div>
    @endif
    @error('codigo')
        <div class="acceso__error" role="alert">{{ $message }}</div>
    @enderror
    @if (session('reenviado'))
        <p class="acceso__intro" role="status">{{ __('acceso.dos_pasos.reenviado', ['correo' => $correo]) }}</p>
    @endif

    <form method="POST" action="{{ route('admin.ingresar.codigo.verificar') }}">
        @csrf
        <div class="acceso__campos">
            <label class="acceso__campo">
                <span class="acceso__etiqueta">{{ __('acceso.dos_pasos.etiqueta') }}</span>
                <input name="codigo" class="acceso__input acceso__input--codigo" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7"
                    autocomplete="one-time-code" placeholder="000000" required autofocus>
            </label>
        </div>
        <div class="acceso__opciones">
            @if ($diasRecordar > 0)
                <label class="acceso__recordar">
                    <input type="checkbox" name="recordar_dispositivo" value="1">
                    {{ trans_choice('acceso.dos_pasos.recordar', $diasRecordar, ['dias' => $diasRecordar]) }}
                </label>
            @endif
        </div>
        <button type="submit" class="acceso__entrar es-listo">{{ __('acceso.dos_pasos.confirmar') }}</button>
    </form>

    <p class="acceso__nota">{{ __('acceso.dos_pasos.nota') }}</p>

    <div class="acceso__contacto">
        <form method="POST" action="{{ route('admin.ingresar.codigo.reenviar') }}" class="formulario-en-linea">
            @csrf
            <button type="submit" class="boton-enlace acceso__contacto-admin">{{ __('acceso.dos_pasos.reenviar') }}</button>
        </form>
        <form method="POST" action="{{ route('admin.ingresar.codigo.cancelar') }}" class="formulario-en-linea">
            @csrf
            <button type="submit" class="boton-enlace acceso__contacto-admin">{{ __('acceso.dos_pasos.otra_cuenta') }}</button>
        </form>
    </div>
</x-acceso.marco>
