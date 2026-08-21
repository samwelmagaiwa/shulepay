<?php

namespace App\Http\Controllers\Accountant;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\FeeStructure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeeStructureController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $structures = FeeStructure::with(['school', 'schoolClass', 'academicYear', 'term', 'feeItems'])
            ->when($request->school_id, fn ($q) => $q->where('school_id', $request->school_id))
            ->when($request->academic_year_id, fn ($q) => $q->where('academic_year_id', $request->academic_year_id))
            ->when($request->term_id, fn ($q) => $q->where('term_id', $request->term_id))
            ->when($request->school_class_id, fn ($q) => $q->where('school_class_id', $request->school_class_id))
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($structures);
    }

    private const VALID_INSTALLMENTS = [1, 2, 3, 4, 6, 12];

    public function store(Request $request): JsonResponse
    {
        $feeMode = $request->input('fee_mode', 'per_term');

        if ($feeMode === 'full_tuition') {
            return $this->storeFullTuition($request);
        }

        $data = $request->validate([
            'school_id' => 'required|exists:schools,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'term_id' => 'required|exists:terms,id',
            'items' => 'required|array|min:1',
            'items.*.name' => 'required|string|max:120',
            'items.*.amount_cents' => 'required|integer|min:0',
            'items.*.is_optional' => 'boolean',
        ]);

        $existing = FeeStructure::where([
            'school_id' => $data['school_id'],
            'school_class_id' => $data['school_class_id'],
            'academic_year_id' => $data['academic_year_id'],
            'term_id' => $data['term_id'],
        ])->first();

        if ($existing) {
            return response()->json(['message' => 'Muundo wa ada tayari upo kwa darasa hili na muhula huu.'], 422);
        }

        $structure = FeeStructure::create([
            'school_id' => $data['school_id'],
            'school_class_id' => $data['school_class_id'],
            'academic_year_id' => $data['academic_year_id'],
            'term_id' => $data['term_id'],
            'fee_mode' => 'per_term',
        ]);

        foreach ($data['items'] as $item) {
            $structure->feeItems()->create([
                'name' => $item['name'],
                'amount_cents' => $item['amount_cents'],
                'is_optional' => $item['is_optional'] ?? false,
            ]);
        }

        return response()->json($structure->load('feeItems'), 201);
    }

    private function storeFullTuition(Request $request): JsonResponse
    {
        $data = $request->validate([
            'school_id' => 'required|exists:schools,id',
            'school_class_id' => 'required|exists:school_classes,id',
            'academic_year_id' => 'required|exists:academic_years,id',
            'full_tuition_cents' => 'required|integer|min:100',
            'installments_count' => ['required', 'integer', 'in:' . implode(',', self::VALID_INSTALLMENTS)],
        ]);

        $year = AcademicYear::with(['terms' => fn ($q) => $q->orderBy('id')])->findOrFail($data['academic_year_id']);
        $terms = $year->terms;

        if ($terms->count() < $data['installments_count']) {
            return response()->json([
                'message' => "Mwaka huu una mihula {$terms->count()} tu, lakini malipo {$data['installments_count']} yanahitajika. Ongeza mihula kwanza.",
            ], 422);
        }

        $perInstallmentCents = (int) round($data['full_tuition_cents'] / $data['installments_count']);
        $monthsPerInstallment = 12 / $data['installments_count'];

        $created = [];
        for ($i = 0; $i < $data['installments_count']; $i++) {
            $term = $terms[$i];

            $existing = FeeStructure::where([
                'school_id' => $data['school_id'],
                'school_class_id' => $data['school_class_id'],
                'academic_year_id' => $data['academic_year_id'],
                'term_id' => $term->id,
            ])->first();

            if ($existing) {
                return response()->json([
                    'message' => "Muundo wa ada tayari upo kwa darasa hili na muhula '{$term->name}'. Futa kwanza kabla ya kuunda upya.",
                ], 422);
            }

            $structure = FeeStructure::create([
                'school_id' => $data['school_id'],
                'school_class_id' => $data['school_class_id'],
                'academic_year_id' => $data['academic_year_id'],
                'term_id' => $term->id,
                'fee_mode' => 'full_tuition',
                'full_tuition_cents' => $data['full_tuition_cents'],
                'installments_count' => $data['installments_count'],
                'installment_number' => $i + 1,
            ]);

            $structure->feeItems()->create([
                'name' => "Ada ya Masomo — Awamu " . ($i + 1) . " ya " . $data['installments_count'],
                'amount_cents' => $perInstallmentCents,
                'is_optional' => false,
            ]);

            $created[] = $structure->load(['term', 'feeItems']);
        }

        return response()->json([
            'message' => "Muundo wa ada kamili umeundwa kwa awamu {$data['installments_count']} (kila miezi {$monthsPerInstallment}).",
            'structures' => $created,
        ], 201);
    }

    public function show(FeeStructure $feeStructure): JsonResponse
    {
        return response()->json($feeStructure->load(['school', 'schoolClass', 'academicYear', 'term', 'feeItems']));
    }

    public function update(Request $request, FeeStructure $feeStructure): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:fee_items,id',
            'items.*.name' => 'required|string|max:120',
            'items.*.amount_cents' => 'required|integer|min:0',
            'items.*.is_optional' => 'boolean',
        ]);

        $keepIds = [];
        foreach ($data['items'] as $item) {
            if (! empty($item['id'])) {
                $feeStructure->feeItems()->where('id', $item['id'])->update([
                    'name' => $item['name'],
                    'amount_cents' => $item['amount_cents'],
                    'is_optional' => $item['is_optional'] ?? false,
                ]);
                $keepIds[] = $item['id'];
            } else {
                $new = $feeStructure->feeItems()->create([
                    'name' => $item['name'],
                    'amount_cents' => $item['amount_cents'],
                    'is_optional' => $item['is_optional'] ?? false,
                ]);
                $keepIds[] = $new->id;
            }
        }

        $feeStructure->feeItems()->whereNotIn('id', $keepIds)->delete();

        return response()->json($feeStructure->load('feeItems'));
    }

    public function destroy(FeeStructure $feeStructure): JsonResponse
    {
        $feeStructure->feeItems()->delete();
        $feeStructure->delete();

        return response()->json(null, 204);
    }
}
