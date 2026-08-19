<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PettyCash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PettyCashController extends Controller {
    public function index(Request $request): JsonResponse {
        $entries = PettyCash::with('recorder')
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $entries->values(),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page'    => $entries->lastPage(),
                'per_page'     => $entries->perPage(),
                'total'        => $entries->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse {
        $data = $request->validate([
            'type'        => 'required|in:top_up,expense',
            'amount_cents' => 'required|integer|min:1',
            'description' => 'required|string|max:500',
            'reference'   => 'nullable|string|max:255',
            'entry_date'  => 'required|date',
        ]);

        return DB::transaction(function () use ($data) {
            $school = app()->bound('active_school') ? app('active_school') : null;
            $schoolId = $school?->id;

            // Get current balance from last entry
            $lastEntry = PettyCash::when($schoolId, fn($q) => $q->where('school_id', $schoolId))
                ->orderBy('entry_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $currentBalance = $lastEntry ? (int) $lastEntry->getRawOriginal('balance_after_cents') : 0;

            if ($data['type'] === 'top_up') {
                $balanceAfter = $currentBalance + $data['amount_cents'];
            } else {
                $balanceAfter = $currentBalance - $data['amount_cents'];
                if ($balanceAfter < 0) {
                    return response()->json(['message' => 'Insufficient petty cash balance.'], 422);
                }
            }

            $data['balance_after_cents'] = $balanceAfter;
            $data['recorded_by']         = auth()->id();

            $entry = PettyCash::create($data);
            AuditLog::record('petty_cash.created', $entry, [], $data);

            return response()->json($entry->load('recorder'), 201);
        });
    }

    public function balance(): JsonResponse {
        $school = app()->bound('active_school') ? app('active_school') : null;
        $schoolId = $school?->id;

        $lastEntry = PettyCash::when($schoolId, fn($q) => $q->where('school_id', $schoolId))
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $balance = $lastEntry ? (int) $lastEntry->getRawOriginal('balance_after_cents') : 0;

        return response()->json(['balance_cents' => $balance]);
    }
}
