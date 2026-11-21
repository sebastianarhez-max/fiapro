<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Por indicación del docente ya no se registran "día de corte" ni "día de pago":
 * el pago único y cada cuota vencen cada 15 días desde la compra, y el listado de pago es quincenal.
 * Las columnas se dejan opcionales (nullable) para no perder los datos anteriores.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('configuracion_credito', function (Blueprint $t) {
            $t->integer('fecha_corte')->nullable()->change();
            $t->time('hora_corte')->nullable()->change();
            $t->integer('fecha_pago')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Se dejan opcionales.
    }
};
