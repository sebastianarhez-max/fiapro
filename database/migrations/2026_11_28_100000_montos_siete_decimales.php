<?php

use App\Services\CuentaCorriente;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Por indicación del docente, el cronograma se calcula con 7 decimales y recién al mostrarlo
 * se redondea a 2. Los montos de las cuotas se guardan con 7 decimales y las cuotas
 * PENDIENTES ya registradas se recalculan (las pagadas no se tocan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('obligacion', function (Blueprint $t) {
            $t->decimal('capital', 15, 7)->change();
            $t->decimal('interes_compensatorio', 15, 7)->default(0)->change();
            $t->decimal('monto_total', 15, 7)->change();
            $t->decimal('saldo', 15, 7)->change();
        });
        Schema::table('compra', function (Blueprint $t) {
            $t->decimal('interes_gracia', 15, 7)->nullable()->change();
            $t->decimal('capital_financiado', 15, 7)->nullable()->change();
            $t->decimal('cuota', 15, 7)->nullable()->change();
        });

        app(CuentaCorriente::class)->recalcularCuotasPendientes();
    }

    public function down(): void
    {
        Schema::table('obligacion', function (Blueprint $t) {
            $t->decimal('capital', 12, 2)->change();
            $t->decimal('interes_compensatorio', 12, 2)->default(0)->change();
            $t->decimal('monto_total', 12, 2)->change();
            $t->decimal('saldo', 12, 2)->change();
        });
        Schema::table('compra', function (Blueprint $t) {
            $t->decimal('interes_gracia', 12, 2)->nullable()->change();
            $t->decimal('capital_financiado', 12, 2)->nullable()->change();
            $t->decimal('cuota', 12, 2)->nullable()->change();
        });
    }
};
