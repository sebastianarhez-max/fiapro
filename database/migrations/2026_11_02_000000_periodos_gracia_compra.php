<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Períodos de gracia total (0 a 3 quincenas) que el cliente elige en cada compra en cuotas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('compra', function (Blueprint $t) {
            if (! Schema::hasColumn('compra', 'periodos_gracia')) {
                $t->unsignedTinyInteger('periodos_gracia')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('compra', function (Blueprint $t) {
            if (Schema::hasColumn('compra', 'periodos_gracia')) {
                $t->dropColumn('periodos_gracia');
            }
        });
    }
};
