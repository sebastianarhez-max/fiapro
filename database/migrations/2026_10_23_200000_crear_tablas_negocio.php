<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de negocio de FiaPro (según el modelo ER). Cada una se crea solo si no existe,
 * así funciona tanto en tu base actual como en una base nueva.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tienda')) {
            Schema::create('tienda', function (Blueprint $t) {
                $t->char('tienda_id', 36)->primary();
                $t->string('nombre_razon_social', 120);
                $t->string('giro', 80)->nullable();
                $t->string('direccion', 200)->nullable();
                $t->string('contacto', 120)->nullable();
                $t->string('estado', 10)->default('activo');
                $t->timestamp('creado_en')->useCurrent();
            });
        }

        if (! Schema::hasTable('cliente')) {
            Schema::create('cliente', function (Blueprint $t) {
                $t->char('cliente_id', 36)->primary();
                $t->char('tienda_id', 36);
                $t->string('nombre_completo', 120);
                $t->string('documento_identidad', 20);
                $t->string('contacto', 120)->nullable();
                $t->timestamp('creado_en')->useCurrent();
                $t->unique(['tienda_id', 'documento_identidad'], 'uq_cliente_documento_tienda');
                $t->foreign('tienda_id', 'fk_cliente_tienda')->references('tienda_id')->on('tienda')->restrictOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('producto')) {
            Schema::create('producto', function (Blueprint $t) {
                $t->char('producto_id', 36)->primary();
                $t->char('tienda_id', 36)->index('idx_producto_tienda');
                $t->string('proveedor', 120)->nullable();
                $t->string('marca', 80)->nullable();
                $t->string('descripcion', 200);
                $t->string('unidad_medida', 30)->nullable();
                $t->string('imagen_url', 300)->nullable();
                $t->decimal('precio_contado', 12, 2)->nullable();
                $t->decimal('precio_lista', 12, 2)->nullable();
                $t->string('modalidad_venta', 15)->nullable();
                $t->string('estado', 10)->default('activo');
                $t->foreign('tienda_id', 'fk_producto_tienda')->references('tienda_id')->on('tienda')->restrictOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('configuracion_credito')) {
            Schema::create('configuracion_credito', function (Blueprint $t) {
                $t->char('config_id', 36)->primary();
                $t->char('cliente_id', 36);
                $t->string('moneda', 3)->default('PEN');
                $t->string('tipo_tasa_comp', 10)->default('TEA');
                $t->decimal('tasa_comp', 12, 7);
                $t->integer('capitalizacion')->nullable();
                $t->string('tipo_tasa_mora', 10)->default('TEA');
                $t->decimal('tasa_mora', 12, 7);
                $t->decimal('limite_credito', 12, 2);
                $t->integer('max_meses')->default(6);
                $t->integer('fecha_corte');
                $t->time('hora_corte')->default('00:00:00');
                $t->integer('fecha_pago');
                $t->timestamp('vigente_desde')->useCurrent();
                $t->tinyInteger('activo')->default(1);
                $t->index('cliente_id', 'idx_config_cliente');
                $t->index(['cliente_id', 'vigente_desde'], 'idx_config_vigencia');
                $t->foreign('cliente_id', 'fk_config_cliente')->references('cliente_id')->on('cliente')->restrictOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('compra')) {
            Schema::create('compra', function (Blueprint $t) {
                $t->char('compra_id', 36)->primary();
                $t->char('cliente_id', 36)->index('idx_compra_cliente');
                $t->char('producto_id', 36)->index('idx_compra_producto');
                $t->decimal('cantidad', 12, 2);
                $t->decimal('precio_credito', 12, 2);
                $t->dateTime('fecha_compra')->index('idx_compra_fecha');
                $t->string('modalidad_pago', 15);
                $t->integer('numero_meses')->nullable();
                $t->string('estado', 15)->default('activa');
                $t->foreign('cliente_id', 'fk_compra_cliente')->references('cliente_id')->on('cliente')->restrictOnDelete()->cascadeOnUpdate();
                $t->foreign('producto_id', 'fk_compra_producto')->references('producto_id')->on('producto')->restrictOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('obligacion')) {
            Schema::create('obligacion', function (Blueprint $t) {
                $t->char('obligacion_id', 36)->primary();
                $t->char('compra_id', 36);
                $t->integer('numero_cuota');
                $t->date('fecha_vencimiento')->index('idx_obligacion_vencimiento');
                $t->decimal('capital', 12, 2);
                $t->decimal('interes_compensatorio', 12, 2)->default(0);
                $t->decimal('monto_total', 12, 2);
                $t->decimal('saldo', 12, 2);
                $t->string('estado', 15)->default('pendiente');
                $t->unique(['compra_id', 'numero_cuota'], 'uq_obligacion_compra_cuota');
                $t->foreign('compra_id', 'fk_obligacion_compra')->references('compra_id')->on('compra')->restrictOnDelete()->cascadeOnUpdate();
            });
        }

        if (! Schema::hasTable('pago')) {
            Schema::create('pago', function (Blueprint $t) {
                $t->char('pago_id', 36)->primary();
                $t->char('obligacion_id', 36)->index('idx_pago_obligacion');
                $t->date('fecha_real_pago')->index('idx_pago_fecha');
                $t->decimal('monto_pagado', 12, 2);
                $t->decimal('monto_capital', 12, 2)->default(0);
                $t->decimal('monto_interes_comp', 12, 2)->default(0);
                $t->decimal('monto_interes_mora', 12, 2)->default(0);
                $t->string('moneda', 3)->default('PEN');
                $t->string('medio_pago', 30)->nullable();
                $t->string('referencia', 200)->nullable();
                $t->timestamp('creado_en')->useCurrent();
                $t->foreign('obligacion_id', 'fk_pago_obligacion')->references('obligacion_id')->on('obligacion')->restrictOnDelete()->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        // Intencionalmente vacío: no se borran tablas con datos de negocio.
    }
};
