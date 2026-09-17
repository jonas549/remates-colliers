<?php

namespace App\Postores;

use App\Models\Configuracion;
use App\Models\Garantia;
use App\Models\Postor;
use App\Models\Remate;
use App\Models\User;
use App\Support\Formato;
use App\Support\Sitio;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Pantalla «Estado de cuenta» con datos reales (Bloques G y H). Devuelve la misma forma que usaba el prototipo
 * (`estado`: banda, pasos y textos) más `detalle`: el remate, la garantía, los datos para constituirla y las acciones.
 *
 * La garantía que se muestra: la del remate pedido (?remate=slug) o, si no, la más relevante (en vivo, luego la próxima
 * más cercana, luego la última).
 */
class EstadoCuenta
{
    public static function para(User $user, ?string $slug = null): array
    {
        $postor = $user->postor;
        $ahora = CarbonImmutable::now('UTC');
        $garantias = Garantia::with('remate.lotes')->where('user_id', $user->id)->get()
            ->filter(fn (Garantia $g) => ! $g->remate->es_demostracion)->values();
        $garantia = $slug ? $garantias->first(fn (Garantia $g) => $g->remate->slug === $slug) : self::relevante($garantias, $ahora);

        $clave = match (true) {
            $postor === null || in_array($postor->estado, [Postor::ESTADO_REGISTRADO, Postor::ESTADO_EN_REVISION], true) => 'cuenta-revision',
            $postor->estado === Postor::ESTADO_RECHAZADO => 'cuenta-rechazada',
            $postor->estado === Postor::ESTADO_BLOQUEADO => 'cuenta-bloqueada',
            $garantia === null => 'sin-inscripcion',
            $garantia->estado === Garantia::ESTADO_APROBADA => 'aprobada',
            $garantia->estado === Garantia::ESTADO_EN_REVISION => 'garantia-revision',
            $garantia->estado === Garantia::ESTADO_RECHAZADA => 'rechazada',
            default => 'garantia-pendiente',
        };

        $remate = $garantia?->remate;
        $horas = Sitio::horasRevision();
        $montoTexto = $garantia ? Formato::clp($garantia->monto) : '';
        $nombreRemate = $remate?->titulo ?? '';

        $textos = [
            'cuenta-revision' => ['CUENTA EN REVISIÓN', 'Estamos revisando tu registro', "Recibimos tus antecedentes. Un ejecutivo de Colliers los valida de forma manual y te informará por correo, normalmente dentro de {$horas} horas hábiles. Hasta entonces no puedes inscribirte en un remate.", '#25408f', 1, 'No iniciada'],
            'cuenta-rechazada' => ['CUENTA NO APROBADA', 'No pudimos aprobar tu cuenta', 'Colliers revisó tus antecedentes y no aprobó la cuenta' . ($postor?->motivo_rechazo ? ': ' . $postor->motivo_rechazo : '.') . ' Escríbenos si quieres corregir algún dato.', '#8c0f22', 1, 'No iniciada'],
            'cuenta-bloqueada' => ['CUENTA BLOQUEADA', 'Tu cuenta está bloqueada', 'No puedes inscribirte ni pujar mientras la cuenta esté bloqueada' . ($postor?->motivo_rechazo ? ' (' . $postor->motivo_rechazo . ')' : '') . '. Escríbenos para revisarlo.', '#8c0f22', 2, 'Bloqueada'],
            'sin-inscripcion' => ['CUENTA APROBADA · SIN INSCRIPCIONES', 'Tu cuenta está aprobada', 'Para pujar, inscríbete en un remate y constituye su garantía por vale a la vista o transferencia. Es un trámite externo a la plataforma: Colliers lo revisa y marca tu inscripción como aprobada o rechazada.', '#25408f', 2, 'Pendiente'],
            'garantia-pendiente' => ['CUENTA APROBADA · GARANTÍA PENDIENTE', 'Tu cuenta está aprobada, falta la garantía', 'Para pujar debes constituir la garantía por vale a la vista o transferencia y enviarnos el comprobante. Es un trámite externo a la plataforma: Colliers lo revisa y marca tu inscripción como aprobada o rechazada.', '#25408f', 2, 'Pendiente'],
            'garantia-revision' => ['GARANTÍA EN REVISIÓN', 'Recibimos tu comprobante de garantía', "Estamos verificando el comprobante. Te avisaremos por correo apenas quede aprobada, normalmente dentro de {$horas} horas hábiles; recién ahí se habilita la sala de pujas para este remate.", '#16265a', 3, 'En revisión'],
            'aprobada' => ['GARANTÍA APROBADA · HABILITADO PARA PUJAR', 'Estás habilitado para pujar', "Tu garantía por {$montoTexto} fue aprobada para {$nombreRemate}. Podrás ingresar posturas en pesos cuando comience la transmisión del remate.", '#1c5330', 4, 'Aprobada'],
            'rechazada' => ['GARANTÍA RECHAZADA', 'No pudimos validar tu garantía', 'Colliers no aprobó el comprobante' . ($garantia?->motivo_rechazo ? ': ' . $garantia->motivo_rechazo : '.') . ' Puedes corregirlo y volver a enviarlo antes del cierre de garantías, o escribirnos si crees que se trata de un error.', '#8c0f22', 2, 'Rechazada'],
        ][$clave];

        $estado = self::armar($clave, ...$textos);
        $puedeSubir = $garantia !== null && $postor?->estaAprobado() && in_array($garantia->estado, [Garantia::ESTADO_PENDIENTE, Garantia::ESTADO_RECHAZADA], true)
            && ($remate->cierre_garantias_en === null || $ahora->lessThan($remate->cierre_garantias_en)) && ! $remate->yaComenzo($ahora);
        $vista = $remate?->estadoVisible($ahora);

        return [
            'estado' => $estado,
            'detalle' => [
                'remate' => $remate ? [
                    'titulo' => $remate->titulo,
                    'meta' => collect([$remate->folio, $remate->lotes->pluck('comuna')->filter()->unique()->join(', '), Formato::fecha($remate->abreEn(), $ahora)])->filter()->join(' · '),
                    'url' => route('remates.show', $remate->slug),
                    'sala' => $clave === 'aprobada' && in_array($vista, [Remate::VISTA_EN_VIVO, Remate::VISTA_PROXIMO], true) ? route('sala.show', $remate->slug) : null,
                    'abre' => Formato::fecha($remate->abreEn(), $ahora),
                    'enVivo' => $vista === Remate::VISTA_EN_VIVO,
                ] : null,
                'garantia' => $garantia ? [
                    'id' => $garantia->id,
                    'monto' => $montoTexto,
                    'plazo' => $remate->cierre_garantias_en ? Formato::fechaCorta($remate->cierre_garantias_en, $ahora) : 'Hasta el inicio',
                    'medio' => ['vale_vista' => 'vale a la vista', 'transferencia' => 'transferencia'][$garantia->medio] ?? null,
                    'recibido' => $garantia->comprobante_subido_en ? Formato::fecha($garantia->comprobante_subido_en, $ahora) : null,
                    'aprobada' => $garantia->estado === Garantia::ESTADO_APROBADA && $garantia->revisado_en ? Formato::fecha($garantia->revisado_en, $ahora) : null,
                    'comprobante' => $garantia->tieneComprobante() ? route('cuenta.comprobante', $garantia) : null,
                    'subir' => $puedeSubir ? route('cuenta.comprobante.store', $garantia) : null,
                ] : null,
                'banco' => self::banco(),
                'inscripciones' => $garantias->map(fn (Garantia $g) => [
                    'titulo' => $g->remate->titulo, 'folio' => $g->remate->folio,
                    'estado' => ['pendiente' => 'Pendiente', 'en_revision' => 'En revisión', 'aprobada' => 'Aprobada', 'rechazada' => 'Rechazada'][$g->estado] ?? $g->estado,
                    'url' => route('cuenta.estado', ['remate' => $g->remate->slug]),
                    'actual' => $garantia?->id === $g->id,
                ])->all(),
                'abiertos' => $postor?->estaAprobado() ? self::rematesAbiertos($garantias, $ahora) : [],
                'basesGenerales' => Sitio::valor('enlace_bases_generales') ?: null,
            ],
        ];
    }

