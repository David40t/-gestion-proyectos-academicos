<?php

namespace Tests\Feature\Errors;

use App\Enums\ProjectStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\Concerns\CreatesProjects;
use Tests\TestCase;

class ErrorHandlingTest extends TestCase
{
    use CreatesProjects, RefreshDatabase;

    public function test_validation_messages_are_in_spanish_with_readable_names(): void
    {
        $student = $this->userWithRoles(Role::ESTUDIANTE);

        $this->actingAs($student)
            ->post('/projects', ['title' => '', 'start_date' => '2026-10-10', 'end_date' => '2026-10-01'])
            ->assertSessionHasErrors([
                'title' => 'El campo título es obligatorio.',
                'end_date' => 'La fecha de finalización debe ser igual o posterior a la fecha de inicio.',
                'teacher_id' => 'El campo docente responsable es obligatorio.',
            ]);
    }

    public function test_date_rules_show_readable_references(): void
    {
        $this->createProjectScenario(ProjectStatus::EnProgreso);
        $this->project->update(['start_date' => now()->subMonth(), 'end_date' => now()->addMonth()]);

        $this->actingAs($this->leader)
            ->post(route('projects.tasks.store', $this->project), [
                'title' => 'Tarea', 'priority' => 'media',
                'start_date' => now()->subDays(5)->toDateString(),
                'due_date' => now()->subDay()->toDateString(),
            ])
            ->assertSessionHasErrors(['due_date' => 'El campo fecha límite debe ser una fecha posterior o igual a hoy.']);
    }

    public function test_registration_errors_are_in_spanish(): void
    {
        $this->post('/register', ['name' => '', 'email' => 'x', 'password' => '123', 'password_confirmation' => '321'])
            ->assertSessionHasErrors([
                'name' => 'El campo nombre es obligatorio.',
                'email' => 'El campo correo electrónico debe ser un correo electrónico válido.',
            ]);
    }

    public function test_custom_404_page(): void
    {
        $this->actingAs($this->userWithRoles(Role::ESTUDIANTE))
            ->get('/projects/999999')
            ->assertNotFound()
            ->assertSee('Página no encontrada')
            ->assertSee('Ir al inicio');
    }

    public function test_custom_403_page(): void
    {
        $this->actingAs($this->userWithRoles(Role::DOCENTE))
            ->get('/projects/create')
            ->assertForbidden()
            ->assertSee('Acceso denegado');
    }

    public function test_custom_405_page_for_audit_modification(): void
    {
        $this->actingAs($this->userWithRoles(Role::DOCENTE))
            ->delete('/audits/1')
            ->assertStatus(405)
            ->assertSee('Operación no permitida');
    }

    public function test_session_expired_page_renders(): void
    {
        $html = view('errors.419')->render();

        $this->assertStringContainsString('La sesión expiró', $html);
    }

    public function test_unexpected_errors_show_a_generic_page_without_internal_details(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/_prueba-error-500', fn () => throw new RuntimeException('Detalle interno SQL secreto'));

        $this->get('/_prueba-error-500')
            ->assertStatus(500)
            ->assertSee('Error interno')
            ->assertDontSee('Detalle interno SQL secreto')
            ->assertDontSee('RuntimeException');
    }

    public function test_business_rule_violations_are_not_reported_as_errors(): void
    {
        Exceptions::fake();
        $this->createProjectScenario();

        $this->actingAs($this->leader)
            ->delete(route('projects.members.destroy', [$this->project, $this->leader]))
            ->assertSessionHas('error');

        Exceptions::assertNotReported(BusinessRuleException::class);
    }

    public function test_business_rule_errors_keep_the_form_input(): void
    {
        $this->createProjectScenario(ProjectStatus::EnProgreso);
        $url = route('projects.show', $this->project);

        $this->actingAs($this->member)->from($url)
            ->post(route('projects.comments.store', $this->project), ['body' => 'Mi texto', 'task_id' => 999999])
            ->assertRedirect($url)
            ->assertSessionHas('error')
            ->assertSessionHasInput('body', 'Mi texto');
    }
}
