<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('description');
            $table->text('objectives')->nullable();
            // Valores definidos en App\Enums\ProjectStatus (ADR-004b).
            $table->string('status', 20)->default('planeacion')->index();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->foreignId('leader_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->softDeletes();
            // Sin columna "progress": el avance se calcula desde las tareas (ADR-005).
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
