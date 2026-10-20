<?php

// Public site catalog: asset sheet labels, property types, occupancy and auction statuses.
// Types, occupancies and statuses are DATA (stored and filtered in Spanish): these keys only display them.
return [
    // Stored value => label. A value without a label is shown as is (Catalogo::etiquetaTipo()).
    'tipos' => [
        'Departamento' => 'Apartment',
        'Casa' => 'House',
        'Oficina' => 'Office',
        'Local comercial' => 'Retail space',
        'Terreno' => 'Land',
        'Bodega' => 'Storage unit',
        'Industrial' => 'Industrial',
    ],
    'ocupaciones' => [
        'Desocupada' => 'Vacant',
        'Ocupada' => 'Occupied',
    ],
    // Visible auction status on the cards (card code => label).
    'estados' => [
        'En vivo' => 'Live',
        'Próximo' => 'Upcoming',
        'Cerrado' => 'Closed',
    ],

    'tarjeta' => [
        'lotes' => ':n lots',
        'garantias_vencido' => 'Deposit deadline: passed',
        'garantias_hasta' => 'Until :fecha',
        'adjudicado_en' => 'Awarded at :monto',
        'no_adjudicado' => 'Not awarded',
        'cerrado_sin_posturas' => 'Closed with no bids',
    ],

    'medidas' => [
        'm2' => ':n m²',
        'm2_utiles' => ':n m² usable',
        'dorm_banos' => ':dorm / :banos',
        'dorm_banos_corto' => ':dorm bd / :banos ba',
        'mensuales' => ':monto per month',
        'si' => 'Yes',
    ],

    // Main sheet on the detail page (uppercase, as in the design).
    'ficha' => [
        'tipo' => 'TYPE',
        'superficie_util' => 'USABLE AREA',
        'superficie_terraza' => 'TERRACE AREA',
        'superficie_terreno' => 'LOT AREA',
        'dormitorios_banos' => 'BEDROOMS / BATHROOMS',
        'estacionamientos' => 'PARKING SPACES',
        'bodega' => 'STORAGE UNIT',
        'anio_construccion' => 'YEAR BUILT',
        'ocupacion' => 'OCCUPANCY',
        'orientacion' => 'ORIENTATION',
        'piso' => 'FLOOR',
        'gastos_comunes' => 'COMMON EXPENSES',
        'entrega' => 'HANDOVER',
    ],

    'adicional' => [
        'estado_ocupacion' => 'Occupancy status',
        'uso' => 'Use',
        'rol_avaluo' => 'SII tax roll / appraisal',
        'rol_estacionamiento' => 'Parking space tax roll',
        'rol_bodega' => 'Storage unit tax roll',
        'contribuciones' => 'Property taxes',
        'martillero' => 'Auctioneer',
        'plazo_saldo' => 'Balance payment term',
    ],

    'mandante' => [
        'mandante' => 'Seller',
        'tipo_venta' => 'Type of sale',
        'ejecutivo' => 'Account executive',
    ],

    // .ics event for «Add to my calendar».
    'calendario' => [
        'titulo' => 'Auction :folio · :titulo',
        'descripcion' => 'Colliers online auction, streamed live. Closes automatically when time runs out, with no extensions.',
        'garantias_hasta' => ' Bid deposits until :fecha.',
        'alarma' => 'Auction :folio in 1 hour',
    ],

    // For JavaScript: same labels (stored value => label). Without a label, show the value.
    'js' => [
        'tipos' => [
            'Departamento' => 'Apartment',
            'Casa' => 'House',
            'Oficina' => 'Office',
            'Local comercial' => 'Retail space',
            'Terreno' => 'Land',
            'Bodega' => 'Storage unit',
            'Industrial' => 'Industrial',
        ],
        'ocupaciones' => [
            'Desocupada' => 'Vacant',
            'Ocupada' => 'Occupied',
        ],
        'estados' => [
            'En vivo' => 'Live',
            'Próximo' => 'Upcoming',
            'Cerrado' => 'Closed',
        ],
    ],
];
