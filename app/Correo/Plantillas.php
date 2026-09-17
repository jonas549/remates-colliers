<?php

namespace App\Correo;

use App\Models\PlantillaCorreo;
use App\Support\Sitio;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * Catálogo de los correos que envía la plataforma (Bloque V, 17/09). El texto de aquí es el ORIGINAL: si un
 * administrador edita una plantilla, la versión editada vive en `plantillas_correo` y esta queda como restauración.
 *
 * El cuerpo se escribe en texto plano, con un párrafo por línea en blanco y variables entre llaves: {{ nombre }}.
 * El enlace del botón lo pone el sistema (no se edita): cambia según el remate, la cuenta o la garantía.
 */
class Plantillas
{
    /** Variables comunes a todos los correos. */
    public const COMUNES = [
        'nombre' => 'Nombre de quien recibe el correo',
        'contacto' => 'Correo de contacto de Colliers',
        'telefono' => 'Teléfono de contacto',
        'sitio' => 'Dirección del sitio',
    ];

    /**
     * clave => nombre, cuándo se envía, a quién, variables propias, asunto, cuerpo y texto del botón.
     * `activable` en false: correos que no se pueden apagar (los de la cuenta del propio postor).
     */
    public const CATALOGO = [
        'bienvenida' => [
            'nombre' => 'Bienvenida y confirmación de correo',
            'cuando' => 'Al registrarse un postor',
            'destinatario' => 'El postor que se registra',
            'activable' => false,
            'variables' => [],
            'asunto' => 'Confirma tu correo',
            'cuerpo' => "Recibimos tu registro como postor en Remates Colliers.\n\nConfirma tu correo para que Colliers revise tus antecedentes. La revisión es manual y te avisamos por este mismo medio.\n\nSi no te registraste, ignora este mensaje.",
            'boton' => 'Confirmar mi correo',
        ],
        'restablecer_clave' => [
            'nombre' => 'Restablecer la contraseña',
            'cuando' => 'Al pedir recuperar la contraseña',
            'destinatario' => 'Quien la pidió',
            'activable' => false,
            'variables' => ['minutos' => 'Minutos que dura el enlace'],
            'asunto' => 'Restablecer tu contraseña',
            'cuerpo' => "Pediste restablecer la contraseña de tu cuenta.\n\nEl enlace vence en {{ minutos }} minutos. Si no lo pediste, ignora este mensaje: tu contraseña no cambia.",
            'boton' => 'Crear una contraseña nueva',
        ],
        'cuenta_aprobada' => [
            'nombre' => 'Cuenta aprobada',
            'cuando' => 'Cuando Colliers aprueba la cuenta',
            'destinatario' => 'El postor',
            'activable' => true,
            'variables' => [],
            'asunto' => 'Tu cuenta fue aprobada',
            'cuerpo' => "Colliers revisó tus antecedentes y aprobó tu cuenta de postor.\n\nYa puedes inscribirte en un remate: al hacerlo verás el monto de la garantía y cómo constituirla (vale a la vista o transferencia, fuera de la plataforma).",
            'boton' => 'Ver remates publicados',
        ],
        'cuenta_rechazada' => [
            'nombre' => 'Cuenta rechazada',
            'cuando' => 'Cuando Colliers rechaza la cuenta',
            'destinatario' => 'El postor',
            'activable' => true,
            'variables' => ['motivo' => 'Motivo escrito por quien rechazó'],
            'asunto' => 'No pudimos aprobar tu cuenta',
            'cuerpo' => "Colliers revisó tus antecedentes y no aprobó tu cuenta.\n\nMotivo: {{ motivo }}\n\nSi quieres corregir algún dato o enviar documentos, escríbenos a {{ contacto }}.",
            'boton' => null,
        ],
        'cuenta_bloqueada' => [
            'nombre' => 'Cuenta bloqueada',
            'cuando' => 'Cuando Colliers bloquea la cuenta',
            'destinatario' => 'El postor',
            'activable' => true,
            'variables' => ['motivo' => 'Motivo escrito por quien bloqueó'],
            'asunto' => 'Tu cuenta fue bloqueada',
            'cuerpo' => "Tu cuenta quedó bloqueada: no puedes inscribirte ni pujar mientras se revisa.\n\nMotivo: {{ motivo }}\n\nEscríbenos a {{ contacto }} para más información.",
            'boton' => null,
        ],
        'cuenta_desbloqueada' => [
            'nombre' => 'Cuenta desbloqueada',
            'cuando' => 'Cuando Colliers desbloquea la cuenta',
            'destinatario' => 'El postor',
            'activable' => true,
            'variables' => [],
            'asunto' => 'Tu cuenta fue desbloqueada',
            'cuerpo' => 'Tu cuenta fue desbloqueada: ya puedes volver a inscribirte y pujar.',
            'boton' => 'Ir a mi cuenta',
        ],
        'comprobante_recibido' => [
            'nombre' => 'Comprobante recibido',
            'cuando' => 'Cuando el postor sube el comprobante de la garantía',
            'destinatario' => 'El postor',
            'activable' => true,
            'variables' => ['remate' => 'Título del remate', 'folio' => 'Folio del remate', 'monto' => 'Monto de la garantía', 'horas' => 'Horas hábiles de revisión'],
            'asunto' => 'Recibimos tu comprobante',
            'cuerpo' => "Recibimos el comprobante de la garantía de {{ remate }} ({{ folio }}) por {{ monto }}.\n\nColliers lo revisa manualmente dentro de {{ horas }} horas hábiles y te avisamos por correo.",
            'boton' => 'Ver el estado de mi cuenta',
        ],
        'garantia_aprobada' => [
            'nombre' => 'Garantía aprobada',
            'cuando' => 'Cuando Colliers aprueba la garantía',
            'destinatario' => 'El postor',
            'activable' => true,
            'variables' => ['remate' => 'Título del remate', 'folio' => 'Folio del remate', 'monto' => 'Monto de la garantía', 'inicio' => 'Fecha y hora de inicio'],
            'asunto' => 'Tu garantía fue aprobada',
            'cuerpo' => "Tu garantía de {{ monto }} para {{ remate }} ({{ folio }}) quedó aprobada: estás habilitado para pujar.\n\nEl remate comienza el {{ inicio }}. Entra a la sala unos minutos antes: el precio y el cronómetro de la plataforma son los oficiales, el video va con algunos segundos de retraso.",
            'boton' => 'Ver el remate',
        ],
        'garantia_rechazada' => [
            'nombre' => 'Garantía rechazada',
            'cuando' => 'Cuando Colliers rechaza la garantía',
            'destinatario' => 'El postor',
            'activable' => true,
            'variables' => ['remate' => 'Título del remate', 'folio' => 'Folio del remate', 'motivo' => 'Motivo escrito por quien rechazó'],
            'asunto' => 'No pudimos aprobar tu garantía',
            'cuerpo' => "Revisamos el comprobante de la garantía de {{ remate }} ({{ folio }}) y no pudimos aprobarla.\n\nMotivo: {{ motivo }}\n\nPuedes subir otro comprobante desde tu cuenta mientras el plazo siga abierto.",
            'boton' => 'Subir otro comprobante',
        ],
        'recordatorio_remate' => [
            'nombre' => 'Recordatorio antes del remate',
            'cuando' => 'Las horas antes del inicio que fija Notificaciones',
            'destinatario' => 'Inscritos con garantía aprobada y quienes pidieron «Avísame»',
            'activable' => true,
            'variables' => ['remate' => 'Título del remate', 'folio' => 'Folio del remate', 'inicio' => 'Fecha y hora de inicio', 'faltan' => 'Cuánto falta (ej.: 24 horas)'],
            'asunto' => 'Tu remate comienza pronto',
            'cuerpo' => "{{ remate }} ({{ folio }}) comienza el {{ inicio }}, en {{ faltan }}.\n\nEl precio y el cronómetro de la plataforma son los oficiales: el video de la transmisión llega con algunos segundos de retraso.",
            'boton' => 'Ver el remate',
        ],
        'recordatorio_garantia' => [
            'nombre' => 'Recordatorio: falta la garantía',
            'cuando' => 'Junto con el recordatorio, si el postor se inscribió y su garantía no está aprobada',
            'destinatario' => 'El postor inscrito',
            'activable' => true,
            'variables' => ['remate' => 'Título del remate', 'folio' => 'Folio del remate', 'inicio' => 'Fecha y hora de inicio', 'cierre_garantias' => 'Cierre del plazo de garantías', 'faltan' => 'Cuánto falta'],
            'asunto' => 'Falta aprobar tu garantía',
            'cuerpo' => "{{ remate }} ({{ folio }}) comienza el {{ inicio }} y tu garantía todavía no está aprobada.

El plazo para constituirla cierra el {{ cierre_garantias }}. Sin la garantía aprobada no puedes pujar.",
            'boton' => 'Ver mi garantía',
        ],
        'recordatorio_cierre_garantias' => [
            'nombre' => 'Recordatorio: cierra el plazo de garantías',
            'cuando' => '48 horas antes del cierre del plazo de garantías',
            'destinatario' => 'Quienes pidieron avisos de remates',
            'activable' => true,
            'variables' => ['remate' => 'Título del remate', 'folio' => 'Folio del remate', 'inicio' => 'Fecha y hora de inicio', 'cierre_garantias' => 'Cierre del plazo de garantías', 'baja' => 'Enlace para darse de baja'],
            'asunto' => 'Cierra el plazo de garantías',
            'cuerpo' => "El plazo para constituir la garantía de {{ remate }} ({{ folio }}) cierra el {{ cierre_garantias }}.

El remate comienza el {{ inicio }}. Para pujar necesitas cuenta aprobada y garantía aprobada para ese remate.",
            'boton' => 'Ver el remate',
        ],
        'adjudicacion' => [
            'nombre' => 'Adjudicación (ganador)',
            'cuando' => 'Al cerrar el lote, si hubo ganador',
            'destinatario' => 'El adjudicatario',
            'activable' => true,
            'variables' => ['remate' => 'Título del remate', 'folio' => 'Folio del remate', 'lote' => 'Título del lote', 'monto' => 'Monto adjudicado', 'cierre' => 'Fecha y hora del cierre'],
            'asunto' => 'Te adjudicaste la propiedad',
            'cuerpo' => "Te adjudicaste {{ lote }} de {{ remate }} ({{ folio }}) en {{ monto }}, el {{ cierre }}.\n\nColliers se comunicará contigo para la firma y el pago. Si tienes dudas, escríbenos a {{ contacto }}.",
            'boton' => 'Ver el remate',
        ],
        'no_adjudicado' => [
            'nombre' => 'Resultado del lote (no adjudicado)',
            'cuando' => 'Al cerrar el lote, a quienes pujaron y no ganaron',
            'destinatario' => 'Los postores que pujaron en ese lote',
            'activable' => true,
            'variables' => ['remate' => 'Título del remate', 'folio' => 'Folio del remate', 'lote' => 'Título del lote', 'monto' => 'Monto adjudicado', 'cierre' => 'Fecha y hora del cierre'],
            'asunto' => 'Resultado del remate',
            'cuerpo' => "{{ lote }} de {{ remate }} ({{ folio }}) se adjudicó en {{ monto }} el {{ cierre }}. Tu puja no fue la más alta.\n\nSobre tu garantía, escríbenos a {{ contacto }}. Cuando publiquemos remates nuevos te avisamos si pediste el aviso.",
            'boton' => 'Ver remates publicados',
        ],
        'remate_nuevo' => [
            'nombre' => 'Remate nuevo publicado («Avísame»)',
            'cuando' => 'Al publicar un remate',
            'destinatario' => 'Quienes se suscribieron a los avisos',
            'activable' => true,
            'variables' => ['remate' => 'Título del remate', 'folio' => 'Folio del remate', 'inicio' => 'Fecha y hora de inicio', 'base' => 'Precio base', 'baja' => 'Enlace para darse de baja'],
            'asunto' => 'Remate nuevo publicado',
            'cuerpo' => "Publicamos {{ remate }} ({{ folio }}).\n\nComienza el {{ inicio }} y su precio base es {{ base }}. Para pujar necesitas cuenta aprobada y la garantía de ese remate aprobada.",
            'boton' => 'Ver el remate',
        ],
        'resumen_remate' => [
            'nombre' => 'Resumen del remate (administración)',
            'cuando' => 'Al cerrar el último lote del remate',
            'destinatario' => 'El correo de avisos o los administradores',
            'activable' => true,
            'variables' => ['remate' => 'Título del remate', 'folio' => 'Folio del remate', 'cierre' => 'Fecha y hora del cierre', 'resumen' => 'Lista de lotes con su resultado y monto', 'total' => 'Total adjudicado', 'adjudicados' => 'Cantidad de lotes adjudicados', 'lotes' => 'Cantidad de lotes'],
            'asunto' => 'Remate cerrado: resumen',
            'cuerpo' => "{{ remate }} ({{ folio }}) cerró el {{ cierre }}.\n\n{{ resumen }}\n\nAdjudicados: {{ adjudicados }} de {{ lotes }}. Total adjudicado: {{ total }}.",
            'boton' => 'Ver el remate en el panel',
        ],
    ];

