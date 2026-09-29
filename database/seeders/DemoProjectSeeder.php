<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\ProjectService;
use Illuminate\Database\Seeder;

/**
 * Proyecto de demostración creado mediante ProjectService, para que cumpla las mismas
 * reglas de negocio que un proyecto creado desde la interfaz (líder, rol LIDER, auditoría).
 */
class DemoProjectSeeder extends Seeder
{
    public function run(ProjectService $projects): void
    {
        $leader = User::where('email', 'lider@demo.test')->firstOrFail();

        if ($leader->ledProjects()->exists()) {
            return; // idempotente
        }

        $projects->create($leader, [
            'title' => 'Sistema de gestión de proyectos académicos',
            'description' => 'Aplicación web para centralizar la gestión y el seguimiento de proyectos universitarios.',
            'objectives' => "Centralizar la información de los proyectos.\nFacilitar el seguimiento docente.",
            'start_date' => now()->subWeeks(2)->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'teacher_id' => User::where('email', 'docente@demo.test')->value('id'),
            'member_ids' => [User::where('email', 'estudiante@demo.test')->value('id')],
        ]);
    }
}
