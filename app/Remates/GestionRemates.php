<?php

namespace App\Remates;

use App\Events\RematePublicado;
use App\Models\Configuracion;
use App\Models\Documento;
use App\Models\Lote;
use App\Models\Remate;
use App\Subastas\Difusion\Emisor;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reglas de gestión de remates desde el panel (Bloque I).
 *
 * Supuestos vigentes (máquina de estados en revisión con el cliente; cambiarlos no requiere migrar):
 * - borrador → publicado → (en vivo por horario) → finalizado; borrador o publicado sin comenzar → cancelado.
 * - Horario, precios, incremento y porcentaje de garantía se editan solo antes de que abra el primer lote.
 * - Un remate que no se concreta NO se reabre: «Crear remate nuevo» copia los lotes no adjudicados a un borrador nuevo.
 */
class GestionRemates
{
    public function __construct(private readonly Emisor $emisor) {}

    /** Remate en borrador con su primer lote (el formulario de creación del diseño junta ambos). */
    public function crear(array $datosRemate, array $datosLote): Remate
    {
        return DB::transaction(function () use ($datosRemate, $datosLote) {
            $titulo = $datosRemate['titulo'] ?? $datosLote['direccion'] ?? $datosLote['titulo'];
            $remate = Remate::create($datosRemate + [
                'folio' => $this->siguienteFolio(),
                'slug' => $this->slugLibre($titulo),
                'titulo' => $titulo,
                'estado' => Remate::ESTADO_BORRADOR,
            ]);
            $remate->lotes()->create($datosLote + ['orden' => 1, 'titulo' => $datosLote['direccion'] ?? $titulo]);
            $remate->programarLotes();

            return $remate;
        });
    }

    /** Guarda los datos del remate. Las condiciones solo cambian antes de que abra el primer lote. */
    public function actualizar(Remate $remate, array $datos): void
    {
        $condiciones = ['inicio_en', 'cierre_garantias_en', 'duracion_lote_segundos', 'pausa_entre_lotes_segundos', 'incremento_minimo', 'porcentaje_garantia'];
        if (! $remate->condicionesEditables()) {
            foreach ($condiciones as $campo) {
                if (array_key_exists($campo, $datos) && $this->distinto($remate->{$campo}, $datos[$campo])) {
                    throw new DomainException('El remate ya comenzó: el horario y las condiciones económicas no se pueden cambiar.');
                }
                unset($datos[$campo]);
            }
        }

        DB::transaction(function () use ($remate, $datos) {
            $remate->update($datos);
            if ($remate->condicionesEditables()) {
                $remate->programarLotes();
            }
        });
        $this->publicarEstadoSiCorresponde($remate);
    }

    public function guardarLote(Remate $remate, ?Lote $lote, array $datos): Lote
    {
        if ($lote !== null && $lote->remate_id !== $remate->id) {
            throw new DomainException('El lote no pertenece a este remate.');
        }
        if (! $remate->condicionesEditables()) {
            $protegidos = ['precio_base', 'duracion_segundos', 'orden'];
            if ($lote === null) {
                throw new DomainException('El remate ya comenzó: no se pueden agregar lotes.');
            }
            foreach ($protegidos as $campo) {
                if (array_key_exists($campo, $datos) && $this->distinto($lote->{$campo}, $datos[$campo])) {
                    throw new DomainException('El remate ya comenzó: el precio base, la duración y el orden del lote no se pueden cambiar.');
                }
                unset($datos[$campo]);
            }
        }

        $lote = DB::transaction(function () use ($remate, $lote, $datos) {
            $datos['titulo'] = $datos['direccion'] ?? $lote?->titulo ?? 'Lote';
            if ($lote === null) {
                $lote = $remate->lotes()->create($datos + ['orden' => (int) $remate->lotes()->max('orden') + 1]);
            } else {
                $lote->update($datos);
            }
            if ($remate->condicionesEditables()) {
                $remate->programarLotes();
            }

            return $lote;
        });
        $this->publicarEstadoSiCorresponde($remate);

        return $lote;
    }