    /** Plantilla vigente: la editada si existe, si no la original. @return array{asunto: string, cuerpo: string, boton: ?string, editada: bool} */
    public static function vigente(string $clave): array
    {
        $original = self::CATALOGO[$clave];
        $editada = PlantillaCorreo::where('clave', $clave)->first();

        return [
            'asunto' => $editada->asunto ?? $original['asunto'],
            'cuerpo' => $editada->cuerpo ?? $original['cuerpo'],
            'boton' => $editada ? $editada->boton : $original['boton'],
            'editada' => $editada !== null,
        ];
    }

    /**
     * Texto listo para enviar, con las variables reemplazadas.
     *
     * @param  array<string, string|int|null>  $datos
     * @return array{asunto: string, parrafos: list<string>, boton: ?string}
     */
    public static function render(string $clave, array $datos = []): array
    {
        $plantilla = self::vigente($clave);
        $valores = $datos + [
            'contacto' => Sitio::correo(),
            'telefono' => Sitio::telefono(),
            'sitio' => rtrim((string) config('app.url'), '/'),
        ];
        $reemplazar = fn (?string $texto) => $texto === null ? null : preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/i',
            fn (array $m) => (string) ($valores[$m[1]] ?? ''),
            $texto,
        );

        $cuerpo = trim((string) $reemplazar($plantilla['cuerpo']));

