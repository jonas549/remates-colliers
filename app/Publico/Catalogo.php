<?php

namespace App\Publico;

use App\Models\Configuracion;
use App\Models\Documento;
use App\Models\Garantia;
use App\Models\Lote;
use App\Models\LoteVisita;
use App\Models\Remate;
use App\Postores\Habilitacion;
use App\Models\User;
use App\Subastas\EstadoRemate;
use App\Support\Formato;
use App\Support\Sitio;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Sitio público con datos reales (Bloque N). Arma la misma forma de datos que usaban las vistas del Bloque T
 * (App\Demo\RematesDemo y DetalleDemo), para no reescribir las plantillas del diseño.
 *
 * Se publican remates publicados, en curso y finalizados; nunca borradores, cancelados ni de demostración (estos últimos
 * se ven solo con el enlace directo, para las pruebas del sandbox).
 */
class Catalogo
{
    /** @return Collection<int, Remate> */
    public static function remates(): Collection
    {
        return Remate::with(['lotes.imagenes', 'lotes.visitas', 'martillero', 'republicaciones'])
            ->whereIn('estado', [Remate::ESTADO_PUBLICADO, Remate::ESTADO_EN_CURSO, Remate::ESTADO_FINALIZADO])
            ->where('es_demostracion', false)->get();
    }

    /** Filas del listado (forma de RematesDemo::todos()). */
    public static function listado(?CarbonImmutable $ahora = null): array
    {
        $ahora ??= CarbonImmutable::now('UTC');

        return self::remates()->map(fn (Remate $r) => self::tarjeta($r, $ahora))->filter()->values()->all();
    }

    public static function tarjeta(Remate $r, CarbonImmutable $ahora): ?array
    {
        $vista = $r->estadoVisible($ahora);
        $lote = self::loteVigente($r);
        if ($lote === null) {
            return null;
        }
        $estado = match ($vista) {
            Remate::VISTA_EN_VIVO => 'En vivo',
            Remate::VISTA_PROXIMO => 'Próximo',
            default => 'Cerrado',
        };
        $objetivo = $vista === Remate::VISTA_EN_VIVO ? $lote->cierra_en : ($vista === Remate::VISTA_PROXIMO ? $r->abreEn() : null);
        $visita = $lote->visitas->first(fn (LoteVisita $v) => $v->inicia_en->greaterThan($ahora));
        $varios = $r->lotes->count() > 1;
        $adjudicados = $r->lotes->where('estado', Lote::ESTADO_ADJUDICADO);
        $nuevo = $r->republicaciones->first(fn (Remate $n) => in_array($n->estado, [Remate::ESTADO_PUBLICADO, Remate::ESTADO_EN_CURSO], true));

        return [
            'id' => $r->slug,
            'delta' => $objetivo ? max(0, $objetivo->getTimestamp() - $ahora->getTimestamp()) : null,
            'puja' => $lote->precio_actual,
            'pujas' => $lote->total_pujas,
            'folio' => $r->folio,
            'direccion' => $varios ? $r->titulo : ($lote->direccion ?: $r->titulo),
            'comuna' => (string) $lote->comuna,
            'region' => (string) $lote->region,
            'tipo' => $varios ? __('catalogo.tarjeta.lotes', ['n' => $r->lotes->count()]) : (string) $lote->tipo_propiedad,
            'sup' => (float) $lote->superficie_util,
            'dorm' => (int) $lote->dormitorios,
            'banos' => (int) $lote->banos,
            'estac' => (int) $lote->estacionamientos,
            'bodega' => (bool) $lote->bodega,
            'ocupacion' => (string) $lote->ocupacion,
            'visita' => $visita ? Formato::fecha($visita->inicia_en, $ahora) : '',
            'martillero' => $r->martillero?->name ?? 'Colliers',
            'precio' => (int) $r->lotes->sum('precio_base'),
            'garantia' => $r->montoGarantia(),
            'limite' => $vista === Remate::VISTA_PROXIMO && $r->cierre_garantias_en
                ? ($ahora->greaterThanOrEqualTo($r->cierre_garantias_en) ? __('catalogo.tarjeta.garantias_vencido') : __('catalogo.tarjeta.garantias_hasta', ['fecha' => CarbonImmutable::instance($r->cierre_garantias_en)->setTimezone(Formato::ZONA)->format('d-m, H:i')]))
                : ($vista === Remate::VISTA_EN_VIVO ? __('catalogo.tarjeta.garantias_vencido') : ''),
            'estado' => $estado,
            'fecha' => $estado === 'Cerrado' ? Formato::dia($r->cierraEn() ?? $r->abreEn()) : Formato::fecha($r->abreEn(), $ahora),
            'resultado' => $estado === 'Cerrado' ? ($adjudicados->isNotEmpty()
                ? __('catalogo.tarjeta.adjudicado_en', ['monto' => Formato::clp((int) $adjudicados->sum('precio_actual'))]) : __('catalogo.tarjeta.no_adjudicado')) : null,
            'nuevoRemate' => $nuevo ? Formato::dia($nuevo->abreEn()) : null,
            'nuevoHref' => $nuevo ? route('remates.show', $nuevo->slug) : null,
            'foto' => $lote->imagenes->first()?->url() ?? asset('img/demo/prop-hero.jpg'),
            'bases' => self::urlBases($r),
        ];
    }

