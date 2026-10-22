<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DentalPiece;
use App\Models\ToothSurface;
use Illuminate\Http\JsonResponse;

/**
 * Read-only catalog of the FDI dental pieces (issue #212).
 *
 * The specialty-record forms need to reference the piece a clinical record
 * belongs to: three of the five tables declare `dental_piece_id` as NOT NULL,
 * and until now the client had no way to discover the ids. This endpoint is
 * deliberately read-only; the catalog is seeded, not administered from the UI.
 */
class DentalPieceController extends Controller
{
    public function index(): JsonResponse
    {
        $pieces = DentalPiece::query()
            ->where('is_active', true)
            // #293 S1/S2: a surface procedure needs the surface of its piece,
            // so the catalog ships the five canonical surfaces per piece in the
            // same response instead of one request per row.
            ->with(['toothSurfaces' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('surface_code'),
            ])
            ->orderBy('fdi_number')
            ->get(['id', 'fdi_number', 'name', 'type', 'quadrant'])
            ->map(fn (DentalPiece $piece) => [
                'id' => $piece->id,
                'fdi_number' => $piece->fdi_number,
                'name' => $piece->name,
                'type' => $piece->type,
                'quadrant' => $piece->quadrant,
                'surfaces' => $piece->toothSurfaces
                    ->map(fn (ToothSurface $surface) => [
                        'id' => $surface->id,
                        'surface_code' => $surface->surface_code,
                        'surface_name' => $surface->surface_name,
                        'abbreviation' => $surface->abbreviation,
                    ])
                    ->values()
                    ->all(),
            ]);

        return response()->json([
            'data' => $pieces,
            'meta' => [
                'message' => 'Piezas dentales cargadas exitosamente',
                'total' => $pieces->count(),
            ],
        ]);
    }
}
