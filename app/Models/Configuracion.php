<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Valores de negocio editables desde el panel (Bloque V: Administración → Configuración). Los valores por defecto se
 * siembran con `colliers:instalar` y solo si la clave no existe: nunca pisan lo que un administrador cambió.
 *
 * Tipos: entero | porcentaje | decimal | texto | texto_largo | correo | url | booleano | opcion | lista_montos | secreto.
 * Un `secreto` (clave SMTP) se guarda cifrado con APP_KEY y nunca vuelve al navegador.
 */
#[Table('configuraciones')]
#[Fillable(['clave', 'valor', 'tipo', 'grupo', 'descripcion'])]
class Configuracion extends Model
{
    /**
     * Una pantalla por tema (17/09, pedido de Jonas): Configuración dejó de ser una sola pantalla larga.
     * Cada sección es una entrada del submenú; `grupos` son los subtítulos que muestra dentro.
     */
    public const SECCIONES = [
        'remates' => ['titulo' => 'Remates y pujas', 'grupos' => ['pujas', 'remates'],
            'bajada' => 'Cómo se puja y cuánto dura cada lote. Los remates ya creados conservan sus condiciones; cada remate puede fijar las suyas.'],
        'garantias' => ['titulo' => 'Garantías', 'grupos' => ['garantias', 'banco'],
            'bajada' => 'Monto, plazos y los datos que ve el postor para constituirla. El proceso es manual y externo: no hay pasarela de pago.'],
        'correo' => ['titulo' => 'Correo (SMTP)', 'grupos' => ['correo'],
            'bajada' => 'Servidor de salida y remitente. Los correos se envían por la cola, que procesa el cron cada minuto.'],
        'plantillas' => ['titulo' => 'Plantillas de correo', 'grupos' => [],
            'bajada' => 'Asunto y texto de cada correo que envía la plataforma.'],
        'notificaciones' => ['titulo' => 'Notificaciones', 'grupos' => ['notificaciones'],
            'bajada' => 'Qué se envía, a quién y cuándo.'],
        'correos' => ['titulo' => 'Registro de correos', 'grupos' => [],
            'bajada' => 'Historial de todo lo que la plataforma intentó enviar, con el error completo cuando falla.'],
        'seguridad' => ['titulo' => 'Seguridad', 'grupos' => ['seguridad'],
            'bajada' => 'Bloqueo por intentos fallidos y duración de la sesión.'],
        'sitio' => ['titulo' => 'Sitio', 'grupos' => ['uf', 'contacto', 'sitio'],
            'bajada' => 'Lo que ve el visitante: UF de referencia, contacto, enlaces y textos legales.'],
        'sistema' => ['titulo' => 'Sistema', 'grupos' => [],
            'bajada' => 'Solo lectura: cómo está el servidor que atiende las pujas.'],
    ];

    /** Grupos (subtítulos dentro de cada sección). */
    public const GRUPOS = [
        'pujas' => 'Pujas y cierre',
        'remates' => 'Remates',
        'garantias' => 'Garantías',
        'banco' => 'Datos para constituir la garantía',
        'uf' => 'Unidad de fomento (solo referencia visual)',
        'contacto' => 'Contacto, enlaces y textos legales',
        'correo' => 'Correo saliente (SMTP)',
        'notificaciones' => 'Notificaciones',
        'seguridad' => 'Seguridad',
        'sitio' => 'Sitio público',
    ];

