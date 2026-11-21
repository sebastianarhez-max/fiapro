<?php

/**
 * Parámetros de negocio de FiaPro. Se pueden cambiar en el .env sin tocar código.
 * IMPORTANTE: los topes de TEA son referenciales; el tope legal vigente lo fija el BCRP
 * (se actualiza cada semestre). Reemplázalos por los valores vigentes.
 */
return [
    // Solo se trabaja con TEA (tasa efectiva anual), por recomendación del docente.
    'tea_max_ordinaria' => (float) env('FIAPRO_TEA_MAX_ORDINARIA', 30),
    'tea_max_mora' => (float) env('FIAPRO_TEA_MAX_MORA', 40),

    // Rango realista del límite de crédito por cliente (comercio de barrio), en la moneda del crédito.
    'limite_min' => (float) env('FIAPRO_LIMITE_MIN', 200),
    'limite_max' => (float) env('FIAPRO_LIMITE_MAX', 500),

    // Plazo: cuotas quincenales; el máximo de meses lo define cada cliente (1 a meses_max).
    'meses_max' => 4,
    'meses_defecto' => 3,
];


