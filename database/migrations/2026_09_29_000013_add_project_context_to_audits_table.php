<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contexto de proyecto en la auditoría: permite consultar "todo lo ocurrido en un proyecto"
 * (y limitar al docente a sus proyectos) sin joins polimórficos. Sin FK a propósito:
 * el registro de auditoría debe sobrevivir a la entidad (ADR-009).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable()->after('auditable_id');
            $table->index(['project_id', 'created_at']);
        });

        // Completa el contexto de los registros existentes.
        DB::table('audits')->where('auditable_type', 'App\\Models\\Project')->update(['project_id' => DB::raw('auditable_id')]);

        foreach (['App\\Models\\Task' => 'tasks', 'App\\Models\\Comment' => 'comments'] as $type => $table) {
            DB::table('audits')->where('auditable_type', $type)->update([
                'project_id' => DB::table($table)->select('project_id')->whereColumn("{$table}.id", 'audits.auditable_id'),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'created_at']);
            $table->dropColumn('project_id');
        });
    }
};
