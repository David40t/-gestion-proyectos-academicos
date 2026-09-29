<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

/*
| Las rutas de autenticación (login, logout, registro, recuperación de contraseña)
| las registra Laravel Fortify. Ver config/fortify.php.
*/

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
});
