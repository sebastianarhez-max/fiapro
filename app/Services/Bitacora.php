<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/** Registra cada operación relevante en la tabla bitacora. */
class Bitacora
{
    public static function registrar(string $accion, string $entidad, ?string $entidadId = null, array $detalle = []): void
    {
        $u = Auth::user();

        DB::table('bitacora')->insert([
            'user_id' => $u?->id,
            'tienda_id' => $u?->tienda_id,
            'accion' => $accion,
            'entidad' => $entidad,
            'entidad_id' => $entidadId,
            'detalle' => $detalle ? json_encode($detalle, JSON_UNESCAPED_UNICODE) : null,
            'ip' => request()->ip(),
            'creado_en' => now(),
        ]);
    }
}