    /** Sube o baja un lote un puesto y reprograma los horarios. Solo antes de que abra el primer lote. */
    public function moverLote(Remate $remate, Lote $lote, string $direccion): void
    {
        if ($lote->remate_id !== $remate->id) {
            throw new DomainException('El lote no pertenece a este remate.');
        }
        if (! $remate->condicionesEditables()) {
            throw new DomainException('El remate ya comenzó: el orden de los lotes no se puede cambiar.');
        }

        DB::transaction(function () use ($remate, $lote, $direccion) {
            // Se renumera 1..N para que un orden con huecos o repetido no deje el cambio a medias.
            $lotes = $remate->lotes()->orderBy('id')->lockForUpdate()->get()->sortBy([['orden', 'asc'], ['id', 'asc']])->values();
            $posicion = $lotes->search(fn (Lote $l) => $l->id === $lote->id);
            $destino = $direccion === 'subir' ? $posicion - 1 : $posicion + 1;
            if ($destino < 0 || $destino >= $lotes->count()) {
                return;
            }
            $ids = $lotes->pluck('id')->all();
            [$ids[$posicion], $ids[$destino]] = [$ids[$destino], $ids[$posicion]];
            foreach ($ids as $n => $id) {
                Lote::whereKey($id)->update(['orden' => $n + 1]);
            }
            $remate->programarLotes();
        });
        $this->publicarEstadoSiCorresponde($remate);
    }

    /** Lo que falta para publicar. Vacío = se puede publicar. @return list<string> */
    public function faltantesParaPublicar(Remate $remate): array
    {
        $faltan = [];
        $ahora = CarbonImmutable::now('UTC');
        $lotes = $remate->lotes()->get();

        if ($remate->estado !== Remate::ESTADO_BORRADOR) {
            $faltan[] = 'Solo se publica un remate en borrador.';
        }
        if ($lotes->isEmpty()) {
            $faltan[] = 'Agrega al menos un lote.';
        }
        foreach ($lotes as $lote) {
            foreach (['direccion' => 'dirección', 'comuna' => 'comuna', 'region' => 'región', 'tipo_propiedad' => 'tipo de propiedad'] as $campo => $nombre) {
                if (blank($lote->{$campo})) {
                    $faltan[] = "Lote {$lote->orden}: falta la {$nombre}.";
                }
            }
            if ($lote->precio_base < 1) {
                $faltan[] = "Lote {$lote->orden}: falta el precio base.";
            }
        }
        if ($remate->inicio_en === null) {
            $faltan[] = 'Falta la fecha y hora de inicio.';
        } elseif ($remate->inicio_en->lessThanOrEqualTo($ahora)) {
            $faltan[] = 'El inicio debe ser una fecha futura.';
        }
        if ($remate->cierre_garantias_en !== null && $remate->inicio_en !== null && $remate->cierre_garantias_en->greaterThan($remate->inicio_en)) {
            $faltan[] = 'El cierre de garantías debe ser antes del inicio.';
        }
        if ($remate->martillero_id === null) {
            $faltan[] = 'Asigna un martillero.';
        }

        return $faltan;
    }

    public function publicar(Remate $remate): void
    {
        $faltan = $this->faltantesParaPublicar($remate);
        if ($faltan !== []) {
            throw new DomainException(implode(' ', $faltan));
        }

        DB::transaction(function () use ($remate) {
            // Plazo de garantías por defecto: N horas antes del inicio (configurable, Bloque V).
            $remate->cierre_garantias_en ??= $remate->inicio_en->subHours((int) Configuracion::valor('garantias_cierre_horas_antes'));
            $remate->estado = Remate::ESTADO_PUBLICADO;
            $remate->publicado_en = CarbonImmutable::now('UTC');
            $remate->save();
            $remate->programarLotes();
        });
        $this->publicarEstadoSiCorresponde($remate);
        if (! $remate->es_demostracion) {
            RematePublicado::dispatch($remate);
        }
    }

    /** Borrador o publicado que todavía no comienza. Uno que ya comenzó se cierra lote por lote (cierre anticipado). */
    public function cancelar(Remate $remate, string $motivo): void
    {
        if (! in_array($remate->estado, [Remate::ESTADO_BORRADOR, Remate::ESTADO_PUBLICADO], true) || $remate->yaComenzo()) {
            throw new DomainException('Solo se cancela un remate que no ha comenzado. Para uno en vivo, cierra el lote anticipadamente.');
        }

        $remate->update([
            'estado' => Remate::ESTADO_CANCELADO,
            'cancelado_en' => CarbonImmutable::now('UTC'),
            'motivo_cancelacion' => mb_substr(trim($motivo), 0, 500),
        ]);
        $this->publicarEstadoSiCorresponde($remate);
    }

