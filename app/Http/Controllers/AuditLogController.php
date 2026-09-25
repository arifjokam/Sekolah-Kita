<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request, AuditLogService $audit): View
    {
        Gate::authorize('viewAny', AuditLog::class);
        $audit->recordOrFail(
            $request->user(),
            'audit_log.viewed',
            'audit_log_collection',
            context: ['filters' => $request->only(['action', 'result', 'target_type'])],
            request: $request,
        );

        $logs = AuditLog::query()
            ->with('actor:id,name')
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')->toString()))
            ->when($request->filled('result'), fn ($query) => $query->where('result', $request->string('result')->toString()))
            ->when($request->filled('target_type'), fn ($query) => $query->where('target_type', $request->string('target_type')->toString()))
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();

        return view('admin.audit-logs.index', compact('logs'));
    }

    public function show(Request $request, AuditLog $auditLog, AuditLogService $audit): View
    {
        Gate::authorize('view', $auditLog);
        $audit->recordOrFail(
            $request->user(),
            'audit_log.viewed',
            'audit_log',
            $auditLog->getKey(),
            request: $request,
        );

        return view('admin.audit-logs.show', [
            'auditLog' => $auditLog->load('actor:id,name'),
        ]);
    }
}
