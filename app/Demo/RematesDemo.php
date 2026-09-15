<?php

namespace App\Demo;

/**
 * Datos de ejemplo del prototipo de Claude Design, usados mientras las vistas no tienen backend
 * (Bloque T). Las vistas reciben exactamente esta forma; cuando exista el modelo de datos se
 * reemplaza esta clase por consultas reales sin tocar las plantillas.
 */
class RematesDemo
{
    /** Remates del listado, tal como aparecen en index.dc.html. Montos en pesos (enteros). */
    public static function todos(): array
    {
        return [
            ['id' => 'apoquindo', 'folio' => 'R-2026-114', 'direccion' => 'Av. Apoquindo 4501, Depto. 1802', 'comuna' => 'Las Condes', 'region' => 'Región Metropolitana', 'tipo' => 'Departamento', 'sup' => 118, 'dorm' => 3, 'banos' => 2, 'estac' => 2, 'bodega' => true, 'ocupacion' => 'Desocupada', 'visita' => '', 'martillero' => 'M. Ossandón', 'precio' => 185000000, 'garantia' => 8000000, 'limite' => 'Cierre de garantías: vencido', 'estado' => 'En vivo', 'fecha' => 'Hoy, 12:00'],
            ['id' => 'militares', 'folio' => 'R-2026-118', 'direccion' => 'Los Militares 5620, Depto. 703', 'comuna' => 'Las Condes', 'region' => 'Región Metropolitana', 'tipo' => 'Departamento', 'sup' => 96, 'dorm' => 3, 'banos' => 2, 'estac' => 1, 'bodega' => true, 'ocupacion' => 'Desocupada', 'visita' => '05-09-2026, 11:00', 'martillero' => 'M. Ossandón', 'precio' => 142000000, 'garantia' => 6000000, 'limite' => 'Hasta 07-09, 18:00', 'estado' => 'Próximo', 'fecha' => '09-09-2026, 12:00'],
            ['id' => 'elalba', 'folio' => 'R-2026-119', 'direccion' => 'Camino El Alba 11.850, Casa 6', 'comuna' => 'Las Condes', 'region' => 'Región Metropolitana', 'tipo' => 'Casa', 'sup' => 240, 'dorm' => 4, 'banos' => 3, 'estac' => 2, 'bodega' => false, 'ocupacion' => 'Ocupada', 'visita' => '', 'martillero' => 'C. Vergara', 'precio' => 390000000, 'garantia' => 15000000, 'limite' => 'Hasta 09-09, 18:00', 'estado' => 'Próximo', 'fecha' => '11-09-2026, 12:00'],
            ['id' => 'montt', 'folio' => 'R-2026-121', 'direccion' => 'Manuel Montt 1740, Depto. 505', 'comuna' => 'Providencia', 'region' => 'Región Metropolitana', 'tipo' => 'Departamento', 'sup' => 74, 'dorm' => 2, 'banos' => 1, 'estac' => 1, 'bodega' => false, 'ocupacion' => 'Desocupada', 'visita' => '10-09-2026, 16:00', 'martillero' => 'C. Vergara', 'precio' => 96000000, 'garantia' => 4000000, 'limite' => 'Hasta 14-09, 18:00', 'estado' => 'Próximo', 'fecha' => '16-09-2026, 12:00'],
            ['id' => 'pedrovaldivia', 'folio' => 'R-2026-122', 'direccion' => 'Pedro de Valdivia 2130, Depto. 1204', 'comuna' => 'Ñuñoa', 'region' => 'Región Metropolitana', 'tipo' => 'Departamento', 'sup' => 68, 'dorm' => 2, 'banos' => 1, 'estac' => 0, 'bodega' => true, 'ocupacion' => 'Ocupada', 'visita' => '', 'martillero' => 'M. Ossandón', 'precio' => 84000000, 'garantia' => 4000000, 'limite' => 'Hasta 21-09, 18:00', 'estado' => 'Próximo', 'fecha' => '23-09-2026, 12:00'],
            ['id' => 'renaca', 'folio' => 'R-2026-124', 'direccion' => 'Av. Concón–Reñaca 250, Depto. 902', 'comuna' => 'Viña del Mar', 'region' => 'Región de Valparaíso', 'tipo' => 'Departamento', 'sup' => 112, 'dorm' => 3, 'banos' => 2, 'estac' => 1, 'bodega' => true, 'ocupacion' => 'Desocupada', 'visita' => '25-09-2026, 12:00', 'martillero' => 'R. Fuentes', 'precio' => 128000000, 'garantia' => 5000000, 'limite' => 'Hasta 28-09, 18:00', 'estado' => 'Próximo', 'fecha' => '30-09-2026, 12:00'],
            ['id' => 'chicureo', 'folio' => 'R-2026-126', 'direccion' => 'Los Robles 340, Casa 12, Chicureo', 'comuna' => 'Colina', 'region' => 'Región Metropolitana', 'tipo' => 'Casa', 'sup' => 186, 'dorm' => 4, 'banos' => 3, 'estac' => 2, 'bodega' => true, 'ocupacion' => 'Ocupada', 'visita' => '', 'martillero' => 'C. Vergara', 'precio' => 210000000, 'garantia' => 9000000, 'limite' => 'Hasta 05-10, 18:00', 'estado' => 'Próximo', 'fecha' => '07-10-2026, 12:00'],
            ['id' => 'sanmartin', 'folio' => 'R-2026-105', 'direccion' => 'San Martín 655, Casa A', 'comuna' => 'Concepción', 'region' => 'Región del Biobío', 'tipo' => 'Casa', 'sup' => 150, 'dorm' => 3, 'banos' => 2, 'estac' => 1, 'bodega' => false, 'ocupacion' => 'Desocupada', 'visita' => '', 'martillero' => 'R. Fuentes', 'precio' => 72000000, 'garantia' => 3000000, 'limite' => '', 'estado' => 'Cerrado', 'fecha' => '19-08-2026', 'resultado' => 'Adjudicado en $88.000.000'],
            ['id' => 'alemania', 'folio' => 'R-2026-108', 'direccion' => 'Av. Alemania 0980, Depto. 401', 'comuna' => 'Temuco', 'region' => 'Región de La Araucanía', 'tipo' => 'Departamento', 'sup' => 88, 'dorm' => 2, 'banos' => 2, 'estac' => 1, 'bodega' => false, 'ocupacion' => 'Desocupada', 'visita' => '', 'martillero' => 'R. Fuentes', 'precio' => 64000000, 'garantia' => 3000000, 'limite' => '', 'estado' => 'Cerrado', 'fecha' => '12-08-2026', 'resultado' => 'No adjudicado', 'nuevoRemate' => '14-10-2026'],
        ];
    }

    public static function buscar(string $id): array
    {
        return collect(self::todos())->firstWhere('id', $id);
    }

    /** Remate destacado en el hero del listado y en la foto del login. */
    public static function proximoDestacado(): array
    {
        return self::buscar('militares') + ['fechaCorta' => '09-09-2026', 'foto' => 'prop-hero.jpg'];
    }

    public static function clp(int $monto): string
    {
        return '$' . number_format($monto, 0, ',', '.');
    }
}
