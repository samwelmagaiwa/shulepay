<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PettyCash;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PettyCashController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $entries = PettyCash::with('recorder')
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->paginate(min(max($request->integer('per_page', 20), 5), 200));

        $latest = PettyCash::orderBy('entry_date', 'desc')->orderBy('id', 'desc')->first();
        $sumOf = fn (string $type) => (int) PettyCash::where('type', $type)->sum('amount_cents');

        return response()->json([
            'data' => $entries->values()->map(fn ($e) => $e->toArray() + ['recorder_name' => $e->recorder?->name]),
            // what the cards show: the whole fund, not only the page on screen
            'summary' => [
                'balance_cents' => (int) ($latest?->balance_after_cents?->cents() ?? 0),
                'in_cents' => $sumOf('top_up'),
                'out_cents' => $sumOf('expense'),
            ],
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|in:top_up,expense',
            'amount_cents' => 'required|integer|min:1',
            'description' => 'required|string|max:500',
            'reference' => 'nullable|string|max:255',
            'entry_date' => 'required|date',
        ]);

        return DB::transaction(function () use ($data) {
            // petty_cash_entries carries no school_id in a tenant database — every
            // row there already belongs to the one school.
            $schoolId = Tenant::active() ? null : (app()->bound('active_school') ? app('active_school')?->id : null);

            // Get current balance from last entry
            $lastEntry = PettyCash::when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->orderBy('entry_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            $currentBalance = $lastEntry ? (int) $lastEntry->getRawOriginal('balance_after_cents') : 0;

            if ($data['type'] === 'top_up') {
                $balanceAfter = $currentBalance + $data['amount_cents'];
            } else {
                $balanceAfter = $currentBalance - $data['amount_cents'];
                if ($balanceAfter < 0) {
                    return response()->json(['message' => __('messages.insufficient_petty_cash_balance')], 422);
                }
            }

            $data['balance_after_cents'] = $balanceAfter;
            $data['recorded_by'] = Tenant::actorId();

            $entry = PettyCash::create($data);
            AuditLog::record('petty_cash.created', $entry, [], $data);

            return response()->json($entry->load('recorder'), 201);
        });
    }

    public function balance(): JsonResponse
    {
        $schoolId = Tenant::active() ? null : (app()->bound('active_school') ? app('active_school')?->id : null);

        $lastEntry = PettyCash::when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
            ->orderBy('entry_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        $balance = $lastEntry ? (int) $lastEntry->getRawOriginal('balance_after_cents') : 0;

        return response()->json(['balance_cents' => $balance]);
    }
}
