<?php

namespace App\Http\Controllers;

use App\Http\Requests\Audit\AuditFilterRequest;
use App\Models\Audit;
use App\Services\AuditQueryService;
use Illuminate\View\View;

/**
 * Consulta de auditoría: solo index y show. No hay rutas para crear, editar ni eliminar.
 */
class AuditController extends Controller
{
    public function __construct(private readonly AuditQueryService $audits) {}

    public function index(AuditFilterRequest $request): View
    {
        $this->authorize('viewAny', Audit::class);

        return view('audits.index', [
            'audits' => $this->audits->search($request->user(), $request->validated()),
            'projects' => $this->audits->projectOptions($request->user()),
            'filters' => $request->validated(),
        ]);
    }

    public function show(Audit $audit): View
    {
        $this->authorize('view', $audit);

        return view('audits.show', ['audit' => $audit->load(['user:id,name,email', 'project:id,title'])]);
    }
}