    /** Hero del listado y foto del Login: el próximo remate más cercano (o el en vivo si no hay próximos). */
    public static function destacado(?CarbonImmutable $ahora = null): ?array
    {
        $ahora ??= CarbonImmutable::now('UTC');
        $remates = self::remates();
        $elegido = $remates->filter(fn (Remate $r) => $r->estadoVisible($ahora) === Remate::VISTA_PROXIMO)->sortBy(fn (Remate $r) => $r->abreEn()?->getTimestamp())->first()
            ?? $remates->filter(fn (Remate $r) => $r->estadoVisible($ahora) === Remate::VISTA_EN_VIVO)->first();
        if ($elegido === null) {
            return null;
        }
        $tarjeta = self::tarjeta($elegido, $ahora);

        return $tarjeta + [
            'fechaCorta' => Formato::dia($elegido->abreEn()),
            'limiteLargo' => $elegido->cierre_garantias_en ? Formato::fecha($elegido->cierre_garantias_en, $ahora) : null,
            'enVivo' => $elegido->estadoVisible($ahora) === Remate::VISTA_EN_VIVO,
        ];
    }

    /** Ficha de detalle (forma de DetalleDemo::para()), más lo que necesita el tiempo real y las acciones. */
    public static function detalle(Remate $r, ?User $user, ?CarbonImmutable $ahora = null): array
    {
        $ahora ??= CarbonImmutable::now('UTC');
        $r->loadMissing(['lotes.imagenes', 'lotes.visitas', 'martillero', 'documentos']);
        $vista = $r->estadoVisible($ahora);
        $lote = self::loteVigente($r);
        $a = $lote->atributos ?? [];
        $enVivo = $vista === Remate::VISTA_EN_VIVO;
        $m2 = fn ($n) => $n === null ? null : __('catalogo.medidas.m2', ['n' => Formato::numero($n, 2)]);
        $garantiaAprobada = Habilitacion::conGarantiaAprobada($user, $r);
        $fotos = $lote->imagenes->map->url()->values();

        $ficha = array_filter([
            __('catalogo.ficha.tipo') => self::etiquetaTipo($lote->tipo_propiedad),
            __('catalogo.ficha.superficie_util') => $m2($lote->superficie_util),
            __('catalogo.ficha.superficie_terraza') => $m2($lote->superficie_terraza),
            __('catalogo.ficha.superficie_terreno') => $m2($lote->superficie_terreno),
            __('catalogo.ficha.dormitorios_banos') => $lote->dormitorios !== null ? __('catalogo.medidas.dorm_banos', ['dorm' => $lote->dormitorios, 'banos' => $lote->banos ?? 0]) : null,
            __('catalogo.ficha.estacionamientos') => $lote->estacionamientos ? (string) $lote->estacionamientos : null,
            __('catalogo.ficha.bodega') => $lote->bodega ? __('catalogo.medidas.si') : null,
            __('catalogo.ficha.anio_construccion') => $a['anio_construccion'] ?? null,
            __('catalogo.ficha.ocupacion') => self::etiquetaOcupacion($lote->ocupacion),
            __('catalogo.ficha.orientacion') => $a['orientacion'] ?? null,
            __('catalogo.ficha.piso') => $a['piso'] ?? null,
            __('catalogo.ficha.gastos_comunes') => isset($a['gastos_comunes']) && is_numeric($a['gastos_comunes']) ? __('catalogo.medidas.mensuales', ['monto' => Formato::clp((int) $a['gastos_comunes'])]) : ($a['gastos_comunes'] ?? null),
            __('catalogo.ficha.entrega') => $a['entrega'] ?? null,
        ], fn ($v) => filled($v));

        $adicional = array_filter([
            __('catalogo.adicional.estado_ocupacion') => self::etiquetaOcupacion($lote->ocupacion),
            __('catalogo.adicional.uso') => $a['uso'] ?? null,
            __('catalogo.adicional.rol_avaluo') => $a['rol_avaluo'] ?? null,
            __('catalogo.adicional.rol_estacionamiento') => $a['rol_estacionamiento'] ?? null,
            __('catalogo.adicional.rol_bodega') => $a['rol_bodega'] ?? null,
            __('catalogo.adicional.contribuciones') => $a['contribuciones'] ?? null,
            __('catalogo.adicional.martillero') => $r->martillero?->name,
            __('catalogo.adicional.plazo_saldo') => $a['plazo_saldo'] ?? null,
        ], fn ($v) => filled($v));

        $mandante = array_filter([
            __('catalogo.mandante.mandante') => $a['mandante'] ?? null,
            __('catalogo.mandante.tipo_venta') => $a['tipo_venta'] ?? null,
            __('catalogo.mandante.ejecutivo') => $a['ejecutivo'] ?? null,
        ], fn ($v) => filled($v));

        $recientes = collect(EstadoRemate::construir($r, $ahora)['lotes'])->firstWhere('id', $lote->id)['pujas'] ?? [];

        return [
            'id' => $r->slug,
            'enVivo' => $enVivo,
            'vista' => $vista,
            'folio' => $r->folio,
            'titulo' => $r->titulo,
            'direccion' => $lote->direccion ?: $r->titulo,
            'meta' => collect([trim(collect([$lote->comuna, $lote->region])->filter()->join(', ')), self::etiquetaTipo($lote->tipo_propiedad),
                $lote->superficie_util ? __('catalogo.medidas.m2_utiles', ['n' => Formato::numero($lote->superficie_util, 2)]) : null,
                $lote->dormitorios !== null ? __('catalogo.medidas.dorm_banos_corto', ['dorm' => $lote->dormitorios, 'banos' => $lote->banos ?? 0]) : null, self::etiquetaOcupacion($lote->ocupacion)])->filter()->join(' · '),
            'base' => (int) $r->lotes->sum('precio_base'),
            'garantia' => $r->montoGarantia(),
            'incremento' => $r->incrementoMinimo(),
            'fecha' => Formato::fecha($r->abreEn(), $ahora),
            'limite' => $r->cierre_garantias_en ? Formato::fecha($r->cierre_garantias_en, $ahora) : null,
            'garantiasAbiertas' => $vista === Remate::VISTA_PROXIMO && ($r->cierre_garantias_en === null || $ahora->lessThan($r->cierre_garantias_en)),
            'martillero' => $r->martillero?->name ?? 'Colliers',
            'video' => $r->youtube_video_id,
            'fotoPrincipal' => $fotos->first() ?? asset('img/demo/prop-hero.jpg'),
            'galeria' => $fotos->slice($enVivo ? 0 : 1, 4)->values()->all(),
            'totalFotos' => $fotos->count(),
            'descripcion' => $lote->descripcion ?: $r->descripcion,
            'ficha' => $ficha,
            'adicional' => $adicional,
            'mandante' => $mandante,
            'mapa' => $lote->latitud !== null && $lote->longitud !== null ? [
                'lat' => (float) $lote->latitud, 'lng' => (float) $lote->longitud, 'etiqueta' => $lote->direccion,
                'texto' => collect([collect([$lote->direccion, $lote->comuna])->filter()->join(', '), $a['referencia_mapa'] ?? null])->filter()->join(' · '),
            ] : null,
            'visitas' => $lote->visitas->filter(fn (LoteVisita $v) => $v->termina_en->greaterThan($ahora))->map(fn (LoteVisita $v) => [
                Formato::fechaLarga($v->inicia_en) === '' ? '' : preg_replace('/ de \d{4}$/', '', Formato::fechaLarga($v->inicia_en)),
                $v->inicia_en->setTimezone(Formato::ZONA)->format('H:i') . ' – ' . $v->termina_en->setTimezone(Formato::ZONA)->format('H:i'),
            ])->values()->all(),
            'documentos' => $r->documentos->filter(fn (Documento $d) => $d->lote_id === null || $d->lote_id === $lote->id)->map(fn (Documento $d) => [
                $d->titulo,
                strtoupper(pathinfo($d->nombre_original, PATHINFO_EXTENSION)) . ' · ' . Formato::numero(max(1, (int) round(($d->tamano_bytes ?? 0) / 1024))) . ' KB',
                ($d->publico || $garantiaAprobada) ? route('remates.documento', [$r->slug, $d->id]) : null,
            ])->values()->all(),
            'garantiaCondicion' => (string) Configuracion::valor('texto_condiciones_garantia'),
            'recomendados' => self::recomendados($r, $ahora),
            'bases' => self::urlBases($r),
            'canal' => Configuracion::valor('enlace_canal_youtube') ?: null,
            'lotes' => $r->lotes->count() > 1 ? $r->lotes->map(fn (Lote $l) => [
                'orden' => $l->orden, 'direccion' => $l->direccion ?: $l->titulo, 'base' => $l->precio_base,
                'horario' => Formato::fecha($l->abre_en, $ahora), 'estado' => $l->estado, 'actual' => $l->id === $lote->id,
            ])->all() : [],
            'resultado' => in_array($vista, [Remate::VISTA_ADJUDICADO, Remate::VISTA_CERRADO], true)
                ? ($r->lotes->where('estado', Lote::ESTADO_ADJUDICADO)->isNotEmpty() ? __('catalogo.tarjeta.adjudicado_en', ['monto' => Formato::clp((int) $r->lotes->where('estado', Lote::ESTADO_ADJUDICADO)->sum('precio_actual'))]) : __('catalogo.tarjeta.cerrado_sin_posturas'))
                : null,
            // Tiempo real: el detalle en vivo SOLO lee el JSON estático y el reloj sin framework (no ejecuta PHP con espectadores).
            'tiempoReal' => [
                'loteId' => $lote->id,
                // Próximo: al llegar la hora, la página se recarga sola y muestra el remate en vivo (21/09).
                'proximo' => $vista === Remate::VISTA_PROXIMO,
                'objetivoMs' => (int) (($enVivo ? $lote->cierra_en : $r->abreEn())?->format('Uv') ?? 0),
                'servidorMs' => (int) $ahora->format('Uv'),
                'estadoJson' => asset('tiempo-real/' . $r->slug . '.json'),
                'hora' => asset('hora.php'),
                'pujas' => array_values($recientes),
                'precioActual' => $lote->precio_actual,
                'totalPujas' => $lote->total_pujas,
                'uf' => Sitio::uf(),
                // Bloque J: al cerrar, el cliente muestra el cierre al segundo y avisa una sola vez al servidor.
                'margenMs' => (int) Configuracion::valor('margen_liquidacion_segundos') * 1000,
                'avisoCierre' => EstadoRemate::avisoCierre(),
                'estadoUrl' => route('tiempo-real.estado', $r->slug),
            ],
        ];
    }

