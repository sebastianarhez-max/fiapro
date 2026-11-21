<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columnas y tablas para los módulos de compras, listado de pago en la fecha de corte
 * y pagos con mora. Igual que las migraciones anteriores, solo agrega lo que falta.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Compra: monto total, tasa moratoria pactada al momento de la compra y fecha de registro
        Schema::table('compra', function (Blueprint $t) {
            if (! Schema::hasColumn('compra', 'monto')) {
                $t->decimal('monto', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('compra', 'tea_mora_aplicada')) {
                $t->decimal('tea_mora_aplicada', 12, 7)->nullable();
            }
            if (! Schema::hasColumn('compra', 'moneda')) {
                $t->string('moneda', 3)->default('PEN');
            }
            if (! Schema::hasColumn('compra', 'creado_en')) {
                $t->timestamp('creado_en')->nullable()->useCurrent();
            }
        });

        // Obligación: datos de la mora y del pago efectivo
        Schema::table('obligacion', function (Blueprint $t) {
            if (! Schema::hasColumn('obligacion', 'dias_mora')) {
                $t->integer('dias_mora')->default(0);
            }
            if (! Schema::hasColumn('obligacion', 'interes_moratorio')) {
                $t->decimal('interes_moratorio', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('obligacion', 'fecha_pago_real')) {
                $t->date('fecha_pago_real')->nullable();
            }
        });

        // Listado de pago generado en cada fecha de corte
        if (! Schema::hasTable('listado_pago')) {
            Schema::create('listado_pago', function (Blueprint $t) {
                $t->char('listado_id', 36)->primary();
                $t->char('cliente_id', 36)->index('idx_listado_cliente');
                $t->dateTime('fecha_corte');
                $t->date('fecha_pago');
                $t->decimal('total_capital', 12, 2)->default(0);
                $t->decimal('total_interes', 12, 2)->default(0);
                $t->decimal('total_mora', 12, 2)->default(0);
                $t->decimal('total', 12, 2)->default(0);
                $t->string('moneda', 3)->default('PEN');
                $t->json('detalle')->nullable();
                $t->timestamp('generado_en')->useCurrent();
                $t->unique(['cliente_id', 'fecha_corte'], 'uq_listado_cliente_corte');
                $t->foreign('cliente_id', 'fk_listado_cliente')->references('cliente_id')->on('cliente')->restrictOnDelete()->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        // Intencionalmente vacío: no se borran datos de negocio.
    }
};
