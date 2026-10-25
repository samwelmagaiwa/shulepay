<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\Supplier;
use App\Models\SupplierDocument;
use App\Services\Suppliers\SupplierAccess;
use App\Support\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SupplierDocumentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SupplierDocument::with('supplier:id,name,code')->orderBy('expiry_date')->orderByDesc('id');

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->supplier_id);
        }
        if ($request->boolean('expiring')) {
            $query->whereDate('expiry_date', '<=', now()->addDays(SupplierDocument::EXPIRING_DAYS)->toDateString());
        }

        return response()->json($query->get()->map(fn ($d) => $this->present($d)));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => 'required|integer',
            'type' => ['required', Rule::in(SupplierDocument::TYPES)],
            'title' => 'nullable|string|max:150',
            'reference_number' => 'nullable|string|max:80',
            'issue_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after_or_equal:issue_date',
            'notes' => 'nullable|string|max:1000',
            'file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);
        $supplier = Supplier::findOrFail($data['supplier_id']);

        $doc = SupplierDocument::create(Tenant::stripSchoolId([
            'school_id' => $supplier->school_id,
            'supplier_id' => $supplier->id,
            'type' => $data['type'],
            'title' => $data['title'] ?? null,
            'reference_number' => $data['reference_number'] ?? null,
            'issue_date' => $data['issue_date'] ?? null,
            'expiry_date' => $data['expiry_date'] ?? null,
            'notes' => $data['notes'] ?? null,
            'file_path' => $request->hasFile('file') ? $request->file('file')->store('supplier-documents', 'public') : null,
            'recorded_by' => Tenant::actorId(),
        ]));

        return response()->json($this->present($doc->load('supplier:id,name,code')), 201);
    }

    public function destroy(SupplierDocument $document): JsonResponse
    {
        SupplierAccess::authorize('document_delete');
        if ($document->file_path) {
            Storage::disk('public')->delete($document->file_path);
        }
        $document->delete();

        return response()->json(['message' => __('suppliers.document_deleted')]);
    }

    private function present(SupplierDocument $d): array
    {
        return [
            ...$d->toArray(),
            'status' => $d->status(),
            'days_left' => $d->daysLeft(),
            'file_url' => $d->file_path ? Storage::disk('public')->url($d->file_path) : null,
        ];
    }
}