    private static function recomendados(Remate $actual, CarbonImmutable $ahora): array
    {
        return self::remates()->filter(fn (Remate $r) => $r->id !== $actual->id && in_array($r->estadoVisible($ahora), [Remate::VISTA_EN_VIVO, Remate::VISTA_PROXIMO], true))
            ->sortBy(fn (Remate $r) => $r->abreEn()?->getTimestamp())->take(3)
            ->map(function (Remate $r) use ($ahora) {
                $t = self::tarjeta($r, $ahora);

                return [
                    'id' => $r->slug, 'direccion' => $t['direccion'], 'ubicacion' => trim($t['comuna'] . ', ' . $t['region'], ', '),
                    'tipoSup' => collect([self::etiquetaTipo($t['tipo']), $t['sup'] ? __('catalogo.medidas.m2', ['n' => Formato::numero($t['sup'], 2)]) : null, $t['dorm'] ? __('catalogo.medidas.dorm_banos_corto', ['dorm' => $t['dorm'], 'banos' => $t['banos']]) : null])->filter()->join(' · '),
                    'precio' => $t['precio'], 'garantia' => $t['garantia'], 'fecha' => $t['fecha'], 'estado' => mb_strtoupper(self::etiquetaEstado($t['estado'])),
                    'vivo' => $t['estado'] === 'En vivo', 'foto' => $t['foto'],
                ];
            })->values()->all();
    }

