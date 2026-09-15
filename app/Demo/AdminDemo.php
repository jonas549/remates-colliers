<?php

namespace App\Demo;

/** Datos de ejemplo de las pantallas de administración del prototipo. */
class AdminDemo
{
    public static function dashboard(): array
    {
        $clp = fn ($n) => RematesDemo::clp($n);

        return [
            'kpis' => [
                ['SUBASTAS ACTIVAS', '7', '1 en vivo · 6 próximas', false],
                ['POSTORES REGISTRADOS', '184', '+12 esta semana', false],
                ['CUENTAS POR APROBAR', '6', 'Revisión manual pendiente', true],
                ['GARANTÍAS EN REVISIÓN', '9', '$54.000.000 comprometidos', true],
                ['ADJUDICADO EN AGOSTO', '$1.284M', '8 de 10 remates concretados', false],
            ],
            'subastas' => [
                ['id' => 'apoquindo', 'folio' => 'R-2026-114', 'direccion' => 'Av. Apoquindo 4501, Depto. 1802', 'comuna' => 'Las Condes', 'estado' => 'EN VIVO', 'tono' => 'vivo', 'base' => $clp(185000000), 'actual' => $clp(198500000), 'postores' => '14 activos', 'cierre' => 'Hoy, 12:41'],
                ['id' => 'militares', 'folio' => 'R-2026-118', 'direccion' => 'Los Militares 5620, Depto. 703', 'comuna' => 'Las Condes', 'estado' => 'PRÓXIMO', 'tono' => 'proximo', 'base' => $clp(142000000), 'actual' => '—', 'postores' => '9 inscritos', 'cierre' => '09-09, 12:30'],
                ['id' => 'elalba', 'folio' => 'R-2026-119', 'direccion' => 'Camino El Alba 11.850, Casa 6', 'comuna' => 'Las Condes', 'estado' => 'PRÓXIMO', 'tono' => 'proximo', 'base' => $clp(390000000), 'actual' => '—', 'postores' => '4 inscritos', 'cierre' => '11-09, 12:30'],
                ['id' => 'montt', 'folio' => 'R-2026-121', 'direccion' => 'Manuel Montt 1740, Depto. 505', 'comuna' => 'Providencia', 'estado' => 'PRÓXIMO', 'tono' => 'proximo', 'base' => $clp(96000000), 'actual' => '—', 'postores' => '6 inscritos', 'cierre' => '16-09, 12:30'],
                ['id' => 'alemania', 'folio' => 'R-2026-108', 'direccion' => 'Av. Alemania 0980, Depto. 401', 'comuna' => 'Temuco', 'estado' => 'CERRADO', 'tono' => 'cerrado', 'base' => $clp(64000000), 'actual' => 'Sin postores', 'postores' => '0', 'cierre' => '12-08, 12:30'],
            ],
            'pendientes' => [
                ['Rodrigo Salas Pinto', 'Cuenta nueva · RUT 15.482.331-2', 'CUENTA', 'revision'],
                ['Inversiones Andes SpA', 'Garantía R-2026-118 · $6.000.000', 'GARANTÍA', 'pendiente'],
                ['Camila Ortiz Vera', 'Garantía R-2026-118 · comprobante recibido', 'GARANTÍA', 'pendiente'],
                ['Jorge Tapia Ruiz', 'Cuenta nueva · faltan documentos', 'CUENTA', 'revision'],
            ],
            'actividad' => [
                ['Nueva puja de $198.500.000 en R-2026-114', 'hace 34 seg', '#c8102e'],
                ['Garantía aprobada a María Paz González (R-2026-114)', 'hace 22 min', '#1c5330'],
                ['Se publicó el remate R-2026-121, Manuel Montt 1740', 'hace 2 h', '#25408f'],
                ['Garantía rechazada a Luis Cárcamo: monto insuficiente', 'ayer, 17:40', '#c8102e'],
                ['R-2026-108 cerró sin postores; se solicitó republicación', '12-08, 12:30', '#a3abb8'],
                ['Carolina Méndez cerró anticipadamente R-2026-101', '05-08, 11:12', '#a3abb8'],
            ],
        ];
    }

    public static function subastas(): array
    {
        return [
            ['id' => 1, 'folio' => 'R-2026-114', 'direccion' => 'Av. Apoquindo 4501, Depto. 1802', 'comuna' => 'Las Condes', 'martillero' => 'M. Ossandón', 'estado' => 'En vivo', 'base' => 185000000, 'actual' => 198500000, 'garantia' => 8000000, 'inscritos' => '14 habilitados', 'inicio' => 'Hoy, 12:00'],
            ['id' => 2, 'folio' => 'R-2026-118', 'direccion' => 'Los Militares 5620, Depto. 703', 'comuna' => 'Las Condes', 'martillero' => 'M. Ossandón', 'estado' => 'Próxima', 'base' => 142000000, 'actual' => null, 'garantia' => 6000000, 'inscritos' => '9 inscritos', 'inicio' => '09-09, 12:00'],
            ['id' => 3, 'folio' => 'R-2026-119', 'direccion' => 'Camino El Alba 11.850, Casa 6', 'comuna' => 'Las Condes', 'martillero' => 'C. Vergara', 'estado' => 'Próxima', 'base' => 390000000, 'actual' => null, 'garantia' => 15000000, 'inscritos' => '4 inscritos', 'inicio' => '11-09, 12:00'],
            ['id' => 4, 'folio' => 'R-2026-121', 'direccion' => 'Manuel Montt 1740, Depto. 505', 'comuna' => 'Providencia', 'martillero' => 'C. Vergara', 'estado' => 'Próxima', 'base' => 96000000, 'actual' => null, 'garantia' => 4000000, 'inscritos' => '6 inscritos', 'inicio' => '16-09, 12:00'],
            ['id' => 5, 'folio' => 'R-2026-124', 'direccion' => 'Av. Concón–Reñaca 250, Depto. 902', 'comuna' => 'Viña del Mar', 'martillero' => 'R. Fuentes', 'estado' => 'Borrador', 'base' => 128000000, 'actual' => null, 'garantia' => 5000000, 'inscritos' => '—', 'inicio' => 'Sin fecha'],
            ['id' => 6, 'folio' => 'R-2026-105', 'direccion' => 'San Martín 655, Casa A', 'comuna' => 'Concepción', 'martillero' => 'R. Fuentes', 'estado' => 'Adjudicada', 'base' => 72000000, 'actual' => 88000000, 'garantia' => 3000000, 'inscritos' => '11 habilitados', 'inicio' => '19-08, 12:00'],
            ['id' => 7, 'folio' => 'R-2026-108', 'direccion' => 'Av. Alemania 0980, Depto. 401', 'comuna' => 'Temuco', 'martillero' => 'R. Fuentes', 'estado' => 'Cerrada', 'base' => 64000000, 'actual' => null, 'garantia' => 3000000, 'inscritos' => '3 habilitados', 'inicio' => '12-08, 12:00'],
        ];
    }

