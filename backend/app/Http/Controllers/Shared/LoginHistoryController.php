<?php

namespace App\Http\Controllers\Shared;

use App\Http\Controllers\Controller;
use App\Models\LoginHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoginHistoryController extends Controller
{
    /**
     * GET /api/login-history
     * Owner only. Paginated login history with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = LoginHistory::with('user:id,name,email')
            ->latest('attempted_at');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('from')) {
            $query->whereDate('attempted_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('attempted_at', '<=', $request->to);
        }

        $records = $query->paginate(50);

        $records->getCollection()->transform(function ($item) {
            return [
                'id' => $item->id,
                'user' => $item->user ? ['id' => $item->user->id, 'name' => $item->user->name] : null,
                'ip_address' => $item->ip_address,
                'user_agent' => mb_substr($item->user_agent ?? '', 0, 100),
                'status' => $item->status,
                'attempted_at' => $item->attempted_at,
            ];
        });

        return response()->json($records);
    }
}
