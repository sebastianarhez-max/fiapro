<?php

namespace App\Support;

/** Formato de montos según la moneda del crédito (sin conversión entre monedas). */
class Moneda
{
    public static function simbolo(?string $moneda): string
    {
        return $moneda === 'USD' ? 'US$' : 'S/';
    }

    public static function f(float|string|null $monto, ?string $moneda = 'PEN'): string
    {
        return self::simbolo($moneda) . ' ' . number_format((float) $monto, 2);
    }
}
