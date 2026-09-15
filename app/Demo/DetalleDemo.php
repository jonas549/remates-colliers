<?php

namespace App\Demo;

/**
 * Datos de las fichas de detalle del prototipo: "Los Militares" (próximo) y "Apoquindo" (en vivo).
 * Montos tal como aparecen en el prototipo. OJO: el incremento mínimo del prototipo
 * ($1.000.000 y $1.500.000) contradice el acta (CLP 100.000): con el modelo real sale de configuración.
 */
class DetalleDemo
{
    public static function para(string $id): ?array
    {
        return match ($id) {
            'militares' => self::militares(),
            'apoquindo' => self::apoquindo(),
            default => null,
        };
    }

    private static function militares(): array
    {
        return [
            'id' => 'militares',
            'enVivo' => false,
            'folio' => 'R-2026-118',
            'direccion' => 'Los Militares 5620, Depto. 703',
            'meta' => 'Las Condes, Región Metropolitana · Departamento · 96 m² útiles · 3D / 2B · Desocupada',
            'base' => 142000000,
            'garantia' => 6000000,
            'incremento' => 1000000,
            'fecha' => '09-09-2026, 12:00',
            'limite' => '07-09-2026, 18:00',
            'martillero' => 'M. Ossandón',
            'deltaInicio' => 2 * 86400 + 5 * 3600 + 42,
            'fotoPrincipal' => 'prop-militares.jpg',
            'galeria' => ['prop-apoquindo.jpg', 'prop-elalba.jpg', 'prop-montt.jpg', 'prop-renaca.jpg'],
            'totalFotos' => 18,
            'descripcion' => 'Departamento en séptimo piso, orientación poniente, en un edificio de 2011 con conserjería 24 horas, piscina y quincho. Tres dormitorios, dos baños, cocina con logia y terraza de 9 m². Incluye estacionamiento y bodega. Se entrega desocupado, a pasos del eje Los Militares y con acceso directo a Apoquindo.',
            'ficha' => [
                'TIPO' => 'Departamento', 'SUPERFICIE ÚTIL' => '96 m²', 'SUPERFICIE TERRAZA' => '9 m²', 'DORMITORIOS / BAÑOS' => '3 / 2',
                'ESTACIONAMIENTOS' => '1 subterráneo', 'BODEGA' => 'Sí, 5 m²', 'AÑO DE CONSTRUCCIÓN' => '2011', 'OCUPACIÓN' => 'Desocupada',
                'ORIENTACIÓN' => 'Poniente', 'PISO' => '7 de 12', 'GASTOS COMUNES' => '$145.000 mensuales', 'ENTREGA' => '30 días desde la escritura',
            ],
            'adicional' => [
                'Estado de ocupación' => 'Desocupada', 'Uso' => 'Residencial', 'Rol avalúo' => '2871-14', 'Rol estacionamiento' => '2871-96',
                'Rol bodega' => '2871-152', 'Contribuciones' => 'Al día', 'Martillero' => 'M. Ossandón', 'Plazo de pago del saldo' => '30 días desde la adjudicación',
            ],
            'mandante' => ['Mandante' => 'Banco Consorcio', 'Tipo de venta' => 'Bien recuperado', 'Ejecutivo a cargo' => 'Paula Riquelme · Colliers Chile'],
            'mapa' => ['lat' => -33.4093, 'lng' => -70.5772, 'etiqueta' => 'Los Militares 5620, Depto. 703', 'texto' => 'Los Militares 5620, Las Condes · a 450 m de Metro Los Dominicos'],
            'visitas' => [['2 de septiembre', '11:00 – 13:00'], ['5 de septiembre', '16:00 – 18:00']],
            'documentos' => [
                ['Bases especiales del remate', 'PDF · 410 KB'], ['Procedimiento de subasta en línea', 'PDF · 180 KB'],
                ['Bases generales', 'PDF · 260 KB'], ['Certificado de dominio vigente', 'PDF · 88 KB'],
            ],
            'garantiaCondicion' => 'La garantía se constituye por vale a la vista o transferencia y es revisada manualmente por Colliers hasta 48 horas antes del inicio. Si el remate no se concreta, la propiedad se publica en un remate nuevo con fecha y condiciones propias.',
            'recomendados' => [
                ['id' => 'apoquindo', 'direccion' => 'Av. Apoquindo 4501, Depto. 1802', 'ubicacion' => 'Las Condes, Región Metropolitana', 'tipoSup' => 'Departamento · 118 m² · 3D / 2B', 'precio' => 185000000, 'garantia' => 8000000, 'fecha' => 'Hoy, 12:00', 'estado' => 'EN VIVO', 'vivo' => true],
                ['id' => 'montt', 'direccion' => 'Manuel Montt 1740, Depto. 505', 'ubicacion' => 'Providencia, Región Metropolitana', 'tipoSup' => 'Departamento · 74 m² · 2D / 1B', 'precio' => 96000000, 'garantia' => 4000000, 'fecha' => '16-09-2026, 12:00', 'estado' => 'PRÓXIMO', 'vivo' => false],
                ['id' => 'chicureo', 'direccion' => 'Los Robles 340, Casa 12, Chicureo', 'ubicacion' => 'Colina, Región Metropolitana', 'tipoSup' => 'Casa · 186 m² · 4D / 3B', 'precio' => 210000000, 'garantia' => 9000000, 'fecha' => '07-10-2026, 12:00', 'estado' => 'PRÓXIMO', 'vivo' => false],
            ],
        ];
    }