    /**
     * Valores confirmados en el acta, en el diseño o en decisiones de Jonas (CLAUDE.md §3 y §7). Los datos bancarios y los
     * enlaces legales parten vacíos: el diseño trae datos de ejemplo que no se pueden presentar como reales.
     */
    public const DEFECTOS = [
        // Pujas y cierre
        'incremento_minimo' => ['valor' => '100000', 'tipo' => 'entero', 'grupo' => 'pujas', 'etiqueta' => 'Incremento mínimo global (CLP)', 'min' => 1000, 'descripcion' => 'Incremento mínimo global entre pujas, en pesos. Cada remate puede fijar el suyo.'],
        'pujas_rapidas' => ['valor' => '[100000,500000,1000000]', 'tipo' => 'lista_montos', 'grupo' => 'pujas', 'etiqueta' => 'Botones de puja rápida (CLP)', 'descripcion' => 'Montos de los botones de puja rápida, separados por coma (acta: 100.000, 500.000 y 1.000.000).'],
        'margen_liquidacion_segundos' => ['valor' => '2', 'tipo' => 'entero', 'grupo' => 'pujas', 'etiqueta' => 'Margen de liquidación (segundos)', 'min' => 1, 'max' => 60, 'descripcion' => 'Segundos después del cierre para terminar las pujas recibidas antes de T. No extiende el remate: nadie puede pujar después del cierre. Sin OPcache conviene 5 s.'],
        // Remates
        'pausa_entre_lotes_minutos' => ['valor' => '0', 'tipo' => 'entero', 'grupo' => 'remates', 'etiqueta' => 'Pausa entre lotes (minutos)', 'min' => 0, 'max' => 600, 'descripcion' => 'Por defecto entre el cierre de un lote y la apertura del siguiente. Cada remate puede fijar la suya.'],
        'duracion_lote_minutos' => ['valor' => '30', 'tipo' => 'entero', 'grupo' => 'remates', 'etiqueta' => 'Duración por defecto de cada lote (minutos)', 'min' => 1, 'max' => 600, 'descripcion' => 'Temporizador fijo, sin extensiones. Cada remate o lote puede fijar la suya.'],
        // Garantías
        'porcentaje_garantia' => ['valor' => '10', 'tipo' => 'porcentaje', 'grupo' => 'garantias', 'etiqueta' => 'Garantía (% del precio base)', 'min' => 0, 'max' => 100, 'descripcion' => 'Porcentaje de la garantía sobre el precio base del remate (acta: 10 %). Una garantía ya creada conserva su monto.'],
        'garantias_cierre_horas_antes' => ['valor' => '48', 'tipo' => 'entero', 'grupo' => 'garantias', 'etiqueta' => 'Cierre de garantías (horas antes del inicio)', 'min' => 0, 'max' => 720, 'descripcion' => 'Plazo por defecto para recibir garantías si el remate no fija otra fecha (diseño: «hasta 48 horas antes»).'],
        'garantias_revision_horas' => ['valor' => '24', 'tipo' => 'entero', 'grupo' => 'garantias', 'etiqueta' => 'Tiempo de revisión informado (horas hábiles)', 'min' => 1, 'max' => 240, 'descripcion' => 'Plazo que se le informa al postor para revisar su cuenta o su comprobante (diseño: 24 horas hábiles).'],
        // Datos bancarios
        'banco_nombre' => ['valor' => '', 'tipo' => 'texto', 'grupo' => 'banco', 'etiqueta' => 'Banco', 'descripcion' => 'Para transferencias. Mientras estén vacíos, el postor ve «escríbenos para recibir los datos».'],
        'banco_tipo_cuenta' => ['valor' => 'Cuenta corriente', 'tipo' => 'texto', 'grupo' => 'banco', 'etiqueta' => 'Tipo de cuenta', 'descripcion' => ''],
        'banco_numero_cuenta' => ['valor' => '', 'tipo' => 'texto', 'grupo' => 'banco', 'etiqueta' => 'Número de cuenta', 'descripcion' => ''],
        'banco_titular' => ['valor' => '', 'tipo' => 'texto', 'grupo' => 'banco', 'etiqueta' => 'Titular', 'descripcion' => 'También es el nombre del vale a la vista.'],
        'banco_rut_titular' => ['valor' => '', 'tipo' => 'texto', 'grupo' => 'banco', 'etiqueta' => 'RUT del titular', 'descripcion' => ''],
        'vale_vista_direccion' => ['valor' => '', 'tipo' => 'texto', 'grupo' => 'banco', 'etiqueta' => 'Dirección de entrega del vale a la vista', 'descripcion' => ''],
        // UF
        'uf_fuente' => ['valor' => 'mindicador', 'tipo' => 'opcion', 'grupo' => 'uf', 'etiqueta' => 'Fuente del valor', 'opciones' => ['mindicador' => 'Automática (mindicador.cl, cada hora)', 'manual' => 'Manual (el valor de abajo)'], 'descripcion' => 'La UF se muestra solo como referencia: las pujas y garantías son en pesos.'],
        'uf_valor' => ['valor' => '', 'tipo' => 'decimal', 'grupo' => 'uf', 'etiqueta' => 'Valor de la UF (CLP)', 'min' => 0, 'descripcion' => 'Con fuente automática lo actualiza el sistema. Vacío: el sitio no muestra referencias en UF.'],
        'uf_fecha' => ['valor' => '', 'tipo' => 'texto', 'grupo' => 'uf', 'etiqueta' => 'Fecha del valor', 'solo_lectura' => true, 'descripcion' => ''],
        // Contacto y legales
        'contacto_correo' => ['valor' => 'remates@colliers.cl', 'tipo' => 'correo', 'grupo' => 'contacto', 'etiqueta' => 'Correo de contacto y de comprobantes', 'descripcion' => 'Aparece en el sitio, en la sala y en los correos. Recibe los comprobantes de garantía.'],
        'contacto_telefono' => ['valor' => '+56 2 2760 3535', 'tipo' => 'texto', 'grupo' => 'contacto', 'etiqueta' => 'Teléfono de contacto', 'descripcion' => ''],
        'enlace_canal_youtube' => ['valor' => '', 'tipo' => 'url', 'grupo' => 'contacto', 'etiqueta' => 'Canal de YouTube de Colliers', 'descripcion' => 'Enlace «Ver el canal de Colliers» del detalle de remate.'],
        'enlace_bases_generales' => ['valor' => '', 'tipo' => 'url', 'grupo' => 'contacto', 'etiqueta' => 'Bases generales de los remates (enlace)', 'descripcion' => ''],
        'enlace_terminos' => ['valor' => '', 'tipo' => 'url', 'grupo' => 'contacto', 'etiqueta' => 'Términos y condiciones (enlace)', 'descripcion' => 'Se enlaza en el registro de postores.'],
        'enlace_privacidad' => ['valor' => '', 'tipo' => 'url', 'grupo' => 'contacto', 'etiqueta' => 'Política de privacidad (enlace)', 'descripcion' => ''],
        'texto_condiciones_garantia' => ['valor' => 'La garantía se constituye por vale a la vista o transferencia y es revisada manualmente por Colliers antes del inicio. Si el remate no se concreta, la propiedad se publica en un remate nuevo con fecha y condiciones propias.', 'tipo' => 'texto_largo', 'grupo' => 'contacto', 'etiqueta' => 'Condiciones de la garantía (detalle de remate)', 'descripcion' => ''],
        // Correo
        'correo_modo' => ['valor' => 'env', 'tipo' => 'opcion', 'grupo' => 'correo', 'etiqueta' => 'Envío de correos', 'opciones' => ['env' => 'Según el servidor (.env)', 'smtp' => 'SMTP con los datos de abajo', 'log' => 'No enviar: dejar en el registro (pruebas)'], 'descripcion' => 'Los correos salen por la cola, que procesa el cron cada minuto.'],
        'smtp_host' => ['valor' => '', 'tipo' => 'texto', 'grupo' => 'correo', 'etiqueta' => 'Servidor SMTP', 'descripcion' => 'Ej.: mail.colliers.cl'],
        'smtp_puerto' => ['valor' => '587', 'tipo' => 'entero', 'grupo' => 'correo', 'etiqueta' => 'Puerto', 'min' => 1, 'max' => 65535, 'descripcion' => '587 con TLS, 465 con SSL.'],
        'smtp_cifrado' => ['valor' => 'tls', 'tipo' => 'opcion', 'grupo' => 'correo', 'etiqueta' => 'Cifrado', 'opciones' => ['tls' => 'TLS (STARTTLS)', 'ssl' => 'SSL', 'ninguno' => 'Ninguno'], 'descripcion' => ''],
        'smtp_usuario' => ['valor' => '', 'tipo' => 'texto', 'grupo' => 'correo', 'etiqueta' => 'Usuario', 'descripcion' => ''],
        'smtp_clave' => ['valor' => '', 'tipo' => 'secreto', 'grupo' => 'correo', 'etiqueta' => 'Contraseña', 'descripcion' => 'Se guarda cifrada. Déjala vacía para conservar la actual.'],
        'correo_remitente' => ['valor' => '', 'tipo' => 'correo', 'grupo' => 'correo', 'etiqueta' => 'Remitente (dirección)', 'descripcion' => 'Vacío: el del .env.'],
        'correo_remitente_nombre' => ['valor' => 'Remates Colliers', 'tipo' => 'texto', 'grupo' => 'correo', 'etiqueta' => 'Remitente (nombre)', 'descripcion' => ''],
        // Notificaciones
        'correo_avisos_admin' => ['valor' => '', 'tipo' => 'correo', 'grupo' => 'notificaciones', 'etiqueta' => 'Correo que recibe los avisos de la administración', 'descripcion' => 'Recibe el resumen de cada remate al cerrarse. Vacío: todos los administradores activos.'],
        'recordatorio_horas_antes' => ['valor' => '24', 'tipo' => 'entero', 'grupo' => 'notificaciones', 'etiqueta' => 'Recordatorio antes del remate (horas)', 'min' => 1, 'max' => 336, 'descripcion' => 'A inscritos y a quienes pidieron «Avísame antes de que comience».'],
        // Seguridad
        'login_intentos_maximos' => ['valor' => '5', 'tipo' => 'entero', 'grupo' => 'seguridad', 'etiqueta' => 'Intentos de ingreso antes de bloquear', 'min' => 1, 'max' => 50, 'descripcion' => 'Diseño del Login: 5.'],
        'login_bloqueo_minutos' => ['valor' => '15', 'tipo' => 'entero', 'grupo' => 'seguridad', 'etiqueta' => 'Duración del bloqueo (minutos)', 'min' => 1, 'max' => 1440, 'descripcion' => 'Decisión del 16/09: 15.'],
        'sesion_minutos' => ['valor' => '120', 'tipo' => 'entero', 'grupo' => 'seguridad', 'etiqueta' => 'Duración de la sesión (minutos)', 'min' => 15, 'max' => 720, 'descripcion' => 'Inactividad antes de pedir de nuevo la contraseña (15 min a 12 h). Al cambiar la contraseña se cierran las demás sesiones.'],
        // Sitio
        'filtro_garantia_visible' => ['valor' => '0', 'tipo' => 'booleano', 'grupo' => 'sitio', 'etiqueta' => 'Mostrar el filtro «Garantía requerida» en el listado', 'descripcion' => 'Oculto por decisión del 15/09; útil si los remates usan porcentajes distintos.'],
    ];