    /** @param Collection<int, Garantia> $garantias */
    private static function relevante(Collection $garantias, CarbonImmutable $ahora): ?Garantia
    {
        $orden = [Remate::VISTA_EN_VIVO => 0, Remate::VISTA_PROXIMO => 1];

        return $garantias->sortBy(fn (Garantia $g) => [
            $orden[$g->remate->estadoVisible($ahora)] ?? 2,
            ($orden[$g->remate->estadoVisible($ahora)] ?? 2) === 2 ? -$g->id : ($g->remate->abreEn()?->getTimestamp() ?? PHP_INT_MAX),
        ])->first();
    }

    /** Remates próximos con inscripción abierta, en los que el postor todavía no está inscrito. */
    private static function rematesAbiertos(Collection $garantias, CarbonImmutable $ahora): array
    {
        $inscritos = $garantias->pluck('remate_id')->all();

        return Remate::with('lotes')->whereIn('estado', [Remate::ESTADO_PUBLICADO])->where('es_demostracion', false)
            ->whereNotIn('id', $inscritos)->get()
            ->filter(fn (Remate $r) => $r->estadoVisible($ahora) === Remate::VISTA_PROXIMO
                && ($r->cierre_garantias_en === null || $ahora->lessThan($r->cierre_garantias_en)))
            ->sortBy(fn (Remate $r) => $r->abreEn()?->getTimestamp())->take(5)
            ->map(fn (Remate $r) => [
                'titulo' => $r->titulo, 'folio' => $r->folio, 'fecha' => Formato::fecha($r->abreEn(), $ahora),
                'garantia' => Formato::clp($r->montoGarantia()), 'url' => route('remates.show', $r->slug),
                'inscribir' => route('cuenta.inscribirme', $r->slug),
            ])->values()->all();
    }

