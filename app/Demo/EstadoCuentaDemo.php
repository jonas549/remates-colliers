<?php

namespace App\Demo;

/**
 * Estados simulados de la pantalla "Estado de cuenta" del prototipo (prop `estado`).
 * Cuando exista el modelo, el estado se deriva de la cuenta y de la garantía reales.
 */
class EstadoCuentaDemo
{
    public const ESTADOS = [
        'cuenta-revision' => 'Cuenta en revisión',
        'garantia-pendiente' => 'Garantía pendiente',
        'garantia-revision' => 'Garantía en revisión',
        'aprobada' => 'Aprobada',
        'rechazada' => 'Rechazada',
    ];

    /** Vista completa con los datos fijos del prototipo (solo local, ?estado=): misma forma que App\Postores\EstadoCuenta. */
    public static function vista(?string $clave): array
    {
        $estado = self::para($clave);
        $sala = route('sala.show', 'apoquindo');

        return [
            'estado' => $estado,
            'detalle' => [
                'remate' => ['titulo' => 'Los Militares 5620, Depto. 703', 'meta' => 'R-2026-118 · Las Condes · 09-09-2026, 12:00', 'url' => route('remates.show', 'militares'),
                    'sala' => $sala, 'abre' => '09-09-2026 a las 12:00', 'enVivo' => false],
                'garantia' => ['id' => 0, 'monto' => '$6.000.000', 'plazo' => '07-09, 18:00', 'medio' => 'vale a la vista', 'recibido' => '29-08-2026',
                    'aprobada' => '30-08-2026, 11:24', 'comprobante' => '#', 'subir' => null],
                'banco' => ['banco_nombre' => 'Banco de Chile', 'banco_tipo_cuenta' => 'Cuenta corriente', 'banco_numero_cuenta' => '000-12345-67',
                    'banco_titular' => 'Colliers International Chile S.A.', 'banco_rut_titular' => '96.123.456-7', 'vale_vista_direccion' => 'Av. Apoquindo 4499, piso 8, Las Condes'],
                'inscripciones' => [],
                'abiertos' => [],
                'basesGenerales' => '#',
            ],
        ];
    }

    public static function para(?string $clave): array
    {
        $clave = array_key_exists($clave ?? '', self::ESTADOS) ? $clave : 'aprobada';

        $mapa = [
            'cuenta-revision' => [
                'etiqueta' => 'CUENTA EN REVISIÓN',
                'titulo' => 'Estamos revisando tu registro',
                'texto' => 'Recibimos tus antecedentes. Un ejecutivo de Colliers los valida de forma manual y te informará por correo, normalmente dentro de 24 horas hábiles. Hasta entonces no puedes inscribirte en un remate.',
                'fondo' => '#25408f', 'etapa' => 1, 'garantia' => 'No iniciada',
            ],
            'garantia-pendiente' => [
                'etiqueta' => 'CUENTA APROBADA · GARANTÍA PENDIENTE',
                'titulo' => 'Tu cuenta está aprobada, falta la garantía',
                'texto' => 'Para pujar debes constituir la garantía por vale a la vista o transferencia y enviarnos el comprobante. Es un trámite externo a la plataforma: Colliers lo revisa y marca tu inscripción como aprobada o rechazada.',
                'fondo' => '#25408f', 'etapa' => 2, 'garantia' => 'Pendiente',
            ],
            'garantia-revision' => [
                'etiqueta' => 'GARANTÍA EN REVISIÓN',
                'titulo' => 'Recibimos tu comprobante de garantía',
                'texto' => 'Estamos verificando el vale a la vista con el área de finanzas. Te avisaremos por correo apenas quede aprobada; recién ahí se habilita la sala de pujas para este remate.',
                'fondo' => '#16265a', 'etapa' => 3, 'garantia' => 'En revisión',
            ],
            'aprobada' => [
                'etiqueta' => 'GARANTÍA APROBADA · HABILITADO PARA PUJAR',
                'titulo' => 'Estás habilitado para pujar',
                'texto' => 'Tu garantía por $6.000.000 fue aprobada para Los Militares 5620, Depto. 703. Podrás ingresar posturas en pesos cuando comience la transmisión del remate.',
                'fondo' => '#1c5330', 'etapa' => 4, 'garantia' => 'Aprobada',
            ],
            'rechazada' => [
                'etiqueta' => 'GARANTÍA RECHAZADA',
                'titulo' => 'No pudimos validar tu garantía',
                'texto' => 'El comprobante no corresponde al monto o al titular requerido para este remate. Puedes corregirlo y volver a enviarlo antes del cierre de garantías, o escribirnos si crees que se trata de un error.',
                'fondo' => '#8c0f22', 'etapa' => 2, 'garantia' => 'Rechazada',
            ],
        ][$clave];

        $tonoGarantia = match ($mapa['garantia']) {
            'Rechazada' => 'rojo',
            'En revisión' => 'azul',
            'Pendiente' => 'ambar',
            default => 'neutro',
        };

        $etapa = $mapa['etapa'];
        $pasos = [
            ['titulo' => 'Registro enviado', 'texto' => 'Datos personales y documentos recibidos.', 'chip' => 'Completado'],
            ['titulo' => 'Cuenta aprobada', 'texto' => 'Revisión manual de antecedentes por Colliers.', 'chip' => $etapa >= 2 ? 'Aprobada' : 'En revisión'],
            ['titulo' => 'Garantía constituida', 'texto' => 'Vale a la vista o transferencia, fuera de la plataforma.', 'chip' => $mapa['garantia']],
            ['titulo' => 'Habilitado para pujar', 'texto' => 'Se activa la sala de pujas del remate inscrito.', 'chip' => $etapa >= 4 ? 'Habilitado' : 'Bloqueado'],
        ];

        foreach ($pasos as $i => &$paso) {
            $n = $i + 1;
            $paso['hecho'] = $n < $etapa;
            $paso['actual'] = $n === $etapa;
            $paso['alcanzado'] = $n <= $etapa;
            $paso['numero'] = $paso['hecho'] ? '✓' : str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $paso['tono'] = $paso['hecho'] ? 'verde' : ($paso['actual'] ? ($tonoGarantia === 'neutro' ? 'azul' : $tonoGarantia) : 'neutro');
        }

        $habilitado = $clave === 'aprobada';

        return $mapa + [
            'clave' => $clave,
            'tonoGarantia' => $tonoGarantia,
            'pasos' => $pasos,
            'habilitado' => $habilitado,
            'tituloLateral' => $habilitado ? 'TU PRÓXIMO REMATE' : 'MIENTRAS TANTO',
            'textoLateral' => $habilitado
                ? 'Accesos directos a la sala de pujas, al catálogo y a los antecedentes de la propiedad en la que estás inscrito.'
                : 'Puedes revisar el catálogo de remates y los antecedentes de cada propiedad. La sala de pujas se habilita solo con la garantía aprobada.',
        ];
    }
}