    private const COLUMNAS = ['valor', 'tipo', 'grupo', 'descripcion'];

    /** Claves editables de una sección, en el orden de DEFECTOS. @return array<string, array> */
    public static function camposDe(string $seccion): array
    {
        $grupos = self::SECCIONES[$seccion]['grupos'] ?? [];

        return array_filter(self::DEFECTOS, fn (array $datos) => in_array($datos['grupo'], $grupos, true));
    }

    /**
     * Valores leídos hace menos de MEMORIA_SEGUNDOS: una puja consultaba la misma clave 3–4 veces. Vida corta a propósito:
     * el cron y la cola viven hasta 50 s y deben ver un cambio hecho desde el panel. Guardar desde el panel la vacía.
     *
     * @var array<string, array{0: float, 1: ?self}>
     */
    private static array $memoria = [];

    private const MEMORIA_SEGUNDOS = 2.0;

    protected static function booted(): void
    {
        static::saved(fn () => self::olvidar());
        static::deleted(fn () => self::olvidar());
    }

    public static function olvidar(): void
    {
        self::$memoria = [];
    }

    /** Crea las claves que faltan con su valor por defecto. Devuelve cuántas creó. Idempotente. */
    public static function sembrarDefectos(): int
    {
        $creadas = 0;
        foreach (self::DEFECTOS as $clave => $datos) {
            $columnas = array_intersect_key($datos, array_flip(self::COLUMNAS));
            $creadas += static::firstOrCreate(['clave' => $clave], $columnas)->wasRecentlyCreated ? 1 : 0;
        }

        return $creadas;
    }