    /** Datos para la transferencia o el vale a la vista (Administración → Configuración). Null si faltan. */
    public static function banco(): ?array
    {
        $datos = collect(['banco_nombre', 'banco_tipo_cuenta', 'banco_numero_cuenta', 'banco_titular', 'banco_rut_titular', 'vale_vista_direccion'])
            ->mapWithKeys(fn ($k) => [$k => (string) Configuracion::valor($k)]);

        return filled($datos['banco_numero_cuenta']) && filled($datos['banco_titular']) ? $datos->all() : null;
    }

    private static function armar(string $clave, string $etiqueta, string $titulo, string $texto, string $fondo, int $etapa, string $garantia): array
    {
        $tonoGarantia = match ($garantia) {
            'Rechazada', 'Bloqueada' => 'rojo',
            'En revisión' => 'azul',
            'Pendiente' => 'ambar',
            default => 'neutro',
        };
        $cuentaRechazada = in_array($clave, ['cuenta-rechazada', 'cuenta-bloqueada'], true);
        $pasos = [
            ['titulo' => 'Registro enviado', 'texto' => 'Datos personales y documentos recibidos.', 'chip' => 'Completado'],
            ['titulo' => 'Cuenta aprobada', 'texto' => 'Revisión manual de antecedentes por Colliers.', 'chip' => $clave === 'cuenta-rechazada' ? 'Rechazada' : ($clave === 'cuenta-bloqueada' ? 'Bloqueada' : ($etapa >= 2 ? 'Aprobada' : 'En revisión'))],
            ['titulo' => 'Garantía constituida', 'texto' => 'Vale a la vista o transferencia, fuera de la plataforma.', 'chip' => $garantia],
            ['titulo' => 'Habilitado para pujar', 'texto' => 'Se activa la sala de pujas del remate inscrito.', 'chip' => $etapa >= 4 ? 'Habilitado' : 'Bloqueado'],
        ];
        foreach ($pasos as $i => &$paso) {
            $n = $i + 1;
            $paso['hecho'] = $n < $etapa;
            $paso['actual'] = $n === $etapa;
            $paso['alcanzado'] = $n <= $etapa;
            $paso['numero'] = $paso['hecho'] ? '✓' : str_pad((string) $n, 2, '0', STR_PAD_LEFT);
            $tonoActual = $cuentaRechazada ? 'rojo' : ($tonoGarantia === 'neutro' ? 'azul' : $tonoGarantia);
            $paso['tono'] = $paso['hecho'] ? 'verde' : ($paso['actual'] ? $tonoActual : 'neutro');
        }
        $habilitado = $clave === 'aprobada';

        return [
            'clave' => $clave, 'etiqueta' => $etiqueta, 'titulo' => $titulo, 'texto' => $texto, 'fondo' => $fondo, 'etapa' => $etapa,
            'garantia' => $garantia, 'tonoGarantia' => $tonoGarantia, 'pasos' => $pasos, 'habilitado' => $habilitado,
            'tituloLateral' => $habilitado ? 'TU PRÓXIMO REMATE' : 'MIENTRAS TANTO',
            'textoLateral' => $habilitado
                ? 'Accesos directos a la sala de pujas, al catálogo y a los antecedentes de la propiedad en la que estás inscrito.'
                : 'Puedes revisar el catálogo de remates y los antecedentes de cada propiedad. La sala de pujas se habilita solo con la garantía aprobada.',
        ];
    }
}
