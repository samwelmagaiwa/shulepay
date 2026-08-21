<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiscountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $discounts = Discount::with(['student', 'invoice', 'appliedBy'])
            ->when($request->student_id, fn ($q) => $q->where('student_id', $request->student_id))
            ->when($request->invoice_id, fn ($q) => $q->where('invoice_id', $request->invoice_id))
            ->latest()
            ->paginate(50);

        return response()->json($discounts);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'invoice_id' => 'required|exists:invoices,id',
            'amount_cents' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
        ]);

        $invoice = Invoice::with('payments')->findOrFail($data['invoice_id']);

        if ($data['amount_cents'] > $invoice->balanceDueCents()) {
            return response()->json(['message' => 'Discount cannot exceed the remaining balance.'], 422);
        }

        $discount = DB::transaction(function () use ($data, $invoice) {
            $d = Discount::create([
                'invoice_id' => $invoice->id,
                'student_id' => $invoice->student_id,
                'type' => 'general',
                'amount_cents' => $data['amount_cents'],
                'reason' => $data['reason'],
                'applied_by' => auth()->id(),
                'applied_at' => now(),
            ]);

            // Recalculate invoice status
            $totalPaid = $invoice->payments()->sum('amount_cents');
            $totalDiscounts = $invoice->discounts()->sum('amount_cents');
            $balance = $invoice->total_amount_cents - $totalPaid - $totalDiscounts;

            $invoice->update([
                'status' => $balance <= 0 ? 'paid' : ($totalPaid > 0 ? 'partial' : 'unpaid'),
            ]);

            return $d;
        });

        return response()->json($discount->load(['student', 'invoice', 'appliedBy']), 201);
    }

    public function destroy(Discount $discount): JsonResponse
    {
        DB::transaction(function () use ($discount) {
            $invoice = $discount->invoice()->with('payments', 'discounts')->first();
            $discount->delete();

            $totalPaid = $invoice->payments()->sum('amount_cents');
            $totalDiscounts = $invoice->discounts()->sum('amount_cents');
            $balance = $invoice->total_amount_cents - $totalPaid - $totalDiscounts;

            $invoice->update([
                'status' => $balance <= 0 ? 'paid' : ($totalPaid > 0 ? 'partial' : 'unpaid'),
            ]);
        });

        return response()->json(null, 204);
    }
}
