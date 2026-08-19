<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Http\Resources\SupplierResource;
use App\Models\AuditLog;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplierController extends Controller {
    public function index(Request $request): AnonymousResourceCollection {
        $query = Supplier::with('school')->latest();

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        return SupplierResource::collection($query->paginate(20));
    }

    public function store(Request $request): JsonResponse {
        $data = $request->validate([
            'name'         => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone'        => 'nullable|string|max:50',
            'email'        => 'nullable|email|max:255',
            'address'      => 'nullable|string',
        ]);

        $supplier = Supplier::create($data);
        AuditLog::record('supplier.created', $supplier, [], $data);

        return response()->json(new SupplierResource($supplier->load('school')), 201);
    }

    public function show(Supplier $supplier): JsonResponse {
        return response()->json(new SupplierResource($supplier->load('school')));
    }

    public function update(Request $request, Supplier $supplier): JsonResponse {
        $data = $request->validate([
            'name'         => 'sometimes|required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone'        => 'nullable|string|max:50',
            'email'        => 'nullable|email|max:255',
            'address'      => 'nullable|string',
        ]);

        $before = $supplier->toArray();
        $supplier->update($data);
        AuditLog::record('supplier.updated', $supplier, $before, $data);

        return response()->json(new SupplierResource($supplier->load('school')));
    }

    public function destroy(Supplier $supplier): JsonResponse {
        AuditLog::record('supplier.deleted', $supplier, $supplier->toArray(), []);
        $supplier->delete();

        return response()->json(['message' => 'Supplier deleted.']);
    }
}
