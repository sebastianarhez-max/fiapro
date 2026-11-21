<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Roles y vínculo con tienda / cliente en la tabla de login (users)
        Schema::table('users', function (Blueprint $t) {
            if (! Schema::hasColumn('users', 'rol')) {
                $t->string('rol', 20)->default('cliente');      // admin_sistema | admin_tienda | cliente
            }
            if (! Schema::hasColumn('users', 'estado')) {
                $t->string('estado', 10)->default('activo');
            }
            if (! Schema::hasColumn('users', 'tienda_id')) {
                $t->char('tienda_id', 36)->nullable()->index();
            }
            if (! Schema::hasColumn('users', 'cliente_id')) {
                $t->char('cliente_id', 36)->nullable()->index();
            }
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreign('tienda_id', 'fk_users_tienda')->references('tienda_id')->on('tienda')->restrictOnDelete()->cascadeOnUpdate();
            $t->foreign('cliente_id', 'fk_users_cliente')->references('cliente_id')->on('cliente')->restrictOnDelete()->cascadeOnUpdate();
        });

        // 2) Bitácora: registro de todas las operaciones
        if (! Schema::hasTable('bitacora')) {
            Schema::create('bitacora', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable()->index();
                $t->char('tienda_id', 36)->nullable()->index();
                $t->string('accion', 40);
                $t->string('entidad', 40);
                $t->string('entidad_id', 60)->nullable();
                $t->json('detalle')->nullable();
                $t->string('ip', 45)->nullable();
                $t->timestamp('creado_en')->useCurrent();
            });
        }

        // 3) Datos para reconstruir el cuadro de flujo de cada compra financiada
        Schema::table('compra', function (Blueprint $t) {
            if (! Schema::hasColumn('compra', 'fecha_primer_pago')) {
                $t->date('fecha_primer_pago')->nullable();
                $t->integer('dias_gracia')->nullable();
                $t->decimal('interes_gracia', 12, 2)->nullable();
                $t->decimal('capital_financiado', 12, 2)->nullable();
                $t->decimal('tea_aplicada', 12, 7)->nullable();
                $t->decimal('cuota', 12, 2)->nullable();
            }
        });
    }

    public function down(): void
    {
        // Intencionalmente vacío.
    }
};