    public static function valor(string $clave, mixed $defecto = null): mixed
    {
        $recordada = self::$memoria[$clave] ?? null;
        if ($recordada !== null && microtime(true) - $recordada[0] < self::MEMORIA_SEGUNDOS) {
            $fila = $recordada[1];
        } else {
            $fila = static::query()->where('clave', $clave)->first();
            self::$memoria[$clave] = [microtime(true), $fila];
        }

        if ($fila === null) {
            $base = self::DEFECTOS[$clave] ?? null;

            return $base === null ? $defecto : self::convertir($base['valor'], $base['tipo']);
        }

        return self::convertir($fila->valor, $fila->tipo);
    }

    /** Guarda un valor desde el panel, con el tipo del catálogo. Un secreto vacío conserva el anterior. */
    public static function guardar(string $clave, mixed $valor): void
    {
        $base = self::DEFECTOS[$clave];
        $fila = static::firstOrNew(['clave' => $clave], array_intersect_key($base, array_flip(self::COLUMNAS)));
        $fila->tipo = $base['tipo'];
        $fila->grupo = $base['grupo'];

        $fila->valor = match ($base['tipo']) {
            'secreto' => blank($valor) ? $fila->valor : Crypt::encryptString((string) $valor),
            'booleano' => filter_var($valor, FILTER_VALIDATE_BOOL) ? '1' : '0',
            'lista_montos' => json_encode(array_values(array_map('intval', (array) $valor))),
            default => $valor === null ? '' : trim((string) $valor),
        };
        $fila->save();
    }

    private static function convertir(?string $valor, string $tipo): mixed
    {
        if ($valor === null) {
            return null;
        }

        return match ($tipo) {
            'entero' => $valor === '' ? null : (int) $valor,
            'decimal' => $valor === '' ? null : (float) $valor,
            'booleano' => filter_var($valor, FILTER_VALIDATE_BOOL),
            'json', 'lista_montos' => json_decode($valor, true),
            'secreto' => self::descifrar($valor),
            default => $valor,
        };
    }

    private static function descifrar(string $valor): ?string
    {
        if ($valor === '') {
            return null;
        }
        try {
            return Crypt::decryptString($valor);
        } catch (Throwable) {
            return null;
        }
    }
}
