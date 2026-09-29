<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Dashboard base. Las métricas por rol se incorporan en la Fase 10 (DashboardService).
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('dashboard.index', [
            'user' => $user,
            'roles' => $user->roles->pluck('display_name')->join(', '),
        ]);
    }
}
