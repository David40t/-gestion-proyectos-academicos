<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    /**
     * Dashboard adaptado a la perspectiva del usuario (estudiante, líder o docente).
     */
    public function __invoke(Request $request): View
    {
        return view('dashboard.index', [
            'user' => $request->user(),
            ...$this->dashboard->for($request->user()),
        ]);
    }
}
