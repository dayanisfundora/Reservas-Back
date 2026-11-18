<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 200);
            $table->string('nombre_corto', 100)->nullable();
            $table->string('nit', 50)->nullable();
            $table->string('logo')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('empresa_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'empresa_id']);
        });

        Schema::create('casas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->string('codigo', 50)->nullable();
            $table->text('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['empresa_id', 'activo']);
        });

        Schema::create('habitaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('casa_id')->constrained('casas')->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->string('codigo', 50)->nullable();
            $table->text('descripcion')->nullable();
            $table->string('tipo', 20);
            $table->unsignedInteger('capacidad')->default(1);
            $table->string('color', 7)->default('#0f766e');
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->index(['casa_id', 'activo']);
            $table->index(['empresa_id', 'casa_id']);
        });

        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('casa_id')->constrained('casas')->cascadeOnDelete();
            $table->foreignId('habitacion_id')->constrained('habitaciones')->cascadeOnDelete();
            $table->string('cliente_nombre', 200);
            $table->string('genero', 20)->nullable();
            $table->json('generos')->nullable();
            $table->string('agencia', 200)->nullable();
            $table->time('hora_llegada')->nullable();
            $table->string('vuelo', 100)->nullable();
            $table->boolean('taxi_llegada')->default(false);
            $table->boolean('taxi_salida')->default(false);
            $table->unsignedInteger('cantidad_personas');
            $table->json('camas')->nullable();
            $table->unsignedInteger('cantidad_noches');
            $table->date('fecha_entrada');
            $table->date('fecha_salida');
            $table->string('estado', 20)->default('pendiente');
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->index(['habitacion_id', 'fecha_entrada', 'fecha_salida']);
            $table->index(['casa_id', 'fecha_entrada']);
            $table->index(['empresa_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservas');
        Schema::dropIfExists('habitaciones');
        Schema::dropIfExists('casas');
        Schema::dropIfExists('empresa_user');
        Schema::dropIfExists('empresas');
    }
};
