<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\ProjectStatusController;
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
});
