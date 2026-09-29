<?php

use App\Http\Controllers\CommentController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MyTaskController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\ProjectStatusController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskProgressController;
use Illuminate\Support\Facades\Route;

/*
| Las rutas de autenticación (login, logout, registro, recuperación de contraseña)
| las registra Laravel Fortify. Ver config/fortify.php.
|
| La autorización fina (¿puede este usuario actuar sobre ESTE registro?) la hace cada
| Controller mediante su Policy; aquí solo se exige sesión iniciada.
*/

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Proyectos
    Route::resource('projects', ProjectController::class);
    Route::patch('projects/{project}/status', [ProjectStatusController::class, 'update'])->name('projects.status.update');

    // Integrantes
    Route::post('projects/{project}/members', [ProjectMemberController::class, 'store'])->name('projects.members.store');
    Route::delete('projects/{project}/members/{member}', [ProjectMemberController::class, 'destroy'])->name('projects.members.destroy');
    Route::patch('projects/{project}/leader', [ProjectMemberController::class, 'updateLeader'])->name('projects.leader.update');

    // Tareas (scoped: una tarea solo se resuelve dentro de su propio proyecto; destroy = eliminación lógica)
    Route::resource('projects.tasks', TaskController::class)->scoped();
    Route::patch('projects/{project}/tasks/{task}/restore', [TaskController::class, 'restore'])
        ->withTrashed()->scopeBindings()->name('projects.tasks.restore');
    Route::patch('projects/{project}/tasks/{task}/progress', [TaskProgressController::class, 'update'])
        ->scopeBindings()->name('projects.tasks.progress.update');
    Route::get('my-tasks', MyTaskController::class)->name('tasks.mine');

    // Comentarios (del proyecto o de una de sus tareas; scoped: el comentario debe pertenecer al proyecto)
    Route::resource('projects.comments', CommentController::class)->only(['store', 'update', 'destroy'])->scoped();

    // Notificaciones internas (solo las propias; permisos verificados por el Gate de permisos)
    Route::get('notifications', [NotificationController::class, 'index'])
        ->middleware('can:notificacion.ver')->name('notifications.index');
    Route::patch('notifications/read-all', [NotificationController::class, 'readAll'])
        ->middleware('can:notificacion.marcar_leida')->name('notifications.read-all');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'read'])
        ->middleware('can:notificacion.marcar_leida')->whereUuid('notification')->name('notifications.read');
});
