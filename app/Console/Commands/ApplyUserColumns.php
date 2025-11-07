<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

class ApplyUserColumns extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:apply-user-columns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Añade columnas faltantes a la tabla users sin hacer rollback';

    public function handle()
    {
        $this->info('Verificando tabla users...');

        if (!Schema::hasTable('users')) {
            $this->error('La tabla users no existe. Asegúrate de que las migraciones estén aplicadas.');
            return 1;
        }

        // apellido
        if (!Schema::hasColumn('users', 'apellido')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('apellido')->nullable()->after('name');
            });
            $this->info('Columna apellido añadida.');
        } else {
            $this->info('Columna apellido ya existe.');
        }

        // rol
        if (!Schema::hasColumn('users', 'rol')) {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('rol', ['admin', 'veterinario', 'asistente'])->default('asistente')->after('email');
            });
            $this->info('Columna rol añadida.');
        } else {
            $this->info('Columna rol ya existe.');
        }

        // activo
        if (!Schema::hasColumn('users', 'activo')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('activo')->default(true)->after('rol');
            });
            $this->info('Columna activo añadida.');
        } else {
            $this->info('Columna activo ya existe.');
        }

        // telefono
        if (!Schema::hasColumn('users', 'telefono')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('telefono')->nullable()->after('activo');
            });
            $this->info('Columna telefono añadida.');
        } else {
            $this->info('Columna telefono ya existe.');
        }

        // ultimo_acceso
        if (!Schema::hasColumn('users', 'ultimo_acceso')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('ultimo_acceso')->nullable()->after('telefono');
            });
            $this->info('Columna ultimo_acceso añadida.');
        } else {
            $this->info('Columna ultimo_acceso ya existe.');
        }

        // soft deletes
        if (!Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->softDeletes();
            });
            $this->info('Soft deletes añadidos.');
        } else {
            $this->info('Soft deletes ya existen.');
        }

        $this->info('Completado.');
        return 0;
    }
}