    /**
     * «Crear remate nuevo»: si el remate no se concretó (o se canceló), sus lotes no adjudicados pasan a un borrador NUEVO,
     * con fotos, visitas futuras y documentos. El original no se toca.
     */
    public function republicar(Remate $remate): Remate
    {
        if (! in_array($remate->estadoVisible(), [Remate::VISTA_CERRADO, Remate::VISTA_ADJUDICADO, Remate::VISTA_CANCELADO], true)) {
            throw new DomainException('Solo se crea un remate nuevo a partir de uno cerrado o cancelado.');
        }
        $lotes = $remate->lotes()->with(['imagenes', 'visitas'])->get()
            ->reject(fn (Lote $l) => in_array($l->estado, [Lote::ESTADO_ADJUDICADO, Lote::ESTADO_CERRADO], true));
        if ($lotes->isEmpty()) {
            throw new DomainException('Todos los lotes de este remate se adjudicaron: no hay nada que volver a rematar.');
        }

        return DB::transaction(function () use ($remate, $lotes) {
            $nuevo = Remate::create(collect($remate->only([
                'titulo', 'descripcion', 'duracion_lote_segundos', 'pausa_entre_lotes_segundos', 'incremento_minimo',
                'porcentaje_garantia', 'martillero_id',
            ]))->all() + [
                'folio' => $this->siguienteFolio(),
                'slug' => $this->slugLibre($remate->titulo),
                'estado' => Remate::ESTADO_BORRADOR,
                'remate_origen_id' => $remate->id,
            ]);

            $mapa = [];
            foreach ($lotes->values() as $i => $origen) {
                $copia = $nuevo->lotes()->create(collect($origen->getAttributes())->only((new Lote)->getFillable())
                    ->except(['remate_id', 'estado', 'abre_en', 'cierra_en', 'lote_origen_id', 'orden', 'atributos'])->all()
                    + ['orden' => $i + 1, 'lote_origen_id' => $origen->id, 'atributos' => $origen->atributos]);
                $mapa[$origen->id] = $copia->id;
                foreach ($origen->imagenes as $imagen) {
                    $copia->imagenes()->create($imagen->only(['ruta', 'orden', 'texto_alternativo', 'credito']));
                }
            }
            foreach (Documento::where('remate_id', $remate->id)->get() as $documento) {
                if ($documento->lote_id !== null && ! isset($mapa[$documento->lote_id])) {
                    continue;
                }
                Documento::create($documento->only(['titulo', 'ruta', 'nombre_original', 'mime', 'tamano_bytes', 'orden', 'publico'])
                    + ['remate_id' => $nuevo->id, 'lote_id' => $documento->lote_id ? $mapa[$documento->lote_id] : null]);
            }

            return $nuevo;
        });
    }

    public function siguienteFolio(): string
    {
        $anio = CarbonImmutable::now('America/Santiago')->year;
        $ultimo = Remate::where('folio', 'like', "R-{$anio}-%")->pluck('folio')
            ->map(fn (string $f) => (int) substr($f, strlen("R-{$anio}-")))->max() ?? 0;

        return sprintf('R-%d-%03d', $anio, $ultimo + 1);
    }

    private function slugLibre(string $titulo): string
    {
        $base = Str::limit(Str::slug($titulo), 80, '') ?: 'remate';
        $slug = $base;
        for ($n = 2; Remate::where('slug', $slug)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    private function distinto(mixed $actual, mixed $nuevo): bool
    {
        if ($actual instanceof CarbonImmutable) {
            return $nuevo === null || ! $actual->equalTo(CarbonImmutable::parse($nuevo, 'UTC'));
        }

        if (is_numeric($actual) && is_numeric($nuevo)) {
            return (float) $actual !== (float) $nuevo;
        }

        return (string) $actual !== (string) $nuevo;
    }

    /** Un remate publicado mantiene su JSON público al día (horario, cancelación). Un fallo no revierte el cambio. */
    private function publicarEstadoSiCorresponde(Remate $remate): void
    {
        if ($remate->estado === Remate::ESTADO_BORRADOR) {
            return;
        }
        try {
            $this->emisor->publicarEstado($remate);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
