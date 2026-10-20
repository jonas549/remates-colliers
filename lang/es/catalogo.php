<?php

// Catálogo del sitio público: etiquetas de la ficha del activo, tipos de propiedad, ocupación y estados del remate.
// Los tipos, ocupaciones y estados son DATOS (se guardan y se filtran en español): estas claves solo los muestran.
return [
    // Valor guardado => etiqueta. Un valor sin etiqueta se muestra tal cual (Catalogo::etiquetaTipo()).
    'tipos' => [
        'Departamento' => 'Departamento',
        'Casa' => 'Casa',
        'Oficina' => 'Oficina',
        'Local comercial' => 'Local comercial',
        'Terreno' => 'Terreno',
        'Bodega' => 'Bodega',
        'Industrial' => 'Industrial',
    ],
    'ocupaciones' => [
        'Desocupada' => 'Desocupada',
        'Ocupada' => 'Ocupada',
    ],
    // Estado visible del remate en las tarjetas (código de la tarjeta => etiqueta).
    'estados' => [
        'En vivo' => 'En vivo',
        'Próximo' => 'Próximo',
        'Cerrado' => 'Cerrado',
    ],

    'tarjeta' => [
        'lotes' => ':n lotes',
        'garantias_vencido' => 'Cierre de garantías: vencido',
        'garantias_hasta' => 'Hasta :fecha',
        'adjudicado_en' => 'Adjudicado en :monto',
        'no_adjudicado' => 'No adjudicado',
        'cerrado_sin_posturas' => 'Cerrado sin posturas',
    ],

    'medidas' => [
        'm2' => ':n m²',
        'm2_utiles' => ':n m² útiles',
        'dorm_banos' => ':dorm / :banos',
        'dorm_banos_corto' => ':dormD / :banosB',
        'mensuales' => ':monto mensuales',
        'si' => 'Sí',
    ],

    // Ficha principal del detalle (en mayúsculas, como el diseño).
    'ficha' => [
        'tipo' => 'TIPO',
        'superficie_util' => 'SUPERFICIE ÚTIL',
        'superficie_terraza' => 'SUPERFICIE TERRAZA',
        'superficie_terreno' => 'SUPERFICIE TERRENO',
        'dormitorios_banos' => 'DORMITORIOS / BAÑOS',
        'estacionamientos' => 'ESTACIONAMIENTOS',
        'bodega' => 'BODEGA',
        'anio_construccion' => 'AÑO DE CONSTRUCCIÓN',
        'ocupacion' => 'OCUPACIÓN',
        'orientacion' => 'ORIENTACIÓN',
        'piso' => 'PISO',
        'gastos_comunes' => 'GASTOS COMUNES',
        'entrega' => 'ENTREGA',
    ],

    'adicional' => [
        'estado_ocupacion' => 'Estado de ocupación',
        'uso' => 'Uso',
        'rol_avaluo' => 'Rol avalúo',
        'rol_estacionamiento' => 'Rol estacionamiento',
        'rol_bodega' => 'Rol bodega',
        'contribuciones' => 'Contribuciones',
        'martillero' => 'Martillero',
        'plazo_saldo' => 'Plazo de pago del saldo',
    ],

    'mandante' => [
        'mandante' => 'Mandante',
        'tipo_venta' => 'Tipo de venta',
        'ejecutivo' => 'Ejecutivo a cargo',
    ],

    // Evento .ics de «Agregar a mi calendario».
    'calendario' => [
        'titulo' => 'Remate :folio · :titulo',
        'descripcion' => 'Remate en línea de Colliers con transmisión en vivo. Cierra automáticamente al vencer el tiempo, sin extensiones.',
        'garantias_hasta' => ' Garantías hasta el :fecha.',
        'alarma' => 'Remate :folio en 1 hora',
    ],

    // Para el JavaScript: mismas etiquetas (valor guardado => etiqueta). Sin etiqueta, mostrar el valor.
    'js' => [
        'tipos' => [
            'Departamento' => 'Departamento',
            'Casa' => 'Casa',
            'Oficina' => 'Oficina',
            'Local comercial' => 'Local comercial',
            'Terreno' => 'Terreno',
            'Bodega' => 'Bodega',
            'Industrial' => 'Industrial',
        ],
        'ocupaciones' => [
            'Desocupada' => 'Desocupada',
            'Ocupada' => 'Ocupada',
        ],
        'estados' => [
            'En vivo' => 'En vivo',
            'Próximo' => 'Próximo',
            'Cerrado' => 'Cerrado',
        ],
    ],
];