    public static function postores(): array
    {
        return [
            ['id' => 1, 'nombre' => 'María Paz González', 'rut' => '15.482.331-2', 'tipo' => 'Persona natural', 'correo' => 'mpgonzalez@correo.cl', 'telefono' => '+56 9 8123 4455', 'remate' => 'Av. Apoquindo 4501, Depto. 1802', 'folio' => 'R-2026-114', 'monto' => 8000000, 'medio' => 'Vale a la vista', 'cuenta' => 'Aprobado', 'garantia' => 'Aprobada', 'actualizado' => 'Aprobada el 28-08, 10:12'],
            ['id' => 2, 'nombre' => 'Inversiones Andes SpA', 'rut' => '76.543.210-K', 'tipo' => 'Persona jurídica', 'correo' => 'contacto@andes.cl', 'telefono' => '+56 2 2233 4455', 'remate' => 'Los Militares 5620, Depto. 703', 'folio' => 'R-2026-118', 'monto' => 6000000, 'medio' => 'Transferencia', 'cuenta' => 'Aprobado', 'garantia' => 'En revisión', 'actualizado' => 'Comprobante del 29-08'],
            ['id' => 3, 'nombre' => 'Camila Ortiz Vera', 'rut' => '17.998.221-5', 'tipo' => 'Persona natural', 'correo' => 'camila.ortiz@correo.cl', 'telefono' => '+56 9 7744 1122', 'remate' => 'Los Militares 5620, Depto. 703', 'folio' => 'R-2026-118', 'monto' => 6000000, 'medio' => 'Vale a la vista', 'cuenta' => 'Aprobado', 'garantia' => 'En revisión', 'actualizado' => 'Comprobante del 30-08'],
            ['id' => 4, 'nombre' => 'Rodrigo Salas Pinto', 'rut' => '13.220.884-1', 'tipo' => 'Persona natural', 'correo' => 'rsalas@correo.cl', 'telefono' => '+56 9 6612 8890', 'remate' => '—', 'folio' => '—', 'monto' => 0, 'medio' => 'Sin garantía', 'cuenta' => 'En revisión', 'garantia' => 'Pendiente', 'actualizado' => 'Registro del 30-08'],
            ['id' => 5, 'nombre' => 'Jorge Tapia Ruiz', 'rut' => '9.884.112-3', 'tipo' => 'Persona natural', 'correo' => 'jtapia@correo.cl', 'telefono' => '+56 9 5533 7788', 'remate' => '—', 'folio' => '—', 'monto' => 0, 'medio' => 'Sin garantía', 'cuenta' => 'En revisión', 'garantia' => 'Pendiente', 'actualizado' => 'Faltan documentos'],
            ['id' => 6, 'nombre' => 'Luis Cárcamo Bravo', 'rut' => '12.101.559-7', 'tipo' => 'Persona natural', 'correo' => 'lcarcamo@correo.cl', 'telefono' => '+56 9 4411 2277', 'remate' => 'Camino El Alba 11.850, Casa 6', 'folio' => 'R-2026-119', 'monto' => 15000000, 'medio' => 'Transferencia', 'cuenta' => 'Aprobado', 'garantia' => 'Rechazada', 'actualizado' => 'Monto insuficiente · 27-08'],
            ['id' => 7, 'nombre' => 'Patricia Fuentes Lira', 'rut' => '16.774.203-9', 'tipo' => 'Persona natural', 'correo' => 'pfuentes@correo.cl', 'telefono' => '+56 9 3322 9911', 'remate' => 'Camino El Alba 11.850, Casa 6', 'folio' => 'R-2026-119', 'monto' => 15000000, 'medio' => 'Vale a la vista', 'cuenta' => 'Aprobado', 'garantia' => 'Aprobada', 'actualizado' => 'Aprobada el 26-08, 16:40'],
            ['id' => 8, 'nombre' => 'Constructora Lonquén Ltda.', 'rut' => '77.201.330-4', 'tipo' => 'Persona jurídica', 'correo' => 'finanzas@lonquen.cl', 'telefono' => '+56 2 2988 1200', 'remate' => 'Manuel Montt 1740, Depto. 505', 'folio' => 'R-2026-121', 'monto' => 4000000, 'medio' => 'Transferencia', 'cuenta' => 'Aprobado', 'garantia' => 'Pendiente', 'actualizado' => 'Inscrito el 31-08'],
        ];
    }
}
