<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DentalPiece;
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
            ->orderBy('fdi_number')
            ->get(['id', 'fdi_number', 'name', 'type', 'quadrant']);

        return response()->json([
            'data' => $pieces,
            'meta' => [
                'message' => 'Piezas dentales cargadas exitosamente',
                'total' => $pieces->count(),
            ],
        ]);
    }
}