    /** Tipo de propiedad para mostrar. El valor guardado (y filtrado) sigue en español; sin etiqueta, va tal cual. */
    public static function etiquetaTipo(?string $valor): string
    {
        return self::etiqueta('tipos', $valor);
    }

    /** Ocupación para mostrar (el valor guardado sigue en español). */
    public static function etiquetaOcupacion(?string $valor): string
    {
        return self::etiqueta('ocupaciones', $valor);
    }

    /** Estado de la tarjeta para mostrar: «En vivo», «Próximo» o «Cerrado» son códigos que la vista y el JS comparan. */
    public static function etiquetaEstado(?string $valor): string
    {
        return self::etiqueta('estados', $valor);
    }

    private static function etiqueta(string $grupo, ?string $valor): string
    {
        $valor = (string) $valor;
        if ($valor === '') {
            return '';
        }
        $clave = 'catalogo.' . $grupo . '.' . $valor;
        $etiqueta = __($clave);

        return is_string($etiqueta) && $etiqueta !== $clave ? $etiqueta : $valor;
    }

    /** El lote que se muestra: el que se está rematando o el próximo; si ya terminaron todos, el primero. */
    public static function loteVigente(Remate $r): ?Lote
    {
        return $r->lotes->first(fn (Lote $l) => ! in_array($l->estado, Lote::ESTADOS_TERMINALES, true)) ?? $r->lotes->first();
    }

    /** «Bases y condiciones»: el primer documento público con «bases» en el título; si no, el enlace general de Configuración. */
    private static function urlBases(Remate $r): ?string
    {
        $documento = $r->relationLoaded('documentos') ? $r->documentos->first(fn (Documento $d) => $d->publico && str_contains(mb_strtolower($d->titulo), 'bases'))
            : Documento::where('remate_id', $r->id)->where('publico', true)->where('titulo', 'like', '%ases%')->orderBy('orden')->first();

        return $documento ? route('remates.documento', [$r->slug, $documento->id]) : (Configuracion::valor('enlace_bases_generales') ?: null);
    }
}