        return [
            'asunto' => trim((string) $reemplazar($plantilla['asunto'])),
            'parrafos' => collect(preg_split('/\R{2,}/', $cuerpo))->map(fn ($p) => trim($p))->filter()->values()->all(),
            'boton' => $plantilla['boton'] ? trim((string) $reemplazar($plantilla['boton'])) : null,
        ];
    }

    /**
     * Correo listo para los avisos que no pasan por AvisoColliers (los de Fortify: confirmar correo y restablecer clave).
     *
     * @param  array<string, string|int|null>  $datos
     */
    public static function correo(string $clave, ?string $nombre, ?string $enlace = null, array $datos = []): MailMessage
    {
        $texto = self::render($clave, ['nombre' => $nombre] + $datos);
        $correo = (new MailMessage)
            ->subject($texto['asunto'] . ' · Remates Colliers')
            ->greeting($nombre ? "Hola {$nombre}" : 'Hola');

        foreach ($texto['parrafos'] as $parrafo) {
            $correo->line($parrafo);
        }
        if ($texto['boton'] !== null && $enlace !== null) {
            $correo->action($texto['boton'], $enlace);
        }

        return $correo->salutation('Colliers Chile · ' . Sitio::correo() . (Sitio::telefono() ? ' · ' . Sitio::telefono() : ''));
    }

    /** Variables que acepta una plantilla (propias + comunes). @return array<string, string> */
    public static function variables(string $clave): array
    {
        return (self::CATALOGO[$clave]['variables'] ?? []) + self::COMUNES;
    }

    /** Datos de ejemplo para la vista previa del panel. @return array<string, string> */
    public static function ejemplo(string $clave): array
    {
        $ejemplos = [
            'nombre' => 'María Paz González', 'motivo' => 'El comprobante no corresponde al monto de la garantía.',
            'remate' => 'Av. Apoquindo 4501, Depto. 1802', 'folio' => 'R-2026-114', 'lote' => 'Lote 1 · Av. Apoquindo 4501',
            'monto' => '$19.850.000', 'inicio' => 'viernes 26 de septiembre, 12:00', 'cierre' => 'viernes 26 de septiembre, 12:30',
            'horas' => '24', 'minutos' => '60', 'faltan' => '24 horas', 'base' => '$185.000.000', 'baja' => rtrim((string) config('app.url'), '/') . '/avisame/baja/ejemplo',
            'resumen' => "Lote 1 · Av. Apoquindo 4501: adjudicado en $199.600.000\nLote 2 · Estacionamiento 12: desierto",
            'total' => '$199.600.000', 'adjudicados' => '1', 'lotes' => '2',
        ];

        return array_intersect_key($ejemplos, self::variables($clave)) + ['nombre' => $ejemplos['nombre']];
    }

    public static function nombre(string $clave): string
    {
        return self::CATALOGO[$clave]['nombre'] ?? Str::headline($clave);
    }
}
