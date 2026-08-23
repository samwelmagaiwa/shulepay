<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * GET /audit-logs
     * Paginated list of audit log entries. Owner only (enforced at route level).
     */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'user' => ['nullable', 'string', 'max:200'],
            'subject_type' => ['nullable', 'string', 'max:200'],
            'action' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $query = AuditLog::with('user')->latest();

        if (! empty($data['user_id'])) {
            $query->where('user_id', $data['user_id']);
        } elseif (! empty($data['user'])) {
            $query->whereHas('user', fn ($q) => $q->where('name', 'like', '%'.$data['user'].'%'));
        }

        if (! empty($data['subject_type'])) {
            // Accept short class names like "Invoice" or full "App\Models\Invoice"
            $type = $data['subject_type'];
            if (! str_contains($type, '\\')) {
                $type = 'App\\Models\\'.$type;
            }
            $query->where('model_type', $type);
        }

        if (! empty($data['action'])) {
            $query->where('action', 'like', '%'.$data['action'].'%');
        }

        if (! empty($data['date_from'])) {
            $query->whereDate('created_at', '>=', $data['date_from']);
        }

        if (! empty($data['date_to'])) {
            $query->whereDate('created_at', '<=', $data['date_to']);
        }

        $logs = $query->paginate(20);

        $items = $logs->getCollection()->map(fn (AuditLog $log) => [
            'id' => $log->id,
            'action' => $log->action,
            'subject_type' => $log->model_type,
            'subject_id' => $log->model_id,
            'changes' => [
                'before' => $log->before,
                'after' => $log->after,
            ],
            'note' => null, // AuditLog model has no 'note' column; include for API consistency
            'user' => $log->user ? ['id' => $log->user->id, 'name' => $log->user->name, 'role' => $log->user->getRoleNames()->first()] : null,
            'ip' => $log->ip_address,
            'created_at' => $log->created_at?->toISOString(),
        ]);

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
