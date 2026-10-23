<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DiagnosisCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * F6 (#286) — the read surface of the CIE-10 catalogue.
 *
 * The catalogue is a reference table: the clinical record reads it to code a
 * diagnosis, and the record itself freezes the code it used. There is no write
 * door on purpose —a professional does not invent a CIE-10 code at the chair—
 * so the seeded catalogue is the only source of codes.
 */
class DiagnosisCatalogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q' => 'sometimes|nullable|string|max:100',
            'category' => 'sometimes|nullable|string|max:100',
            'specialty' => 'sometimes|nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = DiagnosisCatalog::query()->search($data['q'] ?? null);

        if (! empty($data['category'])) {
            $query->where('category', $data['category']);
        }

        if (! empty($data['specialty'])) {
            $query->where('specialty', $data['specialty']);
        }

        // Only active entries can be coded, so the picker of the close reads
        // active ones by default; the catalogue itself is still readable whole.
        $query->where('is_active', array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true);

        $page = $query->orderBy('cie_code')->paginate((int) ($data['per_page'] ?? 20));

        return response()->json([
            'data' => array_map(
                fn (DiagnosisCatalog $diagnosis): array => $this->resource($diagnosis),
                $page->items(),
            ),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    public function show(DiagnosisCatalog $diagnosis): JsonResponse
    {
        return response()->json([
            'data' => $this->resource($diagnosis),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function resource(DiagnosisCatalog $diagnosis): array
    {
        return [
            'id' => $diagnosis->id,
            'cie_code' => $diagnosis->cie_code,
            'name' => $diagnosis->name,
            'description' => $diagnosis->description,
            'category' => $diagnosis->category,
            'specialty' => $diagnosis->specialty,
            'symptoms' => $diagnosis->symptoms,
            'treatment_guidelines' => $diagnosis->treatment_guidelines,
            'prevention_measures' => $diagnosis->prevention_measures,
            'is_active' => (bool) $diagnosis->is_active,
        ];
    }
}
