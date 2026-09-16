<?php

namespace Database\Seeders;

use App\Models\Adjudicacion;
use App\Models\Configuracion;
use App\Models\Empresa;
use App\Models\Garantia;
use App\Models\Lote;
use App\Models\Postor;
use App\Models\Puja;
use App\Models\Remate;
use App\Models\User;
use App\Support\Rut;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Datos de desarrollo con la forma real del modelo, tomados del prototipo (App\Demo\*).
 * Las fechas son relativas a «ahora», igual que el prototipo: siempre hay un remate en vivo, próximos y cerrados.
 * Diferencias deliberadas con el prototipo, porque manda el acta:
 * - Garantía = 10 % de la base (el prototipo muestra ~4 %).
 * - Incremento mínimo = valor global de configuración, CLP 100.000 (el prototipo usa 1.000.000 y 1.500.000).
 * Solo para local y pruebas: crea usuarios con una clave FIJA que está en este repositorio público, así que se niega
 * a correr en cualquier otro entorno (también en el sandbox). Para el sandbox: `colliers:remate-demo`.
 * No se ejecuta en el deploy (no hay db:seed).
 */
class DesarrolloSeeder extends Seeder
{
    private CarbonImmutable $ahora;

    /** @var array<string, User> */
    private array $usuarios = [];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('DesarrolloSeeder solo se ejecuta en local (usa claves fijas y públicas).');
        }

        $this->ahora = CarbonImmutable::now('UTC')->startOfMinute();

        Configuracion::sembrarDefectos();
        $this->personal();
        $this->postores();
        $remates = $this->remates();
        $this->garantias($remates);
        $this->pujasEnVivo($remates['apoquindo']);
        $this->adjudicado($remates['sanmartin']);
        $this->desierto($remates['alemania']);
    }

    private function personal(): void
    {
        $this->usuarios['admin'] = User::create([
            'name' => 'Carolina Méndez', 'email' => 'admin@colliers.test', 'password' => 'colliers-local-2026',
            'rol' => User::ROL_ADMIN, 'estado' => User::ESTADO_ACTIVO,
        ]);
        foreach (['M. Ossandón' => 'mossandon', 'C. Vergara' => 'cvergara', 'R. Fuentes' => 'rfuentes'] as $nombre => $usuario) {
            $this->usuarios[$nombre] = User::create([
                'name' => $nombre, 'email' => "{$usuario}@colliers.test", 'password' => 'colliers-local-2026',
                'rol' => User::ROL_MARTILLERO, 'estado' => User::ESTADO_ACTIVO,
            ]);
        }
    }

    private function postores(): void
    {
        // [clave, nombres, apellidos, cuerpo del RUT, correo, teléfono, estado, empresa?]
        $filas = [
            ['mpgonzalez', 'María Paz', 'González Soto', '15482331', 'mpgonzalez@correo.test', '+56 9 8123 4455', Postor::ESTADO_APROBADO, null],
            ['andes', 'Andrés', 'Molina Rojas', '14220318', 'contacto@andes.test', '+56 2 2233 4455', Postor::ESTADO_APROBADO, ['76543210', 'Inversiones Andes SpA', 'Inversiones inmobiliarias']],
            ['cortiz', 'Camila', 'Ortiz Vera', '17998221', 'camila.ortiz@correo.test', '+56 9 7744 1122', Postor::ESTADO_APROBADO, null],
            ['rsalas', 'Rodrigo', 'Salas Pinto', '13220884', 'rsalas@correo.test', '+56 9 6612 8890', Postor::ESTADO_EN_REVISION, null],
            ['jtapia', 'Jorge', 'Tapia Ruiz', '9884112', 'jtapia@correo.test', '+56 9 5533 7788', Postor::ESTADO_EN_REVISION, null],
            ['lcarcamo', 'Luis', 'Cárcamo Bravo', '12101559', 'lcarcamo@correo.test', '+56 9 4411 2277', Postor::ESTADO_APROBADO, null],
            ['pfuentes', 'Patricia', 'Fuentes Lira', '16774203', 'pfuentes@correo.test', '+56 9 3322 9911', Postor::ESTADO_APROBADO, null],
            ['lonquen', 'Verónica', 'Lagos Muñoz', '11530772', 'finanzas@lonquen.test', '+56 2 2988 1200', Postor::ESTADO_APROBADO, ['77201330', 'Constructora Lonquén Ltda.', 'Construcción']],
        ];

        foreach ($filas as [$clave, $nombres, $apellidos, $cuerpo, $correo, $telefono, $estado, $empresa]) {
            $user = User::create([
                'name' => $empresa ? $empresa[1] : trim($nombres . ' ' . strtok($apellidos, ' ')), 'email' => $correo,
                'password' => 'colliers-local-2026', 'rol' => User::ROL_POSTOR, 'estado' => User::ESTADO_ACTIVO,
            ]);
            // No es asignable en masa: se fuerza (los postores de ejemplo ya confirmaron su correo).
            $user->forceFill(['email_verified_at' => $this->ahora->subDays(20)])->save();
            $empresaModelo = $empresa ? Empresa::create([
                'rut' => $empresa[0] . Rut::digitoVerificador($empresa[0]), 'razon_social' => $empresa[1], 'giro' => $empresa[2],
            ]) : null;

            $postor = Postor::create([
                'user_id' => $user->id,
                'tipo' => $empresa ? Postor::TIPO_JURIDICA : Postor::TIPO_NATURAL,
                'nombres' => $nombres, 'apellidos' => $apellidos,
                'rut' => $cuerpo . Rut::digitoVerificador($cuerpo),
                'nacionalidad' => 'Chilena', 'telefono' => $telefono,
                'direccion' => 'Av. Providencia 1234', 'comuna' => 'Providencia', 'region' => 'Metropolitana',
                'empresa_id' => $empresaModelo?->id, 'calidad' => $empresa ? 'Representante legal' : null,
                'origen' => 'Sitio de Colliers', 'acepta_terminos_en' => $this->ahora->subDays(20),
            ]);
            $postor->estado = $estado;
            if ($estado === Postor::ESTADO_APROBADO) {
                $postor->revisado_por_id = $this->usuarios['admin']->id;
                $postor->revisado_en = $this->ahora->subDays(18);
            }
            $postor->save();

            $this->usuarios[$clave] = $user;
        }
    }

    /** @return array<string, Remate> */
    private function remates(): array
    {
        $santiago = fn (CarbonImmutable $utc) => $utc->setTimezone('America/Santiago');
        $remates = [];

        foreach ($this->catalogo() as $id => $d) {
            // Próximos: inicio = ahora + delta. En vivo: cierra en `delta`. Cerrados: días atrás.
            $inicio = match ($d['estado']) {
                'en_curso' => $this->ahora->addSeconds($d['delta'] - $d['duracion']),
                'finalizado' => $santiago($this->ahora)->subDays($d['diasAtras'])->setTime(12, 0)->utc(),
                default => $this->ahora->addSeconds($d['delta']),
            };

            $remate = Remate::create([
                'folio' => $d['folio'], 'slug' => $id, 'titulo' => $d['direccion'],
                'estado' => $d['estado'], 'inicio_en' => $inicio,
                'cierre_garantias_en' => $santiago($inicio)->subDays(2)->setTime(18, 0)->utc(),
                'duracion_lote_segundos' => $d['duracion'],
                'youtube_video_id' => $d['video'] ?? null,
                'martillero_id' => $this->usuarios[$d['martillero']]->id,
                'publicado_en' => $d['estado'] === 'borrador' ? null : $inicio->subDays(21),
                'finalizado_en' => $d['estado'] === 'finalizado' ? $inicio->addSeconds($d['duracion']) : null,
            ]);

            $lote = Lote::create([
                'remate_id' => $remate->id, 'orden' => 1,
                'estado' => match ($d['estado']) {
                    'en_curso' => Lote::ESTADO_ABIERTO,
                    'finalizado' => Lote::ESTADO_PROGRAMADO, // lo cierran adjudicado() / desierto()
                    default => Lote::ESTADO_PROGRAMADO,
                },
                'titulo' => $d['direccion'], 'tipo_propiedad' => $d['tipo'], 'direccion' => $d['direccion'],
                'comuna' => $d['comuna'], 'region' => $d['region'],
                'latitud' => $d['lat'] ?? null, 'longitud' => $d['lng'] ?? null,
                'superficie_util' => $d['sup'], 'superficie_terraza' => $d['terraza'] ?? null,
                'dormitorios' => $d['dorm'], 'banos' => $d['banos'], 'estacionamientos' => $d['estac'],
                'bodega' => $d['bodega'], 'ocupacion' => $d['ocupacion'],
                'descripcion' => $d['descripcion'] ?? null, 'atributos' => $d['atributos'] ?? null,
                'precio_base' => $d['precio'],
            ]);
            $remate->programarLotes();

            foreach (array_merge(["prop-{$id}.jpg"], $d['galeria'] ?? []) as $i => $foto) {
                $lote->imagenes()->create(['ruta' => "img/demo/{$foto}", 'orden' => $i + 1, 'texto_alternativo' => $d['direccion']]);
            }
            foreach ($d['visitas'] ?? [] as [$dias, $hora, $horas]) {
                $inicioVisita = $santiago($inicio)->subDays($dias)->setTime($hora, 0)->utc();
                $lote->visitas()->create(['inicia_en' => $inicioVisita, 'termina_en' => $inicioVisita->addHours($horas)]);
            }

            $remates[$id] = $remate;
        }

        // Republicación de un remate no concretado: remate nuevo cuyo lote apunta al original.
        $origen = $remates['alemania']->lotes()->first();
        $nuevo = Remate::create([
            'folio' => 'R-2026-131', 'slug' => 'alemania-2', 'titulo' => $origen->titulo, 'estado' => Remate::ESTADO_BORRADOR,
            'inicio_en' => $this->ahora->addDays(28), 'martillero_id' => $this->usuarios['R. Fuentes']->id,
        ]);
        $lote = $nuevo->lotes()->create(collect($origen->getAttributes())->only((new Lote)->getFillable())
            ->except(['remate_id', 'estado', 'abre_en', 'cierra_en', 'atributos', 'lote_origen_id'])->all() + ['lote_origen_id' => $origen->id]);
        $nuevo->programarLotes();

        return $remates;
    }

    /** @param array<string, Remate> $remates */
    private function garantias(array $remates): void
    {
        // [postor, remate, estado, medio]
        $filas = [
            ['mpgonzalez', 'apoquindo', Garantia::ESTADO_APROBADA, Garantia::MEDIO_VALE_VISTA],
            ['pfuentes', 'apoquindo', Garantia::ESTADO_APROBADA, Garantia::MEDIO_TRANSFERENCIA],
            ['andes', 'apoquindo', Garantia::ESTADO_APROBADA, Garantia::MEDIO_TRANSFERENCIA],
            ['andes', 'militares', Garantia::ESTADO_EN_REVISION, Garantia::MEDIO_TRANSFERENCIA],
            ['cortiz', 'militares', Garantia::ESTADO_EN_REVISION, Garantia::MEDIO_VALE_VISTA],
            ['lcarcamo', 'elalba', Garantia::ESTADO_RECHAZADA, Garantia::MEDIO_TRANSFERENCIA],
            ['pfuentes', 'elalba', Garantia::ESTADO_APROBADA, Garantia::MEDIO_VALE_VISTA],
            ['lonquen', 'montt', Garantia::ESTADO_PENDIENTE, null],
            ['cortiz', 'sanmartin', Garantia::ESTADO_APROBADA, Garantia::MEDIO_VALE_VISTA],
            ['pfuentes', 'sanmartin', Garantia::ESTADO_APROBADA, Garantia::MEDIO_TRANSFERENCIA],
        ];

        foreach ($filas as [$postor, $remate, $estado, $medio]) {
            $garantia = Garantia::paraRemate($remates[$remate], $this->usuarios[$postor]);
            $garantia->estado = $estado;
            $garantia->medio = $medio;
            if ($estado !== Garantia::ESTADO_PENDIENTE) {
                $garantia->comprobante_ruta = "comprobantes/demo-{$postor}-{$remate}.pdf";
                $garantia->comprobante_nombre = 'comprobante.pdf';
                $garantia->comprobante_subido_en = $this->ahora->subDays(10);
            }
            if (in_array($estado, [Garantia::ESTADO_APROBADA, Garantia::ESTADO_RECHAZADA], true)) {
                $garantia->revisado_por_id = $this->usuarios['admin']->id;
                $garantia->revisado_en = $this->ahora->subDays(8);
            }
            if ($estado === Garantia::ESTADO_RECHAZADA) {
                $garantia->motivo_rechazo = 'Monto insuficiente';
            }
            $garantia->save();
        }
    }

    /** Pujas del prototipo (sala en vivo): [monto, postor, segundos atrás], de la más antigua a la más nueva. */
    private function pujasEnVivo(Remate $remate): void
    {
        $lote = $remate->lotes()->first();
        $pujas = [
            [185000000, 'pfuentes', 402], [186500000, 'andes', 318], [188000000, 'pfuentes', 240],
            [191500000, 'mpgonzalez', 172], [195000000, 'andes', 96], [198500000, 'mpgonzalez', 34],
        ];
        $this->registrarPujas($lote, $pujas);
    }

    private function adjudicado(Remate $remate): void
    {
        $lote = $remate->lotes()->first();
        $cierre = $lote->cierra_en;
        $segundosAntesDelCierre = fn (int $s) => (int) $this->ahora->diffInSeconds($cierre->subSeconds($s), true);
        $ultima = $this->registrarPujas($lote, [
            [72000000, 'pfuentes', $segundosAntesDelCierre(900)],
            [80000000, 'cortiz', $segundosAntesDelCierre(420)],
            [88000000, 'pfuentes', $segundosAntesDelCierre(200)],
        ]);
        // El prototipo muestra «Adjudicado en $88.000.000».
        $this->cerrar($lote, Lote::ESTADO_ADJUDICADO, $cierre);
        Adjudicacion::create([
            'lote_id' => $lote->id, 'user_id' => $ultima->user_id, 'puja_id' => $ultima->id, 'monto' => $ultima->monto,
            'cerrado_en' => $cierre, 'motivo_cierre' => Lote::CIERRE_TIEMPO, 'estado' => Adjudicacion::ESTADO_ADJUDICADO,
        ]);
    }

    private function desierto(Remate $remate): void
    {
        $lote = $remate->lotes()->first();
        $this->cerrar($lote, Lote::ESTADO_DESIERTO, $lote->cierra_en);
    }

    /** @param array<int, array{0:int, 1:string, 2:int}> $pujas */
    private function registrarPujas(Lote $lote, array $pujas): Puja
    {
        $ultima = null;
        foreach ($pujas as [$monto, $postor, $segundosAtras]) {
            $ultima = Puja::create([
                'lote_id' => $lote->id, 'user_id' => $this->usuarios[$postor]->id, 'monto' => $monto,
                'recibida_en' => $this->ahora->subSeconds($segundosAtras), 'ip' => '127.0.0.1', 'user_agent' => 'DesarrolloSeeder',
            ]);
        }
        // Campos del motor: no son asignables en masa, se escriben explícitamente.
        $lote->precio_actual = $ultima->monto;
        $lote->ganador_id = $ultima->user_id;
        $lote->total_pujas = count($pujas);
        $lote->ultima_puja_en = $ultima->recibida_en;
        $lote->save();

        return $ultima;
    }

    private function cerrar(Lote $lote, string $estado, CarbonImmutable $cuando): void
    {
        $lote->estado = $estado;
        $lote->cerrado_en = $cuando;
        $lote->motivo_cierre = Lote::CIERRE_TIEMPO;
        $lote->save();
    }

    /** Catálogo del prototipo (RematesDemo + DetalleDemo). Montos en pesos. */
    private function catalogo(): array
    {
        $fichaMilitares = [
            'anio_construccion' => 2011, 'orientacion' => 'Poniente', 'piso' => '7 de 12', 'gastos_comunes' => 145000,
            'rol_avaluo' => '2871-14', 'rol_estacionamiento' => '2871-96', 'rol_bodega' => '2871-152', 'contribuciones' => 'Al día',
            'uso' => 'Residencial', 'entrega' => '30 días desde la escritura', 'plazo_saldo' => '30 días desde la adjudicación',
            'mandante' => 'Banco Consorcio', 'tipo_venta' => 'Bien recuperado', 'ejecutivo' => 'Paula Riquelme · Colliers Chile',
            'referencia_mapa' => 'a 450 m de Metro Los Dominicos',
        ];
        $fichaApoquindo = [
            'anio_construccion' => 2014, 'orientacion' => 'Nororiente', 'piso' => '18', 'gastos_comunes' => 180000,
            'rol_avaluo' => '1234-56', 'rol_estacionamiento' => '1234-118 / 1234-119', 'rol_bodega' => '1234-206', 'contribuciones' => 'Al día',
            'uso' => 'Residencial', 'entrega' => '30 días desde la escritura', 'plazo_saldo' => '30 días desde la adjudicación',
            'mandante' => 'Banco Consorcio', 'tipo_venta' => 'Bien recuperado', 'ejecutivo' => 'Paula Riquelme · Colliers Chile',
            'referencia_mapa' => 'a 300 m de Metro Manquehue',
        ];
        $rm = 'Región Metropolitana';

        return [
            'apoquindo' => ['estado' => 'en_curso', 'delta' => 2520, 'duracion' => 3600, 'folio' => 'R-2026-114', 'direccion' => 'Av. Apoquindo 4501, Depto. 1802', 'comuna' => 'Las Condes', 'region' => $rm, 'tipo' => 'Departamento', 'sup' => 118, 'terraza' => 14, 'dorm' => 3, 'banos' => 2, 'estac' => 2, 'bodega' => true, 'ocupacion' => 'Desocupada', 'martillero' => 'M. Ossandón', 'precio' => 185000000, 'video' => 'jfKfPfyJRdk', 'lat' => -33.4145, 'lng' => -70.5810, 'galeria' => ['prop-militares.jpg', 'prop-elalba.jpg', 'prop-montt.jpg'], 'visitas' => [[7, 11, 2], [4, 16, 2]], 'atributos' => $fichaApoquindo,
                'descripcion' => 'Departamento en piso 18 con orientación nororiente y vista despejada al parque, en un edificio de 2014 con conserjería 24 horas, gimnasio y sala multiuso. Tres dormitorios, dos baños, cocina equipada y terraza de 14 m². Se entrega desocupado, a media cuadra de Metro Manquehue y del eje Apoquindo, con comercio, colegios y clínicas en el entorno inmediato.'],
            'militares' => ['estado' => 'publicado', 'delta' => 190800, 'duracion' => 1800, 'folio' => 'R-2026-118', 'direccion' => 'Los Militares 5620, Depto. 703', 'comuna' => 'Las Condes', 'region' => $rm, 'tipo' => 'Departamento', 'sup' => 96, 'terraza' => 9, 'dorm' => 3, 'banos' => 2, 'estac' => 1, 'bodega' => true, 'ocupacion' => 'Desocupada', 'martillero' => 'M. Ossandón', 'precio' => 142000000, 'lat' => -33.4093, 'lng' => -70.5772, 'galeria' => ['prop-apoquindo.jpg', 'prop-elalba.jpg', 'prop-montt.jpg', 'prop-renaca.jpg'], 'visitas' => [[7, 11, 2], [4, 16, 2]], 'atributos' => $fichaMilitares,
                'descripcion' => 'Departamento en séptimo piso, orientación poniente, en un edificio de 2011 con conserjería 24 horas, piscina y quincho. Tres dormitorios, dos baños, cocina con logia y terraza de 9 m². Incluye estacionamiento y bodega. Se entrega desocupado, a pasos del eje Los Militares y con acceso directo a Apoquindo.'],
            'elalba' => ['estado' => 'publicado', 'delta' => 352800, 'duracion' => 1800, 'folio' => 'R-2026-119', 'direccion' => 'Camino El Alba 11.850, Casa 6', 'comuna' => 'Las Condes', 'region' => $rm, 'tipo' => 'Casa', 'sup' => 240, 'dorm' => 4, 'banos' => 3, 'estac' => 2, 'bodega' => false, 'ocupacion' => 'Ocupada', 'martillero' => 'C. Vergara', 'precio' => 390000000],
            'montt' => ['estado' => 'publicado', 'delta' => 777600, 'duracion' => 1800, 'folio' => 'R-2026-121', 'direccion' => 'Manuel Montt 1740, Depto. 505', 'comuna' => 'Providencia', 'region' => $rm, 'tipo' => 'Departamento', 'sup' => 74, 'dorm' => 2, 'banos' => 1, 'estac' => 1, 'bodega' => false, 'ocupacion' => 'Desocupada', 'martillero' => 'C. Vergara', 'precio' => 96000000, 'visitas' => [[6, 16, 2]]],
            'pedrovaldivia' => ['estado' => 'publicado', 'delta' => 1382400, 'duracion' => 1800, 'folio' => 'R-2026-122', 'direccion' => 'Pedro de Valdivia 2130, Depto. 1204', 'comuna' => 'Ñuñoa', 'region' => $rm, 'tipo' => 'Departamento', 'sup' => 68, 'dorm' => 2, 'banos' => 1, 'estac' => 0, 'bodega' => true, 'ocupacion' => 'Ocupada', 'martillero' => 'M. Ossandón', 'precio' => 84000000],
            'renaca' => ['estado' => 'publicado', 'delta' => 1987200, 'duracion' => 1800, 'folio' => 'R-2026-124', 'direccion' => 'Av. Concón–Reñaca 250, Depto. 902', 'comuna' => 'Viña del Mar', 'region' => 'Región de Valparaíso', 'tipo' => 'Departamento', 'sup' => 112, 'dorm' => 3, 'banos' => 2, 'estac' => 1, 'bodega' => true, 'ocupacion' => 'Desocupada', 'martillero' => 'R. Fuentes', 'precio' => 128000000, 'visitas' => [[5, 12, 2]]],
            'chicureo' => ['estado' => 'publicado', 'delta' => 2592000, 'duracion' => 1800, 'folio' => 'R-2026-126', 'direccion' => 'Los Robles 340, Casa 12, Chicureo', 'comuna' => 'Colina', 'region' => $rm, 'tipo' => 'Casa', 'sup' => 186, 'dorm' => 4, 'banos' => 3, 'estac' => 2, 'bodega' => true, 'ocupacion' => 'Ocupada', 'martillero' => 'C. Vergara', 'precio' => 210000000],
            'sanmartin' => ['estado' => 'finalizado', 'diasAtras' => 28, 'duracion' => 1800, 'folio' => 'R-2026-105', 'direccion' => 'San Martín 655, Casa A', 'comuna' => 'Concepción', 'region' => 'Región del Biobío', 'tipo' => 'Casa', 'sup' => 150, 'dorm' => 3, 'banos' => 2, 'estac' => 1, 'bodega' => false, 'ocupacion' => 'Desocupada', 'martillero' => 'R. Fuentes', 'precio' => 72000000],
            'alemania' => ['estado' => 'finalizado', 'diasAtras' => 35, 'duracion' => 1800, 'folio' => 'R-2026-108', 'direccion' => 'Av. Alemania 0980, Depto. 401', 'comuna' => 'Temuco', 'region' => 'Región de La Araucanía', 'tipo' => 'Departamento', 'sup' => 88, 'dorm' => 2, 'banos' => 2, 'estac' => 1, 'bodega' => false, 'ocupacion' => 'Desocupada', 'martillero' => 'R. Fuentes', 'precio' => 64000000],
        ];
    }
}
