<?php

namespace App\Support;

use App\Models\ProcedureCatalog;
use App\Models\ToothSurface;
use Illuminate\Validation\ValidationException;

/**
 * #293 S1/S2 — the single applicability rule.
 *
 * A procedure declares what it applies to (`site_type`). A line that names such
 * a procedure must carry the matching site: the piece for a tooth, the surface
 * for a surface (which already names its piece), the quadrant or arch code.
 * `none` requires nothing and never forbids a declared site.
 *
 * The rule is applied by both line writers —the treatment plan and the
 * quotation— because the estimate inherits what the plan declared, and by the
 * plan-to-quotation copy that finds lines written before this rule existed.
 */
class ProcedureSiteRule
{
    public const QUADRANTS = [
        'superior_derecho',
        'superior_izquierdo',
        'inferior_derecho',
        'inferior_izquierdo',
    ];

    public const ARCHES = ['superior', 'inferior'];

    /**
     * Normalize a raw site payload into the three columns a line stores.
     *
     * A surface is the whole address of its piece, so the piece is derived from
     * it; when both arrive and disagree, the surface wins.
     *
     * @param  array<string, mixed>                                                     $site
     * @return array{dental_piece_id: ?int, tooth_surface_id: ?int, site_code: ?string}
     */
    public static function normalize(array $site): array
    {
        $dentalPieceId = self::integerOrNull($site['dental_piece_id'] ?? null);
        $toothSurfaceId = self::integerOrNull($site['tooth_surface_id'] ?? null);
        $siteCode = self::stringOrNull($site['site_code'] ?? null);

        if ($toothSurfaceId !== null) {
            $surface = ToothSurface::find($toothSurfaceId);

            if ($surface !== null) {
                $dentalPieceId = (int) $surface->dental_piece_id;
            }
        }

        return [
            'dental_piece_id' => $dentalPieceId,
            'tooth_surface_id' => $toothSurfaceId,
            'site_code' => $siteCode,
        ];
    }

    /**
     * Refuse a line whose procedure demands a site the payload does not carry,
     * or whose declared code is outside the vocabulary of its `site_type`.
     *
     * @param array<string, mixed> $site the normalized site
     *
     * @throws ValidationException
     */
    public static function assertFor(?ProcedureCatalog $procedure, array $site): void
    {
        if ($procedure === null) {
            return;
        }

        $type = $procedure->site_type ?? 'none';
        $name = $procedure->name;

        $piece = $site['dental_piece_id'] ?? null;
        $surface = $site['tooth_surface_id'] ?? null;
        $code = $site['site_code'] ?? null;

        $message = match (true) {
            $type === 'tooth' && $piece === null => "El procedimiento «{$name}» se realiza sobre una pieza dental: falta la pieza.",
            $type === 'surface' && $surface === null => "El procedimiento «{$name}» se realiza sobre una superficie dental: falta la superficie dental.",
            $type === 'quadrant' && $code === null => "El procedimiento «{$name}» se realiza sobre un cuadrante: falta el cuadrante (".implode(', ', self::QUADRANTS).').',
            $type === 'quadrant' && ! in_array($code, self::QUADRANTS, true) => "El cuadrante declarado no es válido para el procedimiento «{$name}».",
            $type === 'arch' && $code === null => "El procedimiento «{$name}» se realiza sobre una arcada: falta la arcada (".implode(' o ', self::ARCHES).').',
            $type === 'arch' && ! in_array($code, self::ARCHES, true) => "La arcada declarada no es válida para el procedimiento «{$name}».",
            default => null,
        };

        if ($message !== null) {
            throw ValidationException::withMessages(['items' => [$message]]);
        }
    }

    private static function integerOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
