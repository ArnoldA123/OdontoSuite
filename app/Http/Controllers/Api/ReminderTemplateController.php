<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReminderTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Slice 03 (BF-002): full CRUD on /api/reminder-templates. Uses the model
 * directly per design directive (no ReminderTemplateService abstraction).
 * Routes already wrap the apiResource under role:administrador.
 */
class ReminderTemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) min((int) $request->get('per_page', 25), 100);

        $query = ReminderTemplate::query();

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $items = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => $items->items(),
            'meta' => [
                'message' => 'Plantillas de recordatorio cargadas',
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'max:50'],
            'subject' => ['required', 'string', 'max:255'],
            'body_html' => ['required', 'string'],
            'body_text' => ['required', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ]);

        $template = ReminderTemplate::create($data);

        return response()->json([
            'data' => $template,
            'meta' => ['message' => 'Plantilla creada exitosamente'],
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $template = ReminderTemplate::findOrFail($id);

        return response()->json(['data' => $template]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $template = ReminderTemplate::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'type' => ['sometimes', 'string', 'max:50'],
            'subject' => ['sometimes', 'string', 'max:255'],
            'body_html' => ['sometimes', 'string'],
            'body_text' => ['sometimes', 'string'],
            'variables' => ['nullable', 'array'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $template->fill($data)->save();

        return response()->json([
            'data' => $template->fresh(),
            'meta' => ['message' => 'Plantilla actualizada exitosamente'],
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $template = ReminderTemplate::findOrFail($id);
        $template->delete();

        return response()->json(null, 204);
    }
}
