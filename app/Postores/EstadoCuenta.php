<?php

namespace App\Postores;

use App\Models\Adjudicacion;
use App\Models\Configuracion;
use App\Models\Garantia;
use App\Models\Postor;
use App\Models\Remate;
use App\Models\User;
use App\Subastas\Liquidador;
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
    /** Estados de la garantía con etiqueta propia en «Mi cuenta» (lang/es/cuenta.php, «estados»). */
    private const ESTADOS_GARANTIA = ['pendiente', 'en_revision', 'aprobada', 'rechazada'];

    public static function para(User $user, ?string $slug = null): array
    {
        $postor = $user->postor;
        $ahora = CarbonImmutable::now('UTC');
        // Todas las del postor, también las de un remate de prueba: esa es la cuenta de un postor de prueba, y un postor
        // real no puede inscribirse en uno (InscripcionGarantias). Mostrar menos sería mostrar un estado falso (21/09).
        // Cierre perezoso: los lotes vencidos se materializan antes de mostrar adjudicaciones (sin esperar al cron).
        $liquidador = app(Liquidador::class);
        Garantia::with('remate')->where('user_id', $user->id)->get()->pluck('remate')->filter()->unique('id')
            ->filter(fn (Remate $r) => $r->estadoVisible($ahora) === Remate::VISTA_EN_VIVO)
            ->each(fn (Remate $r) => $liquidador->liquidarVencidos($r, $ahora));
        $garantias = Garantia::with('remate.lotes')->where('user_id', $user->id)->get();
        $garantia = $slug ? $garantias->first(fn (Garantia $g) => $g->remate->slug === $slug) : self::relevante($garantias, $ahora);

        $clave = match (true) {
            $postor === null || in_array($postor->estado, [Postor::ESTADO_REGISTRADO, Postor::ESTADO_EN_REVISION], true) => 'cuenta-revision',
            $postor->estado === Postor::ESTADO_RECHAZADO => 'cuenta-rechazada',
            $postor->estado === Postor::ESTADO_BLOQUEADO => 'cuenta-bloqueada',
            $garantia === null => 'sin-inscripcion',
            // Remate cancelado por Colliers (21/09): «habilitado para pujar» sería falso, el remate no va a ocurrir.
            $garantia->remate?->estado === Remate::ESTADO_CANCELADO => 'remate-cancelado',
            $garantia->estado === Garantia::ESTADO_APROBADA => 'aprobada',
            $garantia->estado === Garantia::ESTADO_EN_REVISION => 'garantia-revision',
            $garantia->estado === Garantia::ESTADO_RECHAZADA => 'rechazada',
            default => 'garantia-pendiente',
        };

        $remate = $garantia?->remate;
        // Habilitación única (modo del panel): la garantía no pertenece a un remate, habilita todos.
        $unica = $garantia?->esUnica() === true;
        $modoUnico = Habilitacion::modo() === Habilitacion::MODO_UNICO;
        $horas = Sitio::horasRevision();
        $montoTexto = $garantia ? Formato::clp($garantia->monto) : '';
        $nombreRemate = $remate?->titulo ?? '';
        // Adjudicaciones del postor (QA 21/10: el ganador entraba después del cierre y solo leía el texto genérico).
        $adjudicaciones = Adjudicacion::with('lote.remate.lotes')->where('user_id', $user->id)
            ->where('estado', '!=', Adjudicacion::ESTADO_INCUMPLIDO)->orderByDesc('cerrado_en')->get();
        // Remate terminado: «Estás habilitado para pujar» sería falso.
        $finalizado = $clave === 'aprobada' && in_array($remate?->estadoVisible($ahora), [Remate::VISTA_ADJUDICADO, Remate::VISTA_CERRADO], true);
        $ganoAqui = $remate !== null && $adjudicaciones->contains(fn (Adjudicacion $a) => $a->lote?->remate_id === $remate->id);

        $b = fn (string $clave, array $marcadores = []) => __('cuenta.bandas.' . $clave, $marcadores);
        $textos = match ($clave) {
            'cuenta-revision' => [$b('cuenta_revision.etiqueta'), $b('cuenta_revision.titulo'), $b('cuenta_revision.texto', ['horas' => $horas]), '#25408f', 1, 'no_iniciada'],
            'cuenta-rechazada' => [$b('cuenta_rechazada.etiqueta'), $b('cuenta_rechazada.titulo'), $postor?->motivo_rechazo
                ? $b('cuenta_rechazada.texto_motivo', ['motivo' => $postor->motivo_rechazo]) : $b('cuenta_rechazada.texto'), '#8c0f22', 1, 'no_iniciada'],
            'cuenta-bloqueada' => [$b('cuenta_bloqueada.etiqueta'), $b('cuenta_bloqueada.titulo'), $postor?->motivo_rechazo
                ? $b('cuenta_bloqueada.texto_motivo', ['motivo' => $postor->motivo_rechazo]) : $b('cuenta_bloqueada.texto'), '#8c0f22', 2, 'bloqueada'],
            'sin-inscripcion' => [$b('sin_inscripcion.etiqueta'), $b('sin_inscripcion.titulo'), $modoUnico
                ? (Habilitacion::montoUnico() ? $b('sin_inscripcion.texto_unica_monto', ['monto' => Formato::clp(Habilitacion::montoUnico())]) : $b('sin_inscripcion.texto_unica'))
                : $b('sin_inscripcion.texto'), '#25408f', 2, 'pendiente'],
            'garantia-pendiente' => [$b('garantia_pendiente.etiqueta'), $b('garantia_pendiente.titulo'), $b('garantia_pendiente.texto'), '#25408f', 2, 'pendiente'],
            'garantia-revision' => [$b('garantia_revision.etiqueta'), $b('garantia_revision.titulo'), $b($unica ? 'garantia_revision.texto_unica' : 'garantia_revision.texto', ['horas' => $horas]), '#16265a', 3, 'en_revision'],
            'aprobada' => $finalizado ? [$b('finalizado.etiqueta'), $b($ganoAqui ? 'finalizado.titulo_ganado' : 'finalizado.titulo'),
                $b($ganoAqui ? 'finalizado.texto_ganado' : 'finalizado.texto', ['remate' => $nombreRemate]), $ganoAqui ? '#1c5330' : '#5b6572', 4, 'aprobada']
                : [$b('aprobada.etiqueta'), $b('aprobada.titulo'), $unica
                ? $b('aprobada.texto_unica', ['monto' => $montoTexto])
                : $b('aprobada.texto', ['monto' => $montoTexto, 'remate' => $nombreRemate]), '#1c5330', 4, 'aprobada'],
            'remate-cancelado' => [$b('remate_cancelado.etiqueta'), $b('remate_cancelado.titulo'), $remate?->motivo_cancelacion
                ? $b('remate_cancelado.texto_motivo', ['remate' => $nombreRemate, 'motivo' => $remate->motivo_cancelacion])
                : $b('remate_cancelado.texto', ['remate' => $nombreRemate]),
                '#5b6572', 3, in_array($garantia?->estado, self::ESTADOS_GARANTIA, true) ? $garantia->estado : 'pendiente'],
            'rechazada' => [$b('rechazada.etiqueta'), $b('rechazada.titulo'), $garantia?->motivo_rechazo
                ? $b('rechazada.texto_motivo', ['motivo' => $garantia->motivo_rechazo]) : $b('rechazada.texto'), '#8c0f22', 2, 'rechazada'],
        };

        // Solo la habilitación ÚNICA aprobada sirve para todos los remates.
        $habilitadoEnTodos = $modoUnico && $unica && $garantia?->estado === Garantia::ESTADO_APROBADA;
        $estado = self::armar($clave, ...$textos);
        $puedeSubir = $garantia !== null && $clave !== 'remate-cancelado' && $postor?->estaAprobado() && in_array($garantia->estado, [Garantia::ESTADO_PENDIENTE, Garantia::ESTADO_RECHAZADA], true)
            && ($unica || (($remate->cierre_garantias_en === null || $ahora->lessThan($remate->cierre_garantias_en)) && ! $remate->yaComenzo($ahora)));
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
                    'finalizado' => $finalizado,
                    'cancelado' => $remate->cancelado_en ? Formato::fecha($remate->cancelado_en, $ahora) : null,
                ] : null,
                'garantia' => $garantia ? [
                    'id' => $garantia->id,
                    'monto' => $montoTexto,
                    'plazo' => $unica ? __('cuenta.garantia.plazo_unica')
                        : ($remate->cierre_garantias_en ? Formato::fechaCorta($remate->cierre_garantias_en, $ahora) : __('cuenta.garantia.plazo_inicio')),
                    'medio' => in_array($garantia->medio, ['vale_vista', 'transferencia'], true) ? __('cuenta.garantia.medios.' . $garantia->medio) : null,
                    'recibido' => $garantia->comprobante_subido_en ? Formato::fecha($garantia->comprobante_subido_en, $ahora) : null,
                    'aprobada' => $garantia->estado === Garantia::ESTADO_APROBADA && $garantia->revisado_en ? Formato::fecha($garantia->revisado_en, $ahora) : null,
                    'comprobante' => $garantia->tieneComprobante() ? route('cuenta.comprobante', $garantia) : null,
                    'subir' => $puedeSubir ? route('cuenta.comprobante.store', $garantia) : null,
                ] : null,
                'banco' => self::banco(),
                // Mismo texto que la sala al terminar un lote (sala.js.lote_ganado).
                'adjudicaciones' => $adjudicaciones->filter(fn (Adjudicacion $a) => $a->lote?->remate !== null)->map(fn (Adjudicacion $a) => [
                    'remate' => $a->lote->remate->titulo . ' · ' . $a->lote->remate->folio,
                    'texto' => __('sala.js.lote_ganado', ['orden' => $a->lote->orden, 'precio' => Formato::clp($a->monto)]),
                    'url' => route('cuenta.estado', ['remate' => $a->lote->remate->slug]),
                ])->values()->all(),
                'inscripciones' => $garantias->map(fn (Garantia $g) => [
                    'titulo' => $g->remate->titulo, 'folio' => $g->remate->folio,
                    'estado' => in_array($g->estado, self::ESTADOS_GARANTIA, true) ? __('cuenta.estados.' . $g->estado) : $g->estado,
                    'url' => route('cuenta.estado', ['remate' => $g->remate->slug]),
                    'actual' => $garantia?->id === $g->id,
                ])->all(),
                // Un postor de prueba solo puja en su remate de prueba: no se le ofrecen los reales.
                'abiertos' => $postor?->estaAprobado() && ! $habilitadoEnTodos && ! $user->esDePrueba()
                    ? self::rematesAbiertos($garantias, $ahora, $modoUnico) : [],
                'dePrueba' => $user->esDePrueba(),
                'habilitadoEnTodos' => $habilitadoEnTodos,
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
    private static function rematesAbiertos(Collection $garantias, CarbonImmutable $ahora, bool $modoUnico = false): array
    {
        // En modo único, una habilitación ya constituida cubre cualquier remate: no se ofrece constituirla de nuevo.
        if ($modoUnico) {
            return $garantias->contains(fn (Garantia $g) => $g->esUnica()) ? [] : self::rematesParaHabilitar($ahora);
        }
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

    /** Modo único: cualquier remate publicado sirve de punto de partida para constituir la habilitación. */
    private static function rematesParaHabilitar(CarbonImmutable $ahora): array
    {
        return Remate::with('lotes')->where('estado', Remate::ESTADO_PUBLICADO)->where('es_demostracion', false)->get()
            ->filter(fn (Remate $r) => in_array($r->estadoVisible($ahora), [Remate::VISTA_PROXIMO, Remate::VISTA_EN_VIVO], true))
            ->sortBy(fn (Remate $r) => $r->abreEn()?->getTimestamp())->take(5)
            ->map(fn (Remate $r) => [
                'titulo' => $r->titulo, 'folio' => $r->folio, 'fecha' => Formato::fecha($r->abreEn(), $ahora),
                'garantia' => __('cuenta.garantia.monto_unica', ['monto' => Formato::clp(Habilitacion::montoUnico() ?? 0)]),
                'url' => route('remates.show', $r->slug), 'inscribir' => route('cuenta.inscribirme', $r->slug),
            ])->values()->all();
    }

    /** Datos para la transferencia o el vale a la vista (Administración → Configuración). Null si faltan. */
    public static function banco(): ?array
    {
        $datos = collect(['banco_nombre', 'banco_tipo_cuenta', 'banco_numero_cuenta', 'banco_titular', 'banco_rut_titular', 'vale_vista_direccion'])
            ->mapWithKeys(fn ($k) => [$k => (string) Configuracion::valor($k)]);

        return filled($datos['banco_numero_cuenta']) && filled($datos['banco_titular']) ? $datos->all() : null;
    }

    /** $garantia: clave del estado de la garantía (no_iniciada, pendiente, en_revision, aprobada, rechazada, bloqueada). */
    private static function armar(string $clave, string $etiqueta, string $titulo, string $texto, string $fondo, int $etapa, string $garantia): array
    {
        $tonoGarantia = match ($garantia) {
            'rechazada', 'bloqueada' => 'rojo',
            'en_revision' => 'azul',
            'pendiente' => 'ambar',
            default => 'neutro',
        };
        $etiquetaGarantia = __('cuenta.estados.' . $garantia);
        $cuentaRechazada = in_array($clave, ['cuenta-rechazada', 'cuenta-bloqueada'], true);
        $pasos = [
            ['titulo' => __('cuenta.pasos.registro.titulo'), 'texto' => __('cuenta.pasos.registro.texto'), 'chip' => __('cuenta.estados.completado')],
            ['titulo' => __('cuenta.pasos.cuenta.titulo'), 'texto' => __('cuenta.pasos.cuenta.texto'), 'chip' => __('cuenta.estados.' . ($clave === 'cuenta-rechazada' ? 'rechazada' : ($clave === 'cuenta-bloqueada' ? 'bloqueada' : ($etapa >= 2 ? 'aprobada' : 'en_revision'))))],
            ['titulo' => __('cuenta.pasos.garantia.titulo'), 'texto' => __('cuenta.pasos.garantia.texto'), 'chip' => $etiquetaGarantia],
            ['titulo' => __('cuenta.pasos.habilitado.titulo'), 'texto' => __('cuenta.pasos.habilitado.texto'), 'chip' => __('cuenta.estados.' . ($clave === 'remate-cancelado' ? 'remate_cancelado' : ($etapa >= 4 ? 'habilitado' : 'bloqueado')))],
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
            'garantia' => $etiquetaGarantia, 'tonoGarantia' => $tonoGarantia, 'pasos' => $pasos, 'habilitado' => $habilitado,
            'tituloLateral' => __($habilitado ? 'cuenta.lateral.titulo_habilitado' : 'cuenta.lateral.titulo'),
            'textoLateral' => __($habilitado ? 'cuenta.lateral.texto_habilitado' : 'cuenta.lateral.texto'),
        ];
    }
}