    private static function apoquindo(): array
    {
        return [
            'id' => 'apoquindo',
            'enVivo' => true,
            'folio' => 'R-2026-114',
            'direccion' => 'Av. Apoquindo 4501, Depto. 1802',
            'meta' => 'Las Condes, Región Metropolitana · Departamento · 118 m² útiles · 3D / 2B',
            'base' => 185000000,
            'garantia' => 8000000,
            'incremento' => 1500000,
            'fecha' => 'Hoy, 12:00',
            'martillero' => 'M. Ossandón',
            'deltaCierre' => 41 * 60 + 22,
            'video' => 'jfKfPfyJRdk',
            'galeria' => ['prop-apoquindo.jpg', 'prop-militares.jpg', 'prop-elalba.jpg', 'prop-montt.jpg'],
            'totalFotos' => 24,
            // Pujas de ejemplo: monto, número de postor, segundos transcurridos al abrir la página.
            'pujas' => [[198500000, 7, 34], [195000000, 3, 96], [191500000, 7, 172], [188000000, 12, 240], [186500000, 3, 318], [185000000, 12, 402]],
            'descripcion' => 'Departamento en piso 18 con orientación nororiente y vista despejada al parque, en un edificio de 2014 con conserjería 24 horas, gimnasio y sala multiuso. Tres dormitorios, dos baños, cocina equipada y terraza de 14 m². Se entrega desocupado, a media cuadra de Metro Manquehue y del eje Apoquindo, con comercio, colegios y clínicas en el entorno inmediato.',
            'ficha' => [
                'TIPO' => 'Departamento', 'SUPERFICIE ÚTIL' => '118 m²', 'SUPERFICIE TERRAZA' => '14 m²', 'DORMITORIOS / BAÑOS' => '3 / 2',
                'ESTACIONAMIENTOS' => '2 subterráneos', 'BODEGA' => 'Sí, 6 m²', 'AÑO DE CONSTRUCCIÓN' => '2014', 'OCUPACIÓN' => 'Desocupada',
                'ROL SII' => '1234-56', 'CONTRIBUCIONES' => 'Al día', 'GASTOS COMUNES' => '$180.000 mensuales', 'ENTREGA' => '30 días desde la escritura',
            ],
            'adicional' => [
                'Estado de ocupación' => 'Desocupada', 'Uso' => 'Residencial', 'Rol avalúo' => '1234-56', 'Rol estacionamiento' => '1234-118 / 1234-119',
                'Rol bodega' => '1234-206', 'Contribuciones' => 'Al día', 'Gastos comunes' => '$180.000 mensuales', 'Plazo de pago del saldo' => '30 días desde la adjudicación',
            ],
            'mandante' => ['Mandante' => 'Banco Consorcio', 'Tipo de venta' => 'Bien recuperado', 'Ejecutivo a cargo' => 'Paula Riquelme · Colliers Chile'],
            'mapa' => ['lat' => -33.4145, 'lng' => -70.5810, 'etiqueta' => 'Av. Apoquindo 4501, Depto. 1802', 'texto' => 'Av. Apoquindo 4501, Las Condes · a 300 m de Metro Manquehue'],
            'visitas' => [['2 de septiembre', '11:00 – 13:00'], ['5 de septiembre', '16:00 – 18:00']],
            'documentos' => [
                ['Bases especiales del remate', 'PDF · 420 KB'], ['Procedimiento de subasta en línea', 'PDF · 180 KB'],
                ['Bases generales', 'PDF · 260 KB'], ['Certificado de dominio vigente', 'PDF · 95 KB'],
            ],
            'garantiaCondicion' => 'La garantía se constituye por vale a la vista o transferencia y es revisada manualmente por Colliers antes del inicio. Si el remate no se concreta, la propiedad se publica en un remate nuevo con fecha y condiciones propias.',
            'recomendados' => [
                ['id' => 'militares', 'direccion' => 'Los Militares 5620, Depto. 703', 'ubicacion' => 'Las Condes, Región Metropolitana', 'tipoSup' => 'Departamento · 96 m² · 3D / 2B', 'precio' => 142000000, 'garantia' => 6000000, 'fecha' => '09-09-2026, 12:00', 'estado' => 'PRÓXIMO', 'vivo' => false],
                ['id' => 'montt', 'direccion' => 'Manuel Montt 1740, Depto. 505', 'ubicacion' => 'Providencia, Región Metropolitana', 'tipoSup' => 'Departamento · 74 m² · 2D / 1B', 'precio' => 96000000, 'garantia' => 4000000, 'fecha' => '16-09-2026, 12:00', 'estado' => 'PRÓXIMO', 'vivo' => false],
                ['id' => 'chicureo', 'direccion' => 'Los Robles 340, Casa 12, Chicureo', 'ubicacion' => 'Colina, Región Metropolitana', 'tipoSup' => 'Casa · 186 m² · 4D / 3B', 'precio' => 210000000, 'garantia' => 9000000, 'fecha' => '07-10-2026, 12:00', 'estado' => 'PRÓXIMO', 'vivo' => false],
            ],
        ];
    }
}
